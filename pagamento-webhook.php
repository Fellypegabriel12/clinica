<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';
require __DIR__ . '/includes/conta.php';
require __DIR__ . '/includes/pagamentos.php';
require __DIR__ . '/includes/gateway.php';

$__gatewayForcado = trim((string)($_GET['gateway'] ?? ''));

header('Content-Type: application/json; charset=utf-8');

$metodoHttp = (string)($_SERVER['REQUEST_METHOD'] ?? '');
if ($metodoHttp !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'erro' => 'Método não suportado. Use POST.']);
    exit;
}

// Limite de tamanho razoável para webhook (proteção contra DoS).
// Stripe/Eupago enviam payloads <100KB normalmente; 1MB é margem folgada.
$__maxBytes = 1_048_576;
$__clen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($__clen > $__maxBytes) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'erro' => 'Payload demasiado grande.']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
if (strlen($raw) > $__maxBytes) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'erro' => 'Payload demasiado grande.']);
    exit;
}

$request = $_POST;
if ($raw !== '' && stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $j = json_decode($raw, true);
    if (is_array($j)) { $request = array_merge($request, $j); }
}

try {
    $pdo = db();
    if ($__gatewayForcado === 'stripe') {
        $gateway = new GatewayStripe($pdo);
    } else {
        $gateway = gateway_obter($pdo);
    }

    $val = $gateway->validarWebhook($request, $raw);

    // FIX (médio): falha de validação devolve SEMPRE 4xx, para TODOS os gateways.
    // Antes só o Stripe recebia 400; Eupago/futuros recebiam 200 e nunca retentavam,
    // perdendo pagamentos quando o erro era transitório.
    if (empty($val['valido'])) {
        error_log('Webhook pagamento rejeitado: ' . ($val['erro'] ?? 'inválido'));
        http_response_code(401);
        echo json_encode(['ok' => false, 'erro' => 'Não autorizado.']);
        exit;
    }

    $evento = $val['evento'] ?? [];

    // Evento marcado como ignorar: acknowledge 200 (é intencional, não é erro).
    if (!empty($evento['ignorar'])) {
        http_response_code(200);
        echo json_encode(['ok' => true, 'ignorado' => true]);
        exit;
    }

    // --- Reembolso: regista observação idempotente; NÃO altera valores automaticamente ---
    // FIX (menor): a marca do evento é escrita na observação (não em idempotency_key,
    // que já está ocupada pelo evento de checkout original). Repetição é detetada pelo marcador.
    if (!empty($evento['refund'])) {
        $evtId = (string)($evento['idempotency_key'] ?? '');
        $pid   = (int)($evento['pagamento_id'] ?? 0);
        try {
            if ($pid > 0 && $evtId !== '') {
                $st = $pdo->prepare('SELECT id, observacoes FROM pagamentos WHERE id = ?');
                $st->execute([$pid]);
                if ($row = $st->fetch()) {
                    $marker = '[Stripe ' . $evtId . ']';
                    $obs    = (string)($row['observacoes'] ?? '');
                    if (strpos($obs, $marker) === false) {
                        $novaObs = trim($obs . "\n" . $marker . ' Evento de reembolso recebido (requer reconciliação administrativa).');
                        $pdo->prepare('UPDATE pagamentos SET observacoes = ? WHERE id = ?')
                            ->execute([mb_substr($novaObs, 0, 255), $pid]);
                    }
                }
            }
            registar_admin('webhook_refund', 'pagamentos', $pid ?: null, 'Evento de reembolso Stripe processado');
        } catch (Throwable $e) {
            error_log('Webhook refund erro: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Erro interno.']);
            exit;
        }
        http_response_code(200);
        echo json_encode(['ok' => true, 'refund_event' => true]);
        exit;
    }

    // FIX (menor): event sem idempotency_key é erro PERMANENTE — 4xx para o gateway não retentar.
    if (empty($evento['idempotency_key'])) {
        error_log('Webhook pagamento sem idempotency_key: ' . json_encode($evento));
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Evento sem identificador idempotente.']);
        exit;
    }

    $res = gateway_confirmar_pagamento($pdo, $evento);

    // FIX (menor): registar em audit trail para rastreabilidade do pagamento.
    try {
        registar_admin('webhook_pagamento', 'pagamentos', (int)($res['pagamento_id'] ?? 0),
            'Evento ' . ($evento['gateway'] ?? '') . ' → estado ' . ($res['estado'] ?? '?')
            . ($res['deduplicado'] ?? false ? ' (duplicado)' : ''));
    } catch (Throwable $e) {
        error_log('Webhook registar_admin: ' . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'deduplicado' => !empty($res['deduplicado']),
        'estado' => $res['estado'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log('Webhook pagamento erro: ' . $e->getMessage());
    // FIX (médio): erro de processamento devolve 500 para o gateway RETENTAR.
    // Antes devolvia 200 e o pagamento perdia-se silenciosamente.
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro interno.']);
}