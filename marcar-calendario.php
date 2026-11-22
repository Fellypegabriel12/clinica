<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';

$u   = exigir_login();
$pdo = db();

$c         = carregar_marcacao($pdo, $u['id']);
$dentista  = $c['dentista'];
$pacientes = $c['pacientes'];
$prefixo   = $c['prefixo'];
$avisos    = $c['avisos'];
$temHorarioProprio = $c['dentista_tem_horario'];

$duracaoProvisoria = DURACAO_PADRAO_MIN;

$hoje   = utc(agora_bd($pdo)->format('Y-m-d') . ' 00:00:00');
$maxDia = $hoje->modify('+' . DIAS_ANTECEDENCIA_MAX . ' days');

function ler_dia(string $texto, DateTimeImmutable $hoje, DateTimeImmutable $maxDia): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $texto, new DateTimeZone('UTC'));
    return ($d !== false && $d->format('Y-m-d') === $texto && $d >= $hoje && $d <= $maxDia) ? $d : null;
}

$diaEscolhido = ler_dia((string)($_GET['data'] ?? ''), $hoje, $maxDia);

if ($diaEscolhido === null) {
    $diaEscolhido = proxima_data_livre($pdo, (int)$dentista['id'], $hoje, $prefixo) ?? $hoje;
}

$erros = [];
$horariosAtuais = $_SESSION['marcacao']['horarios'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $recebidos = (array)($_POST['horarios'] ?? []);
    $novos     = [];
    $falhas    = [];

    foreach ($pacientes as $p) {
        $pid = (int)$p['id'];
        $novoInicio = trim((string)($recebidos[$pid] ?? ''));

        if ($novoInicio === '') {
            $falhas[] = 'Escolhe um horário para ' . $p['nome_completo'] . '.';
            continue;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:00$/', $novoInicio)) {
            $falhas[] = 'Horário inválido para ' . $p['nome_completo'] . '.';
            continue;
        }
        $novos[$pid] = $novoInicio;
    }

    if ($falhas === []) {
        try {
            $pdo->beginTransaction();
            $duracaoPorPaciente = [];
            foreach ($novos as $pid => $inicio) {
                if (isset($horariosAtuais[$pid]) && $horariosAtuais[$pid]['inicio'] === $inicio) {
                    $duracaoPorPaciente[$pid] = (int)$horariosAtuais[$pid]['duracao'];
                    continue;
                }
                reservar_horario($pdo, (int)$dentista['id'], (int)$pid, $inicio, $duracaoProvisoria, $prefixo);
                $duracaoPorPaciente[$pid] = $duracaoProvisoria;
            }
            $pdo->commit();

            $_SESSION['marcacao']['horarios'] = [];
            foreach ($novos as $pid => $inicio) {
                $_SESSION['marcacao']['horarios'][$pid] = [
                    'inicio'  => $inicio,
                    'duracao' => $duracaoPorPaciente[$pid],
                ];
            }

            redirecionar('marcar-tratamentos.php');
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $erros[] = 'Não foi possível reservar: ' . $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Erro a reservar horário: ' . $e->getMessage());
            $erros[] = 'Ocorreu um erro. Tenta novamente.';
        }
    } else {
        $erros = array_merge($erros, $falhas);
    }
}

$slots = slots_do_dia($pdo, (int)$dentista['id'], $diaEscolhido, $prefixo);

$opcoesPorPaciente = [];
foreach ($pacientes as $p) {
    $pid = (int)$p['id'];
    $opcoes = [];
    foreach ($slots as $s) {
        $chaves = blocos_da_consulta($pdo, $s['inicio'], $duracaoProvisoria);
        if (inicio_valido($slots, $chaves, token_paciente($prefixo, $pid))) {
            $fimDT = utc($s['inicio'])->modify('+' . $duracaoProvisoria . ' minutes');
            $opcoes[] = [
                'inicio' => $s['inicio'],
                'texto'  => $s['hora'] . ' – ' . $fimDT->format('H:i'),
            ];
        }
    }
    $opcoesPorPaciente[$pid] = $opcoes;
}

$anterior = $diaEscolhido->modify('-1 day');
$seguinte = $diaEscolhido->modify('+1 day');

$rotulos = [
    'livre'           => ['✔', 'Livre'],
    'ocupado'         => ['✖', 'Ocupado'],
    'indisponivel'    => ['—', 'Indisponível'],
    'reservado_outro' => ['⏳', 'Reservado'],
    'reservado_meu'   => ['★', 'Reservado para ti'],
];

$tituloPagina = 'Marcar: horário';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_marcacao();
?>

<div class="dash-root">

    <div class="dash-page-top">
        <div>
            <span class="dash-kicker">Marcação · Passo 2</span>
            <h1>Escolher horário</h1>
            <p>Marcação com <strong><?= e($dentista['nome_completo']) ?></strong> · <?= e(data_extenso($diaEscolhido)) ?></p>
        </div>
    </div>

    <?= passos_marcacao(2) ?>

    <?php if (!$temHorarioProprio): ?>
        <div class="alerta alerta-info" role="status">
            <strong>Nota:</strong> está a ser usado o horário geral da clínica para este profissional.
            <?php if (in_array($u['papel'] ?? '', ['admin','rececao'], true)): ?>
                Pode configurar o horário em <a href="admin/horarios.php?alvo=<?= (int)$dentista['id'] ?>">Horários</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($avisos as $aviso): ?>
        <div class="alerta alerta-info" role="status"><?= e($aviso) ?></div>
    <?php endforeach; ?>
    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <div class="navdia">
        <?php if ($anterior >= $hoje): ?>
            <a class="botao botao-secundario" href="marcar-calendario.php?data=<?= e($anterior->format('Y-m-d')) ?>">← Dia anterior</a>
        <?php endif; ?>
        <form method="get" action="marcar-calendario.php">
            <label for="data">Ir para</label>
            <input type="date" id="data" name="data" value="<?= e($diaEscolhido->format('Y-m-d')) ?>"
                   min="<?= e($hoje->format('Y-m-d')) ?>" max="<?= e($maxDia->format('Y-m-d')) ?>">
            <button type="submit" class="botao">Ver</button>
        </form>
        <?php if ($seguinte <= $maxDia): ?>
            <a class="botao botao-secundario" href="marcar-calendario.php?data=<?= e($seguinte->format('Y-m-d')) ?>">Dia seguinte →</a>
        <?php endif; ?>
    </div>

    <ul class="legenda" aria-label="Legenda">
        <?php foreach ($rotulos as $estado => [$simbolo, $texto]): ?>
            <li class="bloco bloco-<?= e($estado) ?>"><?= e($simbolo . ' ' . $texto) ?></li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$slots): ?>
        <div class="alerta alerta-info">A clínica está fechada neste dia.</div>
    <?php else: ?>
        <ul class="calendario" aria-label="Horários de <?= e(data_extenso($diaEscolhido)) ?>">
            <?php foreach ($slots as $s): [$simbolo, $texto] = $rotulos[$s['estado']]; ?>
                <li class="bloco bloco-<?= e($s['estado']) ?>">
                    <strong><?= e($s['hora']) ?></strong>
                    <span><?= e($simbolo . ' ' . $texto) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php
    $haOpcoes = true;
    foreach ($pacientes as $p) {
        if (empty($opcoesPorPaciente[(int)$p['id']])) { $haOpcoes = false; break; }
    }
    ?>

    <?php if ($haOpcoes): ?>
        <section class="cartao seccao">
            <h2>Escolher hora para cada pessoa</h2>
            <p class="ajuda">
                Duração provisória: <?= e(formatar_duracao($duracaoProvisoria)) ?>.
                Podes ajustar depois de escolher os tratamentos.
            </p>

            <form method="post" action="marcar-calendario.php" novalidate>
                <?= csrf_campo() ?>

                <?php foreach ($pacientes as $p): $pid = (int)$p['id']; ?>
                    <div class="campo">
                        <label for="h-<?= $pid ?>"><?= e($p['nome_completo']) ?></label>
                        <select id="h-<?= $pid ?>" name="horarios[<?= $pid ?>]" required>
                            <option value="">— escolher —</option>
                            <?php foreach ($opcoesPorPaciente[$pid] as $o): ?>
                                <option value="<?= e($o['inicio']) ?>"
                                    <?= (($horariosAtuais[$pid]['inicio'] ?? '') === $o['inicio']) ? 'selected' : '' ?>>
                                    <?= e($o['texto']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>

                <a class="botao botao-secundario" href="marcar-pacientes.php">← Pacientes</a>
                <button type="submit" class="botao">Continuar para tratamentos →</button>
            </form>
        </section>
    <?php elseif ($slots): ?>
        <div class="alerta alerta-info">
            Não há horas livres neste dia para uma consulta de <?= e(formatar_duracao($duracaoProvisoria)) ?> em todos os pacientes.
            Escolhe outro dia na navegação acima.
        </div>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>