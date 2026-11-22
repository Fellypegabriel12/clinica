<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/gateway.php';
require __DIR__ . '/includes/pagamentos.php';
require __DIR__ . '/includes/checkout.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

$u   = exigir_login();
$pdo = db();

$ref  = trim((string)($_GET['ref'] ?? ''));
$piId = trim((string)($_GET['payment_intent'] ?? ''));
$erroPagina = '';
$marcacao = null;
$resumo = null;
$pagamento = null;
$cartao = ['marca' => '', 'ultimos4' => ''];
$estado = '';
$vista = 'pendente';

try {
    $marcacao = checkout_carregar_marcacao($pdo, $ref, (int)$u['id']);
    $resumo   = checkout_resumo($pdo, $marcacao);
    $pagamento = checkout_pagamento_confirmacao($pdo, (int)$marcacao['id'], $piId);

    if ($pagamento) {
        $estado = (string)$pagamento['estado'];
        $gateway = gateway_obter($pdo);
        if ($gateway instanceof GatewayStripe && $estado === 'pago') {
            try {
                $cartao = checkout_dados_cartao($pdo, $gateway, $pagamento);
            } catch (Throwable $e) {
                error_log('Checkout confirmação cartão: ' . $e->getMessage());
            }
        }
    }

    if ($estado === 'pago' || ($resumo['pago'] > 0.0001 && $resumo['valor'] <= 0.0001)) {
        $vista = 'pago';
    } elseif (in_array($estado, ['falhado', 'recusado', 'cancelado'], true)) {
        $vista = 'falhado';
    } elseif (in_array($estado, ['pendente', 'em_processamento', 'parcialmente_pago'], true)) {
        $vista = 'pendente';
    } elseif ($pagamento === null) {
        $vista = 'pendente';
    } else {
        $vista = 'pendente';
    }
} catch (RuntimeException $e) {
    http_response_code(404);
    $erroPagina = $e->getMessage();
} catch (Throwable $e) {
    error_log('Checkout confirmação: ' . $e->getMessage());
    http_response_code(500);
    $erroPagina = 'Ocorreu um erro ao consultar o pagamento. Tenta novamente.';
}

$modoTeste = str_starts_with(gateway_stripe_publishable_key($pdo), 'pk_test_');
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host) ?: 'localhost';

$tituloPagina = match ($vista) {
    'pago' => 'Pagamento confirmado',
    'falhado' => 'Pagamento não concluído',
    default => 'A confirmar pagamento',
};
if ($erroPagina !== '') { $tituloPagina = 'Confirmação indisponível'; }

$email = trim((string)($resumo['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = trim((string)($u['email'] ?? ''));
}
$valorMostrado = $pagamento ? (float)$pagamento['valor'] : (float)($resumo['valor'] ?? 0);
$metodoCartao = pagamento_cartao_legivel($cartao['marca'] ?? '', $cartao['ultimos4'] ?? '');
$marcaCurta = strtoupper((string)($cartao['marca'] ?? ''));
$marcaBadge = match ($marcaCurta) {
    'VISA' => 'VISA',
    'MASTERCARD' => 'MC',
    'AMEX', 'AMERICAN EXPRESS' => 'AMEX',
    'DISCOVER' => 'DISC',
    'JCB' => 'JCB',
    'UNIONPAY' => 'UP',
    default => 'CARD',
};
$pagamentoReferencia = $pagamento ? trim((string)($pagamento['referencia_transacao'] ?? '')) : '';
$paymentIntentId = $pagamentoReferencia !== '' && str_starts_with($pagamentoReferencia, 'pi_') ? $pagamentoReferencia : $piId;
$podeRepetir = $vista === 'falhado' && !empty($resumo['pode_pagar']);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($tituloPagina) ?> | Clínica Dentária</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>css/checkout.css?v=<?= (int)@filemtime(__DIR__ . '/css/checkout.css') ?>">
</head>
<body>
<main class="checkout-frame">
    <div class="checkout-top">
        <div class="window-dots" aria-hidden="true"><i></i><i></i><i></i></div>
        <div class="address"><span class="lock" aria-hidden="true">&#128274;</span> <?= e($host) ?></div>
        <span class="domain"><?= $modoTeste ? 'Modo de teste' : 'Pagamento seguro' ?></span>
    </div>

    <?php if ($erroPagina !== ''): ?>
        <div class="checkout-page-error" role="alert">
            <h1>Não foi possível mostrar a confirmação</h1>
            <p><?= e($erroPagina) ?></p>
            <p><a href="area-pessoal.php">Voltar à área pessoal</a></p>
        </div>
    <?php else: ?>
        <div class="checkout-layout">
            <section class="product-side" aria-label="Resumo do pagamento">
                <div class="restricted-row">
                    <span class="store-icon" aria-hidden="true">&#9670;</span>
                    <span class="restricted-badge">Área restrita</span>
                </div>
                <div class="product-info">
                    <span class="product-label">Clínica Dentária</span>
                    <h1><?= e((string)$resumo['produto']) ?></h1>
                    <div class="product-price"><?= e(formatar_preco($valorMostrado)) ?></div>
                </div>
                <div class="product-meta">
                    <div><strong>Referência:</strong> <?= e((string)$marcacao['referencia']) ?></div>
                    <?php if (!empty($resumo['pacientes'])): ?>
                        <div class="product-patients"><strong>Paciente<?= count($resumo['pacientes']) > 1 ? 's' : '' ?>:</strong> <?= e(implode(', ', $resumo['pacientes'])) ?></div>
                    <?php endif; ?>
                    <?php $primeira = $resumo['consultas'][0] ?? null; if ($primeira && !empty($primeira['inicio'])): $dt = new DateTime((string)$primeira['inicio']); ?>
                        <div class="product-ref"><strong>Consulta:</strong> <?= e($dt->format('d/m/Y H:i')) ?></div>
                    <?php endif; ?>
                    <?php if ($pagamentoReferencia !== ''): ?>
                        <div class="product-ref"><strong>Pagamento:</strong> <span class="mono"><?= e($pagamentoReferencia) ?></span></div>
                    <?php endif; ?>
                </div>
                <?php if ($modoTeste): ?>
                    <div class="test-badge"><span class="test-dot" aria-hidden="true"></span> Ambiente de teste — não uses um cartão real</div>
                <?php endif; ?>
            </section>

            <section class="confirmation-side" aria-label="Estado do pagamento">
                <?php if ($vista === 'pago'): ?>
                    <div class="success-check" aria-hidden="true">&#10003;</div>
                    <h2>Obrigado pelo seu pedido</h2>
                <?php elseif ($vista === 'falhado'): ?>
                    <div class="success-check failed" aria-hidden="true">&#10005;</div>
                    <h2>O pagamento não foi concluído</h2>
                <?php else: ?>
                    <div class="success-check pending" aria-hidden="true">&#8987;</div>
                    <h2>A confirmar o pagamento</h2>
                <?php endif; ?>

                <div class="confirmation-box">
                    <span class="confirmation-label">Dados de contacto</span>
                    <p><?= e($email !== '' ? $email : '—') ?></p>

                    <span class="confirmation-label method-label">Forma de pagamento</span>
                    <?php if ($vista === 'pago'): ?>
                        <div class="paid-method">
                            <span class="visa-small"><?= e($marcaBadge) ?></span>
                            <strong><?= e($metodoCartao) ?></strong>
                            <span class="paid-amount"><?= e(formatar_preco($valorMostrado)) ?></span>
                        </div>
                    <?php elseif ($vista === 'falhado'): ?>
                        <div class="paid-method">
                            <span class="visa-small"><?= e($marcaBadge) ?></span>
                            <strong>Não cobrado</strong>
                            <span class="paid-amount"><?= e(formatar_preco($valorMostrado)) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="paid-method">
                            <span class="visa-small"><?= e($marcaBadge) ?></span>
                            <strong id="estado-texto">A aguardar confirmação da Stripe</strong>
                            <span class="paid-amount"><?= e(formatar_preco($valorMostrado)) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($vista === 'pago'): ?>
                    <p class="confirmation-note">
                        O pagamento foi confirmado. A clínica recebeu a atualização automaticamente.
                        Podes consultar os detalhes na <a href="marcar-confirmacao.php?ref=<?= e(urlencode((string)$marcacao['referencia'])) ?>">tua marcação</a>.
                    </p>
                <?php elseif ($vista === 'falhado'): ?>
                    <p class="confirmation-note">
                        Não foi efetuada qualquer cobrança.<?= $podeRepetir ? ' Podes tentar novamente com outro cartão.' : '' ?>
                    </p>
                    <div class="confirmation-actions">
                        <?php if ($podeRepetir): ?>
                            <a class="btn-retry" href="checkout.php?ref=<?= e(urlencode((string)$marcacao['referencia'])) ?>">Tentar novamente</a>
                        <?php endif; ?>
                        <a class="btn-secondary" href="marcar-confirmacao.php?ref=<?= e(urlencode((string)$marcacao['referencia'])) ?>">Ver marcação</a>
                    </div>
                <?php else: ?>
                    <p class="confirmation-note" id="estado-nota">
                        Estamos a aguardar a confirmação segura da Stripe. Esta página atualiza automaticamente.
                        Não feches a janela enquanto o pagamento estiver em processamento.
                    </p>
                    <noscript><p class="confirmation-note">Atualiza esta página dentro de alguns instantes para ver o estado final.</p></noscript>
                <?php endif; ?>

                <div class="stripe-footer confirmation-footer">
                    <span>Powered by <strong>stripe</strong></span>
                    <span class="separator" aria-hidden="true"></span>
                    <a href="termos.php">Termos</a>
                    <a href="privacidade.php">Privacidade</a>
                </div>
            </section>
        </div>
    <?php endif; ?>
</main>
<?php if ($erroPagina === '' && $vista === 'pendente'): ?>
    <script
        src="<?= e(BASE_URL) ?>js/checkout-confirmacao.js?v=<?= (int)@filemtime(__DIR__ . '/js/checkout-confirmacao.js') ?>"
        data-estado-url="<?= e(BASE_URL) ?>checkout-estado.php"
        data-ref="<?= e((string)$marcacao['referencia']) ?>"
        data-payment-intent="<?= e($paymentIntentId) ?>"></script>
<?php endif; ?>
</body>
</html>