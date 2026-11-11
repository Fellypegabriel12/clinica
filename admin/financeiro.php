<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/marcacao.php';
require __DIR__ . '/../includes/gestao.php';
require __DIR__ . '/../includes/pagamentos.php';

$u = exigir_login(['admin', 'rececao']);
$pdo = db();

$de = (string)($_GET['de'] ?? date('Y-m-01'));
$ate = (string)($_GET['ate'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) { $de = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $ate = date('Y-m-d'); }
$deSql = $de . ' 00:00:00';
$ateSql = date('Y-m-d', strtotime($ate . ' +1 day')) . ' 00:00:00';
$dtExpr = "COALESCE(p.data_pagamento, p.pago_em, p.criado_em)";

$st = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN p.estado IN ('pago','reembolsado','parcialmente_reembolsado') THEN GREATEST(0,p.valor-p.valor_reembolsado) ELSE 0 END),0) recebido,
    COALESCE(SUM(CASE WHEN p.estado IN ('pendente','em_processamento','parcialmente_pago') THEN p.valor ELSE 0 END),0) pendente_reg,
    COALESCE(SUM(p.valor_reembolsado),0) reembolsado,
    COALESCE(SUM(CASE WHEN p.estado IN ('recusado','falhado') THEN p.valor ELSE 0 END),0) falhado
    FROM pagamentos p WHERE $dtExpr >= ? AND $dtExpr < ?");
$st->execute([$deSql, $ateSql]);
$tot = $st->fetch() ?: ['recebido'=>0,'pendente_reg'=>0,'reembolsado'=>0,'falhado'=>0];

// Dívida global (marcações ativas) - não depende do período para ser útil
$st = $pdo->query("SELECT COALESCE(SUM(GREATEST(0,m.total_previsto - COALESCE(sub.pago,0))),0) FROM marcacoes m LEFT JOIN (SELECT marcacao_id, SUM(GREATEST(0,valor-valor_reembolsado)) pago FROM pagamentos WHERE estado IN ('pago','reembolsado','parcialmente_reembolsado') GROUP BY marcacao_id) sub ON sub.marcacao_id=m.id WHERE m.estado IN ('confirmada','concluida')");
$dividaGlobal = round((float)$st->fetchColumn(), 2);

// Por método
$st = $pdo->prepare("SELECT p.metodo, p.metodo_detalhe, COUNT(*) n,
    COALESCE(SUM(CASE WHEN p.estado IN ('pago','reembolsado','parcialmente_reembolsado') THEN GREATEST(0,p.valor-p.valor_reembolsado) ELSE 0 END),0) recebido,
    COALESCE(SUM(p.valor_reembolsado),0) reembolsado
    FROM pagamentos p WHERE $dtExpr >= ? AND $dtExpr < ? GROUP BY p.metodo, p.metodo_detalhe ORDER BY recebido DESC");
$st->execute([$deSql, $ateSql]);
$porMetodo = $st->fetchAll();

// Tratamentos mais rentáveis (receita líquida em consultas_tratamentos/preço final, período por data da consulta)
$st = $pdo->prepare("SELECT t.nome, COUNT(*) n,
    COALESCE(SUM(ct.preco_final),0) receita,
    COALESCE(SUM(ct.desconto),0) descontos
    FROM consultas_tratamentos ct
    JOIN tratamentos t ON t.id = ct.tratamento_id
    JOIN consultas c ON c.id = ct.consulta_id
    WHERE c.inicio >= ? AND c.inicio < ? AND c.estado <> 'cancelada'
    GROUP BY t.id, t.nome ORDER BY receita DESC LIMIT 15");
$st->execute([$deSql, $ateSql]);
$tratRentaveis = $st->fetchAll();

// Histórico financeiro por paciente (top por dívida/atividade)
$pacienteQ = trim((string)($_GET['paciente'] ?? ''));
$histPaciente = [];
if ($pacienteQ !== '') {
    $st = $pdo->prepare("SELECT p.id, p.nome_completo, p.email, p.numero_utente_sns,
        COALESCE(SUM(m.total_previsto),0) total_prev,
        COALESCE((SELECT SUM(GREATEST(0,pg.valor-pg.valor_reembolsado)) FROM pagamentos pg JOIN marcacoes mm ON mm.id=pg.marcacao_id WHERE mm.utilizador_id IN (SELECT utilizador_id FROM autorizacoes_paciente ap2 WHERE ap2.paciente_id=p.id) AND pg.estado IN ('pago','reembolsado','parcialmente_reembolsado')),0) pago
        FROM pacientes p
        LEFT JOIN consultas c ON c.paciente_id=p.id
        LEFT JOIN marcacoes m ON m.id=c.marcacao_id
        WHERE p.nome_completo LIKE ? OR p.email LIKE ? OR p.numero_utente_sns LIKE ?
        GROUP BY p.id ORDER BY p.nome_completo LIMIT 30");
    $like = '%'.$pacienteQ.'%';
    $st->execute([$like,$like,$like]);
    $histPaciente = $st->fetchAll();
}

$tituloPagina = 'Relatório financeiro';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>
<p><a href="admin/index.php">← Painel</a> · <a href="admin/pagamentos.php">Pagamentos</a></p>
<h1>Relatório financeiro</h1>

<form method="get" action="admin/financeiro.php" class="filtros-gestao">
    <div class="campo"><label for="de">De</label><input type="date" id="de" name="de" value="<?= e($de) ?>"></div>
    <div class="campo"><label for="ate">Até</label><input type="date" id="ate" name="ate" value="<?= e($ate) ?>"></div>
    <div class="campo"><label for="paciente">Histórico paciente</label><input type="text" id="paciente" name="paciente" value="<?= e($pacienteQ) ?>" placeholder="Nome, email ou nº utente SNS"></div>
    <div class="campo"><button type="submit" class="botao">Atualizar</button></div>
</form>

<div class="grelha seccao">
    <div class="cartao texto-centro"><p class="numero"><?= e(formatar_preco($tot['recebido'])) ?></p><p class="ajuda">Total recebido (líquido)</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= e(formatar_preco($tot['pendente_reg'])) ?></p><p class="ajuda">Pendente em registos (período)</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= e(formatar_preco($dividaGlobal)) ?></p><p class="ajuda">Total em dívida (global)</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= e(formatar_preco($tot['reembolsado'])) ?></p><p class="ajuda">Reembolsos registados</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= e(formatar_preco($tot['falhado'])) ?></p><p class="ajuda">Falhados/recusados</p></div>
</div>

<section class="cartao seccao"><h2>Pagamentos por método</h2>
<?php if (!$porMetodo): ?><p class="ajuda">Sem dados no período.</p><?php else: ?>
<div class="tabela-scroll"><table class="tabela"><thead><tr><th>Método</th><th>Movimentos</th><th>Recebido líquido</th><th>Reembolsado</th></tr></thead><tbody>
<?php foreach ($porMetodo as $r): ?><tr><td><?= e(pagamento_metodo_legivel($r['metodo'], $r['metodo_detalhe'] ?? null)) ?></td><td><?= (int)$r['n'] ?></td><td><?= e(formatar_preco($r['recebido'])) ?></td><td><?= e(formatar_preco($r['reembolsado'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?></section>

<section class="cartao seccao"><h2>Tratamentos mais rentáveis</h2>
<p class="ajuda">Baseado em <code>consultas_tratamentos.preco_final</code> (após benefícios aplicados na marcação) para consultas não canceladas no período.</p>
<?php if (!$tratRentaveis): ?><p class="ajuda">Sem dados no período.</p><?php else: ?>
<div class="tabela-scroll"><table class="tabela"><thead><tr><th>Tratamento</th><th>Qtd.</th><th>Receita (final)</th><th>Descontos aplicados</th></tr></thead><tbody>
<?php foreach ($tratRentaveis as $r): ?><tr><td><?= e($r['nome']) ?></td><td><?= (int)$r['n'] ?></td><td><?= e(formatar_preco($r['receita'])) ?></td><td><?= e(formatar_preco($r['descontos'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?></section>

<section class="cartao seccao"><h2>Histórico financeiro de pacientes</h2>
<?php if ($pacienteQ === ''): ?><p class="ajuda">Pesquise por nome, email ou nº utente SNS para consultar histórico financeiro.</p>
<?php elseif (!$histPaciente): ?><p class="ajuda">Sem resultados.</p>
<?php else: ?>
<div class="tabela-scroll"><table class="tabela"><thead><tr><th>Paciente</th><th>Contacto</th><th>Nº utente SNS</th><th>Total previsto (consultas)</th><th>Pago (estimado)</th></tr></thead><tbody>
<?php foreach ($histPaciente as $p): ?><tr><td><?= e($p['nome_completo']) ?></td><td class="ajuda"><?= e($p['email'] ?? '—') ?></td><td><?= e($p['numero_utente_sns'] ?? '—') ?></td><td><?= e(formatar_preco($p['total_prev'])) ?></td><td><?= e(formatar_preco($p['pago'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<p class="ajuda">Valores agregados; para detalhe por movimento use <a href="admin/pagamentos.php?q=<?= e(urlencode($pacienteQ)) ?>">Pagamentos</a> com o termo de pesquisa.</p>
<?php endif; ?></section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
