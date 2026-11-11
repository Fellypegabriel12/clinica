<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';

/* =====================================================================
   PRESTADOR DE PAGAMENTOS SIMULADO (modo demonstração)
   Aqui não há cartões nem dinheiro a sério. Num site real, esta página seria
   substituída pelo prestador (ex.: Stripe, SIBS), que devolve a confirmação
   ao servidor. A marcação só passa a "paga" quando essa confirmação chega.
   ===================================================================== */

$u   = exigir_login();
$pdo = db();

// Simulação apenas para desenvolvimento local explícito. Em produção, usar prestador real.
if (!MODO_DEV) {
    flash_definir('erro', 'O pagamento eletrónico simulado não está disponível. Por favor, escolha pagamento na clínica ou contacte a receção.');
    redirecionar('area-pessoal.php');
}


limpar_reservas_expiradas($pdo);   // cancela marcações cujo prazo de pagamento acabou

$ref = (string)($_GET['ref'] ?? $_POST['ref'] ?? '');
$st = $pdo->prepare('SELECT * FROM marcacoes WHERE referencia = ? AND utilizador_id = ?');
$st->execute([$ref, $u['id']]);
$marc = $st->fetch();
if (!$marc) {
    flash_definir('erro', 'Marcação não encontrada.');
    redirecionar('area-pessoal.php');
}

// Último pagamento desta marcação
$st = $pdo->prepare('SELECT * FROM pagamentos WHERE marcacao_id = ? ORDER BY id DESC LIMIT 1');
$st->execute([$marc['id']]);
$pag = $st->fetch();

if ($marc['estado'] === 'cancelada') {
    flash_definir('erro', 'Esta marcação foi cancelada porque o pagamento não foi concluído a tempo. Faz uma nova marcação.');
    redirecionar('dentistas.php?modo=marcar');
}
if ($marc['estado'] !== 'pendente' || !$pag || !in_array($pag['estado'], ['pendente', 'recusado'], true)) {
    redirecionar('marcar-confirmacao.php?ref=' . urlencode($ref));
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    try {
        $pdo->beginTransaction();

        if ($acao === 'aprovar') {
            // "Confirmação do prestador": só agora o pagamento passa a pago e a marcação a confirmada
            $pdo->prepare(
                "UPDATE pagamentos SET estado = 'pago', prestador = 'demo', referencia_transacao = ?, pago_em = NOW() WHERE id = ?"
            )->execute(['DEMO-' . strtoupper(bin2hex(random_bytes(5))), $pag['id']]);
            $pdo->prepare("UPDATE marcacoes SET estado = 'confirmada' WHERE id = ? AND estado <> 'cancelada'")->execute([$marc['id']]);
            // CORRIGIDO: não reconfirmar consultas já canceladas
            $pdo->prepare("UPDATE consultas SET estado = 'confirmada' WHERE marcacao_id = ? AND estado <> 'cancelada'")->execute([$marc['id']]);
            $pdo->prepare(
                'UPDATE horarios_ocupados SET expira_em = NULL
                  WHERE consulta_id IN (SELECT id FROM consultas WHERE marcacao_id = ?)'
            )->execute([$marc['id']]);
            criar_notificacao($pdo, $u['id'], (int)$marc['id'], 'pagamento_confirmado', 'Pagamento confirmado',
                'Recebemos o pagamento da marcação ' . $ref . '. A marcação está confirmada.');
            $pdo->commit();
            flash_definir('sucesso', 'Pagamento aprovado. Marcação confirmada!');
            redirecionar('marcar-confirmacao.php?ref=' . urlencode($ref));
        }

        if ($acao === 'recusar') {
            $pdo->prepare("UPDATE pagamentos SET estado = 'recusado', prestador = 'demo' WHERE id = ?")->execute([$pag['id']]);
            $pdo->commit();
            $erro = 'O pagamento foi recusado. Podes tentar outra vez ou pagar na clínica. A marcação não foi duplicada.';
            $pag['estado'] = 'recusado';
        }

        if ($acao === 'presencial') {
            // Desiste do pagamento eletrónico: fica confirmada, com o pagamento por fazer na clínica
            $total = $marc['total_previsto'];
            $pdo->prepare("UPDATE pagamentos SET estado = 'recusado' WHERE id = ?")->execute([$pag['id']]);
            $pdo->prepare("INSERT INTO pagamentos (marcacao_id, metodo, valor, estado) VALUES (?, 'presencial', ?, 'pendente')")
                ->execute([$marc['id'], $total]);
            $pdo->prepare("UPDATE marcacoes SET estado = 'confirmada' WHERE id = ? AND estado <> 'cancelada'")->execute([$marc['id']]);
            $pdo->prepare("UPDATE consultas SET estado = 'confirmada' WHERE marcacao_id = ? AND estado <> 'cancelada'")->execute([$marc['id']]);
            $pdo->prepare(
                'UPDATE horarios_ocupados SET expira_em = NULL
                  WHERE consulta_id IN (SELECT id FROM consultas WHERE marcacao_id = ?)'
            )->execute([$marc['id']]);
            criar_notificacao($pdo, $u['id'], (int)$marc['id'], 'confirmacao_marcacao', 'Marcação confirmada',
                'A tua marcação ' . $ref . ' está confirmada. O pagamento será feito na clínica.');
            $pdo->commit();
            flash_definir('sucesso', 'Marcação confirmada. Pagas na clínica.');
            redirecionar('marcar-confirmacao.php?ref=' . urlencode($ref));
        }

        if ($pdo->inTransaction()) { $pdo->commit(); }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Erro no pagamento demo: ' . $e->getMessage());
        $erro = 'Ocorreu um erro. Tenta novamente.';
    }
}

// Tempo que falta até a marcação ser cancelada
$st = $pdo->prepare(
    'SELECT MIN(h.expira_em) FROM horarios_ocupados h
       JOIN consultas c ON c.id = h.consulta_id WHERE c.marcacao_id = ?'
);
$st->execute([$marc['id']]);
$limite = $st->fetchColumn();

$tituloPagina = 'Pagamento (demonstração)';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_marcacao();
?>

<?= passos_marcacao(5) ?>

<h1>Pagamento eletrónico</h1>

<div class="alerta alerta-info" role="note">
    <strong>Modo demonstração:</strong> não há cartões nem dinheiro a sério. Escolhe o resultado que queres simular.
</div>

<?php if ($erro !== ''): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endif; ?>

<section class="cartao">
    <dl class="dados">
        <dt>Referência</dt><dd><?= e($marc['referencia']) ?></dd>
        <dt>Método</dt><dd><?= e(rotulo('metodo', $pag['metodo'])) ?></dd>
        <dt>Valor a pagar agora</dt><dd><strong><?= e(formatar_preco($pag['valor'])) ?></strong></dd>
        <dt>Estado do pagamento</dt><dd><?= e(rotulo('pagamento', $pag['estado'])) ?></dd>
        <?php if ($limite): ?>
            <dt>Prazo</dt><dd>até às <?= e((new DateTime((string)$limite))->format('H:i')) ?>, depois a marcação é cancelada</dd>
        <?php endif; ?>
    </dl>

    <form method="post" action="marcar-pagamento-demo.php">
        <?= csrf_campo() ?>
        <input type="hidden" name="ref" value="<?= e($ref) ?>">
        <p>
            <button type="submit" name="acao" value="aprovar" class="botao">Simular pagamento aprovado</button>
            <button type="submit" name="acao" value="recusar" class="botao botao-secundario">Simular pagamento recusado</button>
        </p>
        <p>
            <button type="submit" name="acao" value="presencial" class="botao botao-secundario">Prefiro pagar na clínica</button>
        </p>
    </form>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>