<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';
require __DIR__ . '/includes/conta.php';

$u = exigir_login();
$pdo = db();

// Pacientes autorizados
$st = $pdo->prepare("SELECT p.id, p.nome_completo FROM autorizacoes_paciente a JOIN pacientes p ON p.id=a.paciente_id WHERE a.utilizador_id=? AND p.ativo=1 ORDER BY p.nome_completo");
$st->execute([$u['id']]);
$pacientes = $st->fetchAll();
$idsPac = array_map('intval', array_column($pacientes, 'id'));

$dentistas = $pdo->query('SELECT id, nome_completo FROM dentistas WHERE ativo=1 ORDER BY nome_completo')->fetchAll();
$tratamentos = $pdo->query('SELECT id, nome FROM tratamentos WHERE ativo=1 ORDER BY nome')->fetchAll();
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'criar') {
        $pacienteId = (int)($_POST['paciente_id'] ?? 0);
        $dentistaId = (int)($_POST['dentista_id'] ?? 0) ?: null;
        $tratamentoId = (int)($_POST['tratamento_id'] ?? 0) ?: null;
        $desde = (string)($_POST['data_desde'] ?? '');
        $ate = (string)($_POST['data_ate'] ?? '');
        $periodo = (string)($_POST['periodo'] ?? 'qualquer');
        if (!in_array($pacienteId, $idsPac, true)) { $erros[] = 'Paciente inválido.'; }
        $dOk = DateTime::createFromFormat('Y-m-d', $desde); $aOk = DateTime::createFromFormat('Y-m-d', $ate);
        if (!$dOk || $dOk->format('Y-m-d') !== $desde || !$aOk || $aOk->format('Y-m-d') !== $ate) { $erros[] = 'Datas inválidas.'; }
        elseif ($ate < $desde) { $erros[] = 'A data final deve ser igual ou posterior à inicial.'; }
        if (!in_array($periodo, ['manha','tarde','qualquer'], true)) { $periodo = 'qualquer'; }
        if ($erros === []) {
            $pdo->prepare("INSERT INTO lista_espera (utilizador_id,paciente_id,dentista_id,tratamento_id,data_desde,data_ate,periodo,estado) VALUES (?,?,?,?,?,?,?, 'ativa')")
                ->execute([$u['id'], $pacienteId, $dentistaId, $tratamentoId, $desde, $ate, $periodo]);
            flash_definir('sucesso', 'Pedido de lista de espera registado. A clínica contactará se houver disponibilidade.');
            redirecionar('lista-espera.php');
        }
    } elseif ($acao === 'cancelar') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE lista_espera SET estado='cancelada' WHERE id=? AND utilizador_id=? AND estado IN ('ativa','notificada')")->execute([$id, $u['id']]);
        flash_definir('sucesso', 'Pedido cancelado.');
        redirecionar('lista-espera.php');
    }
}

$st = $pdo->prepare("SELECT le.*, p.nome_completo AS paciente, d.nome_completo AS dentista, t.nome AS tratamento
  FROM lista_espera le JOIN pacientes p ON p.id=le.paciente_id
  LEFT JOIN dentistas d ON d.id=le.dentista_id LEFT JOIN tratamentos t ON t.id=le.tratamento_id
  WHERE le.utilizador_id=? ORDER BY le.criado_em DESC");
$st->execute([$u['id']]);
$meus = $st->fetchAll();

$tituloPagina = 'Lista de espera';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_conta();
?>
<h1>Lista de espera</h1>
<?= submenu_conta('espera', nao_lidas($pdo, $u['id'])) ?>
<p class="ajuda">Se não encontrar horário disponível, pode deixar um pedido. A clínica fará o contacto quando houver vaga (contacto manual; sem envio automático de email/SMS nesta versão).</p>

<?php foreach ($erros as $e): ?><div class="alerta alerta-erro"><?= e($e) ?></div><?php endforeach; ?>

<section class="cartao seccao"><h2>Novo pedido</h2>
<form method="post" action="lista-espera.php"><?= csrf_campo() ?><input type="hidden" name="acao" value="criar">
    <div class="campo"><label for="paciente_id">Paciente</label><select id="paciente_id" name="paciente_id" required><?php foreach ($pacientes as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nome_completo']) ?></option><?php endforeach; ?></select></div>
    <div class="campo"><label for="dentista_id">Dentista (opcional)</label><select id="dentista_id" name="dentista_id"><option value="0">Qualquer</option><?php foreach ($dentistas as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['nome_completo']) ?></option><?php endforeach; ?></select></div>
    <div class="campo"><label for="tratamento_id">Tratamento (opcional)</label><select id="tratamento_id" name="tratamento_id"><option value="0">Qualquer</option><?php foreach ($tratamentos as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="campo"><label for="data_desde">De</label><input type="date" id="data_desde" name="data_desde" required></div>
    <div class="campo"><label for="data_ate">Até</label><input type="date" id="data_ate" name="data_ate" required></div>
    <div class="campo"><label for="periodo">Período</label><select id="periodo" name="periodo"><option value="qualquer">Qualquer</option><option value="manha">Manhã</option><option value="tarde">Tarde</option></select></div>
    <button class="botao" type="submit">Registar pedido</button>
</form></section>

<section class="seccao"><h2>Os meus pedidos</h2>
<?php if (!$meus): ?><div class="alerta alerta-info">Sem pedidos.</div><?php else: ?><div class="grelha grelha-larga">
<?php foreach ($meus as $m): ?><article class="cartao">
    <strong><?= e($m['paciente']) ?></strong>
    <div class="ajuda"><?= e($m['data_desde']) ?> a <?= e($m['data_ate']) ?> · <?= e($m['periodo']) ?></div>
    <?php if ($m['dentista']): ?><div class="ajuda">Dentista: <?= e($m['dentista']) ?></div><?php endif; ?>
    <div>Estado: <?= e($m['estado']) ?></div>
    <?php if (in_array($m['estado'], ['ativa','notificada'], true)): ?>
    <form method="post" action="lista-espera.php"><?= csrf_campo() ?><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="botao botao-secundario" type="submit">Cancelar</button></form>
    <?php endif; ?>
</article><?php endforeach; ?></div><?php endif; ?></section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
