<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';
require __DIR__ . '/includes/conta.php';
require __DIR__ . '/includes/gestao.php';
require __DIR__ . '/includes/pagamentos.php';
require __DIR__ . '/includes/gateway.php';

$u = exigir_login();
$pdo = db();
limpar_reservas_expiradas($pdo);

// Resumo financeiro por marcação do próprio utilizador
$st = $pdo->prepare(
    "SELECT m.id, m.referencia, m.total_previsto, m.estado, m.tem_preco_estimado, m.criado_em,
            COALESCE(SUM(CASE WHEN p.estado IN ('pago','reembolsado','parcialmente_reembolsado') THEN GREATEST(0,p.valor-p.valor_reembolsado) ELSE 0 END),0) AS pago_liq,
            COALESCE(SUM(p.valor_reembolsado),0) AS reembolsado
       FROM marcacoes m
       LEFT JOIN pagamentos p ON p.marcacao_id = m.id
      WHERE m.utilizador_id = ?
      GROUP BY m.id
      ORDER BY m.criado_em DESC LIMIT 200"
);
$st->execute([$u['id']]);
$marcacoes = $st->fetchAll();

// Histórico de movimentos de pagamento do próprio utilizador
$st = $pdo->prepare(
    "SELECT p.id, p.marcacao_id, p.consulta_id, p.metodo, p.metodo_detalhe, p.valor, p.estado, p.valor_reembolsado,
            p.cartao_marca, p.cartao_ultimos4,
            p.data_pagamento, p.pago_em, p.reembolsado_em, p.criado_em, p.referencia_transacao, p.observacoes,
            m.referencia AS marcacao_ref
       FROM pagamentos p
       JOIN marcacoes m ON m.id = p.marcacao_id
      WHERE m.utilizador_id = ?
      ORDER BY COALESCE(p.data_pagamento, p.pago_em, p.criado_em) DESC, p.id DESC LIMIT 300"
);
$st->execute([$u['id']]);
$pagamentos = $st->fetchAll();

$onlineDisponivel = (int)config_valor($pdo, 'pagamento_online_ativo', 0) === 1
    && gateway_pagamento_disponivel($pdo);

$totalDivida = 0.0; $totalPago = 0.0;
foreach ($marcacoes as $m) {
    $div = max(0.0, round((float)$m['total_previsto'] - (float)$m['pago_liq'], 2));
    $totalDivida += $div;
    $totalPago += (float)$m['pago_liq'];
}

$tituloPagina = 'Pagamentos e dívidas';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_conta();
?>
<h1>Pagamentos e dívidas</h1>
<?= submenu_conta('pagamentos', nao_lidas($pdo, $u['id'])) ?>

<div class="grelha seccao">
    <div class="cartao texto-centro"><p class="numero"><?= e(formatar_preco($totalPago)) ?></p><p class="ajuda">Total pago (líquido)</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= e(formatar_preco($totalDivida)) ?></p><p class="ajuda">Total em dívida</p></div>
</div>

<section class="seccao">
    <h2>Por marcação</h2>
    <?php if (!$marcacoes): ?>
        <div class="alerta alerta-info">Ainda não tem marcações.</div>
    <?php else: ?>
        <div class="grelha grelha-larga">
        <?php foreach ($marcacoes as $m):
            $total = round((float)$m['total_previsto'], 2);
            $pago = round((float)$m['pago_liq'], 2);
            $div = max(0.0, round($total - $pago, 2));
            $estado = $div <= 0.0001 && $total > 0 ? 'Pago' : ($pago > 0 ? 'Parcialmente pago' : 'Pendente');
        ?>
            <article class="cartao">
                <strong><?= e($m['referencia']) ?></strong>
                <div class="ajuda"><?= e((new DateTime($m['criado_em']))->format('d/m/Y H:i')) ?> · <?= e(rotulo('marcacao', $m['estado'])) ?></div>
                <div>Total: <strong><?= e(formatar_preco($total)) ?></strong></div>
                <div>Pago: <?= e(formatar_preco($pago)) ?><?php if ((float)$m['reembolsado'] > 0): ?> <span class="ajuda">(reembolsado <?= e(formatar_preco($m['reembolsado'])) ?>)</span><?php endif; ?></div>
                <div>Em dívida: <strong><?= e(formatar_preco($div)) ?></strong></div>
                <div>Estado financeiro: <span class="estado-etiqueta"><?= e($estado) ?></span></div>
                <?php if ($onlineDisponivel && $m['estado'] !== 'cancelada' && (int)$m['tem_preco_estimado'] !== 1 && $div > 0.0001): ?>
                    <p><a class="botao" href="checkout.php?ref=<?= e(urlencode($m['referencia'])) ?>">Pagar online</a></p>
                <?php endif; ?>
                <p><a class="botao botao-secundario" href="marcar-confirmacao.php?ref=<?= e(urlencode($m['referencia'])) ?>">Ver marcação</a></p>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="seccao">
    <h2>Histórico de pagamentos</h2>
    <?php if (!$pagamentos): ?>
        <div class="alerta alerta-info">Sem registos de pagamento.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Data</th><th>Marcação</th><th>Método</th><th>Valor</th><th>Estado</th><th>Referência</th></tr></thead>
                <tbody>
                <?php foreach ($pagamentos as $p): ?>
                    <tr>
                        <td><?= e((new DateTime($p['data_pagamento'] ?: $p['pago_em'] ?: $p['criado_em']))->format('d/m/Y H:i')) ?></td>
                        <td><a href="marcar-confirmacao.php?ref=<?= e(urlencode($p['marcacao_ref'])) ?>"><?= e($p['marcacao_ref']) ?></a></td>
                        <td><?= e(pagamento_metodo_legivel($p['metodo'], $p['metodo_detalhe'] ?? null)) ?><?php if (($p['cartao_marca'] ?? '') !== '' && ($p['cartao_ultimos4'] ?? '') !== ''): ?><br><span class="ajuda"><?= e(pagamento_cartao_legivel((string)$p['cartao_marca'], (string)$p['cartao_ultimos4'])) ?></span><?php endif; ?></td>
                        <td><?= e(formatar_preco($p['valor'])) ?><?php if ((float)$p['valor_reembolsado']>0): ?><br><span class="ajuda">Reemb. <?= e(formatar_preco($p['valor_reembolsado'])) ?></span><?php endif; ?></td>
                        <td><span class="estado-etiqueta"><?= e(pagamento_estado_legivel($p['estado'])) ?></span></td>
                        <td><span class="ajuda"><?= e($p['referencia_transacao'] ?? '—') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
