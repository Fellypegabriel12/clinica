<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';

$u   = exigir_login(['admin']);
$pdo = db();

$dentistas  = $pdo->query('SELECT id, nome_completo FROM dentistas WHERE ativo = 1 ORDER BY nome_completo')->fetchAll();
$idsValidos = array_map('intval', array_column($dentistas, 'id'));

// Converte o valor de um <input type="datetime-local"> (2026-12-24T09:00) em objeto de data, ou null
function ler_data_hora(string $texto): ?DateTime
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', trim($texto));
    return ($d !== false && $d->format('Y-m-d\TH:i') === trim($texto)) ? $d : null;
}

$erros = [];
$form  = ['dentista' => '0', 'inicio' => '', 'fim' => '', 'motivo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    // --- Apagar ---
    if ($acao === 'apagar') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM bloqueios_horario WHERE id = ?')->execute([$id]);
        registar_admin('apagar_bloqueio', 'bloqueios_horario', $id);
        flash_definir('sucesso', 'Bloqueio removido.');
        redirecionar('admin/bloqueios.php');
    }

    // --- Criar ---
    if ($acao === 'criar') {
        $form['dentista'] = (string)(int)($_POST['dentista'] ?? 0);   // 0 = clínica toda
        $form['inicio']   = (string)($_POST['inicio'] ?? '');
        $form['fim']      = (string)($_POST['fim'] ?? '');
        $form['motivo']   = trim((string)($_POST['motivo'] ?? ''));

        $dentistaId = (int)$form['dentista'];
        $inicio     = ler_data_hora($form['inicio']);
        $fim        = ler_data_hora($form['fim']);

        if ($dentistaId !== 0 && !in_array($dentistaId, $idsValidos, true)) {
            $erros[] = 'Escolhe um dentista válido.';
        }
        if ($inicio === null || $fim === null) {
            $erros[] = 'Indica a data e hora de início e de fim.';
        } elseif ($fim <= $inicio) {
            $erros[] = 'O fim tem de ser depois do início.';
        }
        if (mb_strlen($form['motivo']) > 150) {
            $erros[] = 'O motivo não pode ter mais de 150 caracteres.';
        }

        if ($erros === []) {
            try {
                $pdo->prepare(
                    'INSERT INTO bloqueios_horario (dentista_id, inicio, fim, motivo, criado_por)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $dentistaId ?: null,
                    $inicio->format('Y-m-d H:i:00'),
                    $fim->format('Y-m-d H:i:00'),
                    $form['motivo'] ?: null,
                    $u['id'],
                ]);
                registar_admin('criar_bloqueio', 'bloqueios_horario', (int)$pdo->lastInsertId(), $form['motivo'] ?: null);
                flash_definir('sucesso', 'Bloqueio criado.');
                redirecionar('admin/bloqueios.php');
            } catch (PDOException $e) {
                error_log('Erro ao criar bloqueio: ' . $e->getMessage());
                $erros[] = 'Ocorreu um erro. Tenta novamente.';
            }
        }
    }
}

// Bloqueios que ainda não terminaram
$bloqueios = $pdo->query(
    'SELECT b.id, b.inicio, b.fim, b.motivo, d.nome_completo
       FROM bloqueios_horario b
       LEFT JOIN dentistas d ON d.id = b.dentista_id
      WHERE b.fim >= NOW()
      ORDER BY b.inicio
      LIMIT 200'
)->fetchAll();

$tituloPagina = 'Bloqueios de horário';
require __DIR__ . '/../includes/cabecalho.php';
?>

<style>
    .campo input[type="datetime-local"] {
        padding: .65rem .8rem; font-size: 1rem; font-family: inherit;
        border: 1px solid var(--borda); border-radius: 8px;
        background: var(--fundo); color: var(--texto);
    }
</style>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Bloqueios de horário</h1>
<p class="ajuda">Usa isto para férias, folgas, feriados ou formações. Durante um bloqueio, essas horas aparecem como indisponíveis no calendário.</p>

<section class="seccao">
    <h2>Bloqueios em vigor e futuros</h2>
    <?php if (!$bloqueios): ?>
        <div class="alerta alerta-info">Não há bloqueios.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Quem</th><th>De</th><th>Até</th><th>Motivo</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($bloqueios as $b): ?>
                    <tr>
                        <td><?= e($b['nome_completo'] ?? 'Toda a clínica') ?></td>
                        <td><?= e((new DateTime($b['inicio']))->format('d/m/Y H:i')) ?></td>
                        <td><?= e((new DateTime($b['fim']))->format('d/m/Y H:i')) ?></td>
                        <td><?= e($b['motivo'] ?? '—') ?></td>
                        <td>
                            <form method="post" action="admin/bloqueios.php" class="form-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="apagar">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="botao botao-secundario">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="cartao">
    <h2>Novo bloqueio</h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="admin/bloqueios.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="criar">

        <div class="campo">
            <label for="dentista">Quem fica indisponível</label>
            <select id="dentista" name="dentista">
                <option value="0" <?= $form['dentista'] === '0' ? 'selected' : '' ?>>Toda a clínica</option>
                <?php foreach ($dentistas as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $form['dentista'] === (string)$d['id'] ? 'selected' : '' ?>>
                        <?= e($d['nome_completo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="inicio">Início</label>
            <input type="datetime-local" id="inicio" name="inicio" required value="<?= e($form['inicio']) ?>">
        </div>

        <div class="campo">
            <label for="fim">Fim</label>
            <input type="datetime-local" id="fim" name="fim" required value="<?= e($form['fim']) ?>">
        </div>

        <div class="campo">
            <label for="motivo">Motivo (opcional)</label>
            <input type="text" id="motivo" name="motivo" maxlength="150" value="<?= e($form['motivo']) ?>">
        </div>

        <button type="submit" class="botao">Criar bloqueio</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
