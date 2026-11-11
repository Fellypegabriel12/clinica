<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';
require __DIR__ . '/includes/conta.php';

$u   = exigir_login();
$pdo = db();

// Pacientes autorizados
$st = $pdo->prepare(
    "SELECT p.id, p.nome_completo, a.relacao
       FROM autorizacoes_paciente a
       JOIN pacientes p ON p.id = a.paciente_id
      WHERE a.utilizador_id = ? AND p.ativo = 1
      ORDER BY (a.relacao = 'proprio') DESC, p.nome_completo"
);
$st->execute([$u['id']]);
$pacientes = $st->fetchAll();
$idsPacientes = array_map('intval', array_column($pacientes, 'id'));

// Planos disponíveis (agrupados por seguradora)
$seguradoras = $pdo->query(
    'SELECT s.id, s.nome, s.tipo,
            (SELECT GROUP_CONCAT(pl.id, \':\', pl.nome ORDER BY pl.nome SEPARATOR "|")
               FROM planos_beneficio pl
              WHERE pl.seguradora_id = s.id AND pl.ativo = 1) AS planos
       FROM seguradoras s WHERE s.ativo = 1 ORDER BY s.nome'
)->fetchAll();

// Lista achatada para o <select>
$planosLista = [];
foreach ($seguradoras as $s) {
    if (!$s['planos']) { continue; }
    foreach (explode('|', $s['planos']) as $par) {
        [$pid, $pnome] = explode(':', $par, 2);
        $planosLista[] = ['id' => (int)$pid, 'nome' => $pnome, 'seguradora' => $s['nome']];
    }
}

$erros = [];
$form  = [
    'paciente' => 0, 'plano' => 0, 'apolice' => '', 'beneficiario' => '',
    'valido_de' => '', 'valido_ate' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'registar') {
        $form['paciente']     = (int)($_POST['paciente'] ?? 0);
        $form['plano']        = (int)($_POST['plano'] ?? 0);
        $form['apolice']      = trim((string)($_POST['apolice'] ?? ''));
        $form['beneficiario'] = trim((string)($_POST['beneficiario'] ?? ''));
        $form['valido_de']    = (string)($_POST['valido_de'] ?? '');
        $form['valido_ate']   = (string)($_POST['valido_ate'] ?? '');

        if (!in_array($form['paciente'], $idsPacientes, true)) {
            $erros[] = 'Escolhe um paciente da tua lista.';
        }
        // Plano válido?
        $planoOk = false;
        foreach ($planosLista as $p) {
            if ($p['id'] === $form['plano']) { $planoOk = true; break; }
        }
        if (!$planoOk) {
            $erros[] = 'Escolhe um plano válido.';
        }
        if (mb_strlen($form['apolice']) > 60) {
            $erros[] = 'O número de apólice é demasiado longo.';
        }
        if (mb_strlen($form['beneficiario']) > 60) {
            $erros[] = 'O número de beneficiário é demasiado longo.';
        }
        $dataOk = function (string $d): ?string {
            if ($d === '') { return null; }
            $dt = DateTime::createFromFormat('Y-m-d', $d);
            return ($dt && $dt->format('Y-m-d') === $d) ? $d : false;
        };
        $vd = $dataOk($form['valido_de']);
        $va = $dataOk($form['valido_ate']);
        if ($vd === false) { $erros[] = 'Data de início inválida.'; }
        if ($va === false) { $erros[] = 'Data de fim inválida.'; }
        if ($vd && $va && $va < $vd) { $erros[] = 'A data de fim tem de ser posterior à de início.'; }

        if ($erros === []) {
            try {
                $pdo->prepare(
                    "INSERT INTO beneficios_paciente
                        (paciente_id, plano_id, numero_apolice, numero_beneficiario, valido_de, valido_ate, estado_validacao)
                     VALUES (?, ?, ?, ?, ?, ?, 'registado')"
                )->execute([
                    $form['paciente'], $form['plano'],
                    $form['apolice'] ?: null,
                    $form['beneficiario'] ?: null,
                    $vd, $va,
                ]);
                registar_admin('registar_beneficio', 'beneficios_paciente', (int)$pdo->lastInsertId());
                flash_definir('sucesso', 'Benefício registado. A clínica vai validar e o desconto será aplicado nas próximas marcações.');
                redirecionar('seguros.php');
            } catch (PDOException $e) {
                error_log('Erro registar benefício: ' . $e->getMessage());
                $erros[] = 'Ocorreu um erro. Tenta novamente.';
            }
        }
    }

    if ($acao === 'apagar') {
        $id = (int)($_POST['id'] ?? 0);
        // Só pode apagar se ainda não estiver validado (uma vez validado, a clínica controla)
        $st = $pdo->prepare(
            "SELECT bp.id FROM beneficios_paciente bp
               JOIN autorizacoes_paciente a ON a.paciente_id = bp.paciente_id AND a.utilizador_id = ?
              WHERE bp.id = ? AND bp.estado_validacao IN ('registado','rejeitado')"
        );
        $st->execute([$u['id'], $id]);
        if ($st->fetchColumn()) {
            $pdo->prepare('DELETE FROM beneficios_paciente WHERE id = ?')->execute([$id]);
            registar_admin('apagar_beneficio', 'beneficios_paciente', $id);
            flash_definir('sucesso', 'Benefício removido.');
        } else {
            flash_definir('erro', 'Não podes apagar este benefício (já foi validado ou não te pertence).');
        }
        redirecionar('seguros.php');
    }
}

// Benefícios registados por este utilizador
$st = $pdo->prepare(
    "SELECT bp.id, bp.numero_apolice, bp.numero_beneficiario, bp.valido_de, bp.valido_ate,
            bp.estado_validacao, bp.validado_em, bp.notas, bp.criado_em,
            p.nome_completo AS paciente,
            pl.nome AS plano, s.nome AS seguradora, s.tipo AS tipo_seguradora
       FROM beneficios_paciente bp
       JOIN pacientes p ON p.id = bp.paciente_id
       JOIN autorizacoes_paciente a ON a.paciente_id = bp.paciente_id AND a.utilizador_id = ?
       JOIN planos_beneficio pl ON pl.id = bp.plano_id
       JOIN seguradoras s ON s.id = pl.seguradora_id
      ORDER BY bp.criado_em DESC"
);
$st->execute([$u['id']]);
$meusBeneficios = $st->fetchAll();

$rotulosEstado = [
    'registado'    => 'À espera de validação',
    'em_validacao' => 'Em validação',
    'validado'     => 'Validado',
    'rejeitado'    => 'Rejeitado',
    'expirado'     => 'Expirado',
];

$tituloPagina = 'Seguros e benefícios';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_conta();
?>

<h1>Seguros e benefícios</h1>
<?= submenu_conta('perfil', nao_lidas($pdo, $u['id'])) ?>

<p class="ajuda">
    Regista os teus seguros de saúde, planos dentários ou descontos da clínica.
    Os descontos só são aplicados depois de a clínica validar.
</p>

<section class="seccao">
    <h2>Os meus benefícios</h2>
    <?php if (!$meusBeneficios): ?>
        <div class="alerta alerta-info">Ainda não tens benefícios registados.</div>
    <?php else: ?>
        <div class="grelha grelha-larga">
        <?php foreach ($meusBeneficios as $b): ?>
            <article class="cartao linha-consulta">
                <strong><?= e($b['paciente']) ?></strong>
                <span><strong><?= e($b['seguradora']) ?></strong> — <?= e($b['plano']) ?></span>
                <span class="ajuda">
                    Apólice: <?= e($b['numero_apolice'] ?? '—') ?>
                    · Beneficiário: <?= e($b['numero_beneficiario'] ?? '—') ?>
                </span>
                <span class="ajuda">
                    Validade:
                    <?= $b['valido_de'] ? e((new DateTime($b['valido_de']))->format('d/m/Y')) : '—' ?>
                    a
                    <?= $b['valido_ate'] ? e((new DateTime($b['valido_ate']))->format('d/m/Y')) : '—' ?>
                </span>
                <span>
                    Estado:
                    <span class="estado-etiqueta estado-<?= e($b['estado_validacao']) ?>">
                        <?= e($rotulosEstado[$b['estado_validacao']] ?? $b['estado_validacao']) ?>
                    </span>
                </span>
                <?php if ($b['validado_em']): ?>
                    <span class="ajuda">Tratado em <?= e((new DateTime($b['validado_em']))->format('d/m/Y H:i')) ?></span>
                <?php endif; ?>
                <?php if ($b['notas']): ?>
                    <span class="ajuda">Notas da clínica: <?= e($b['notas']) ?></span>
                <?php endif; ?>

                <?php if (in_array($b['estado_validacao'], ['registado', 'rejeitado'], true)): ?>
                    <form method="post" action="seguros.php" class="form-inline" onsubmit="return confirm('Apagar este benefício?');">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="apagar">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <button type="submit" class="botao botao-secundario">Apagar</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="cartao seccao">
    <h2>Registar novo benefício</h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <?php if (!$pacientes): ?>
        <div class="alerta alerta-info">Não tens pacientes associados.</div>
    <?php elseif (!$planosLista): ?>
        <div class="alerta alerta-info">A clínica ainda não configurou planos de benefício.</div>
    <?php else: ?>
        <form method="post" action="seguros.php" novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="registar">

            <div class="campo">
                <label for="paciente">Para quem <span class="obrigatorio" aria-hidden="true">*</span></label>
                <select id="paciente" name="paciente" required>
                    <option value="0">— escolher —</option>
                    <?php foreach ($pacientes as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $form['paciente'] === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['nome_completo']) ?>
                            <?= $p['relacao'] === 'proprio' ? '(tu)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="plano">Plano <span class="obrigatorio" aria-hidden="true">*</span></label>
                <select id="plano" name="plano" required>
                    <option value="0">— escolher —</option>
                    <?php foreach ($planosLista as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $form['plano'] === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['seguradora']) ?> — <?= e($p['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="apolice">Número de apólice</label>
                <input type="text" id="apolice" name="apolice" maxlength="60" value="<?= e($form['apolice']) ?>">
            </div>

            <div class="campo">
                <label for="beneficiario">Número de beneficiário</label>
                <input type="text" id="beneficiario" name="beneficiario" maxlength="60" value="<?= e($form['beneficiario']) ?>">
            </div>

            <div class="campo">
                <label for="valido_de">Válido de</label>
                <input type="date" id="valido_de" name="valido_de" value="<?= e($form['valido_de']) ?>">
            </div>

            <div class="campo">
                <label for="valido_ate">Válido até</label>
                <input type="date" id="valido_ate" name="valido_ate" value="<?= e($form['valido_ate']) ?>">
            </div>

            <button type="submit" class="botao">Registar benefício</button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>