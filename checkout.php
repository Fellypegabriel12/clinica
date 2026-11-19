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

$ref = trim((string)($_GET['ref'] ?? ''));
$erroPagina = '';
$marcacao = null;
$resumo = null;
$prep = null;

try {
    $marcacao = checkout_carregar_marcacao($pdo, $ref, (int)$u['id']);
    $resumo = checkout_resumo($pdo, $marcacao);

    if ($resumo['cancelada']) {
        $erroPagina = 'Esta marcação foi cancelada. Se precisares de ajuda, contacta a clínica.';
    } elseif (($resumo['valor'] ?? 0) <= 0.0001 && ($resumo['pago'] ?? 0) > 0.0001) {
        redirecionar('checkout-confirmacao.php?ref=' . urlencode((string)$marcacao['referencia']));
    } elseif (!$resumo['pode_pagar']) {
        if ($resumo['estimado']) {
            $erroPagina = 'Esta marcação inclui tratamentos com preço estimado. O pagamento online fica disponível depois da avaliação clínica, quando o valor final estiver confirmado.';
        } elseif (($resumo['valor'] ?? 0) <= 0.0001) {
            $erroPagina = 'Não existe valor em falta para pagar nesta marcação.';
        } else {
            $erroPagina = 'O pagamento online não está disponível para esta marcação.';
        }
    } elseif (!gateway_pagamento_disponivel($pdo) || (int)config_valor($pdo, 'pagamento_online_ativo', 0) !== 1) {
        $erroPagina = 'O pagamento online não está disponível neste momento. Tenta mais tarde ou paga na clínica.';
    } else {
        $gateway = gateway_obter($pdo);
        if (!$gateway instanceof GatewayStripe) {
            $erroPagina = 'O gateway de pagamento configurado não suporta este checkout. Contacta a clínica.';
        } else {
            $email = trim((string)$resumo['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = trim((string)($u['email'] ?? ''));
            }
            $prep = checkout_preparar_intencao($pdo, $gateway, $marcacao, $resumo, $email);
            if (empty($prep['ok'])) {
                $erroPagina = (string)($prep['erro'] ?? 'Não foi possível iniciar o pagamento.');
            } elseif (($prep['estado'] ?? '') === 'pago') {
                redirecionar('checkout-confirmacao.php?ref=' . urlencode((string)$marcacao['referencia'])
                    . '&payment_intent=' . urlencode((string)($prep['payment_intent_id'] ?? '')));
            } elseif (($prep['estado'] ?? '') === 'processing') {
                redirecionar('checkout-confirmacao.php?ref=' . urlencode((string)$marcacao['referencia'])
                    . '&payment_intent=' . urlencode((string)($prep['payment_intent_id'] ?? '')));
            } elseif (empty($prep['client_secret'])) {
                $erroPagina = 'O Stripe não devolveu os dados necessários para concluir o pagamento. Tenta novamente.';
            }
        }
    }
} catch (RuntimeException $e) {
    http_response_code(404);
    $erroPagina = $e->getMessage();
} catch (Throwable $e) {
    error_log('Checkout: ' . $e->getMessage());
    http_response_code(500);
    $erroPagina = 'Ocorreu um erro ao preparar o pagamento. Tenta novamente.';
}

$publishable = gateway_stripe_publishable_key($pdo);
$modoTeste = str_starts_with($publishable, 'pk_test_');
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host) ?: 'localhost';
$returnUrl = $marcacao ? checkout_url_confirmacao($pdo, (string)$marcacao['referencia']) : '';
$tituloPagina = $erroPagina !== '' ? 'Checkout indisponível' : 'Checkout seguro';
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
            <h1>Não foi possível abrir o checkout</h1>
            <p><?= e($erroPagina) ?></p>
            <p>
                <?php if ($marcacao): ?>
                    <a href="marcar-confirmacao.php?ref=<?= e(urlencode((string)$marcacao['referencia'])) ?>">Ver a marcação</a>
                    &nbsp;·&nbsp;
                <?php endif; ?>
                <a href="area-pessoal.php">Voltar à área pessoal</a>
            </p>
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
                    <div class="product-price"><?= e(formatar_preco($resumo['valor'])) ?></div>
                </div>
                <div class="product-meta">
                    <div><strong>Referência:</strong> <?= e((string)$marcacao['referencia']) ?></div>
                    <?php if (!empty($resumo['pacientes'])): ?>
                        <div class="product-patients"><strong>Paciente<?= count($resumo['pacientes']) > 1 ? 's' : '' ?>:</strong> <?= e(implode(', ', $resumo['pacientes'])) ?></div>
                    <?php endif; ?>
                    <?php
                    $primeira = $resumo['consultas'][0] ?? null;
                    if ($primeira && !empty($primeira['inicio'])):
                        $dt = new DateTime((string)$primeira['inicio']);
                    ?>
                        <div class="product-ref"><strong>Consulta:</strong> <?= e($dt->format('d/m/Y H:i')) ?></div>
                    <?php endif; ?>
                    <?php if ($resumo['pago'] > 0.0001): ?>
                        <div class="product-ref"><strong>Já pago:</strong> <?= e(formatar_preco($resumo['pago'])) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($modoTeste): ?>
                    <div class="test-badge"><span class="test-dot" aria-hidden="true"></span> Ambiente de teste — não uses um cartão real</div>
                <?php endif; ?>
            </section>

            <section class="payment-side" aria-label="Dados de pagamento">
                <h2>Pagar com cartão</h2>

                <form id="payment-form"
                      data-publishable-key="<?= e($publishable) ?>"
                      data-client-secret="<?= e((string)$prep['client_secret']) ?>"
                      data-return-url="<?= e($returnUrl) ?>"
                      novalidate>
                    <div class="field-heading">Dados de contacto</div>
                    <div class="contact-fields">
                        <div class="input-wrap">
                            <span class="field-icon" aria-hidden="true">&#9993;</span>
                            <input name="email" type="email" placeholder="e-mail@exemplo.com" autocomplete="email" maxlength="190"
                                   value="<?= e((string)$resumo['email']) ?>" required aria-label="E-mail">
                        </div>
                        <div class="input-wrap">
                            <span class="field-icon" aria-hidden="true">&#9742;</span>
                            <input name="telefone" type="tel" placeholder="912 345 678" autocomplete="tel" maxlength="30"
                                   value="<?= e((string)$resumo['telefone']) ?>" aria-label="Número de telefone">
                            <span class="optional">Opcional</span>
                        </div>
                        <div class="input-wrap">
                            <span class="field-icon" aria-hidden="true">&#9786;</span>
                            <input name="nome" type="text" placeholder="Nome completo" autocomplete="name" maxlength="120"
                                   value="<?= e((string)$resumo['paciente']) ?>" aria-label="Nome completo">
                            <span class="optional">Opcional</span>
                        </div>
                    </div>

                    <div class="payment-heading">Forma de pagamento</div>
                    <div class="card-panel">
                        <div class="card-title"><span class="card-symbol" aria-hidden="true">&#128179;</span><strong>Cartão</strong></div>
                        <div class="field-heading card-data-heading">Dados do cartão</div>
                        <div id="payment-element" class="is-loading" aria-live="polite"></div>

                        <label for="holder">Nome do titular do cartão</label>
                        <input class="holder-input" id="holder" name="holder" type="text" placeholder="Nome completo"
                               autocomplete="cc-name" maxlength="120" value="<?= e((string)$resumo['paciente']) ?>" required>

                        <label for="country">País ou região</label>
                        <select id="country" name="pais">
                            <option value="PT" selected>Portugal</option>
                            <option value="ES">Espanha</option>
                            <option value="FR">França</option>
                            <option value="DE">Alemanha</option>
                            <option value="GB">Reino Unido</option>
                            <option value="CH">Suíça</option>
                            <option value="LU">Luxemburgo</option>
                            <option value="BR">Brasil</option>
                            <option value="AO">Angola</option>
                            <option value="MZ">Moçambique</option>
                            <option value="CV">Cabo Verde</option>
                            <option value="US">Estados Unidos</option>
                            <option value="CA">Canadá</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>

                    <p class="terms">
                        Ao confirmar o pagamento, autorizas a Clínica Dentária a cobrar o valor indicado.
                        O processamento do cartão é feito pela Stripe; a clínica não recebe nem guarda os dados completos do cartão.
                        Consulta os <a href="termos.php">termos</a> e a <a href="privacidade.php">política de privacidade</a>.
                    </p>

                    <div id="checkout-error" class="checkout-error" role="alert" aria-live="assertive"></div>

                    <button id="pay-button" class="pay-button" type="submit" disabled>
                        Pagar <?= e(formatar_preco($resumo['valor'])) ?>
                    </button>

                    <div class="stripe-footer">
                        <span>Powered by <strong>stripe</strong></span>
                        <span class="separator" aria-hidden="true"></span>
                        <a href="termos.php">Termos</a>
                        <a href="privacidade.php">Privacidade</a>
                    </div>
                </form>
            </section>
        </div>
    <?php endif; ?>
</main>
<?php if ($erroPagina === ''): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="<?= e(BASE_URL) ?>js/checkout.js?v=<?= (int)@filemtime(__DIR__ . '/js/checkout.js') ?>"></script>
<?php endif; ?>
</body>
</html>
