<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';
require __DIR__ . '/includes/conta.php';
require __DIR__ . '/includes/pagamentos.php';
require __DIR__ . '/includes/gateway.php';

// Seletor de gateway por query/path auxiliar (Stripe envia para endpoint dedicado/configurável).
// Mantém compatibilidade: sem parâmetro usa gateway_pagamentos configurado.
$__gatewayForcado = trim((string)($_GET['gateway'] ?? ''));

// Endpoint de callback/webhook. Nunca confirma pagamento sem validação autenticada.
// Protegido contra notificações repetidas via idempotency key.
// Não expor dados sensíveis; registar mínimo necessário.
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

$metodoHttp = (string)($_SERVER['REQUEST_METHOD'] ?? '');
if ($metodoHttp !== 'POST') {
    // Callbacks autenticados devem usar POST; GET pode expor segredos em logs/URLs.
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'erro' => 'Método não suportado. Use POST.']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
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
    if (empty($val['valido'])) {
        error_log('Webhook pagamento rejeitado: ' . ($val['erro'] ?? 'inválido'));
        // Para Stripe, responder 400 em assinatura inválida ajuda retries/comportamento correto; não expor detalhes.
        if ($gateway->nome() === 'stripe') { http_response_code(400); }
        echo json_encode(['ok' => false, 'erro' => 'Não autorizado.']);
        exit;
    }
    $evento = $val['evento'] ?? [];
    if (!empty($evento['ignorar'])) {
        echo json_encode(['ok' => true, 'ignorado' => true]);
        exit;
    }
    if (!empty($evento['refund'])) {
        // Evento de reembolso: idempotente (regista marca) mas não altera automaticamente valores sem reconciliação segura.
        // Registar ocorrência deduplicada via gateway_confirmar_pagamento com estado em_processamento? melhor só ack se já visto.
        try {
            // tentativa de marca de idempotência criando registo não aplicável: reutilizar dedupe verificando pagamentos
            $st = $pdo->prepare('SELECT 1 FROM pagamentos WHERE gateway = ? AND idempotency_key = ? LIMIT 1');
            $st->execute(['stripe', (string)$evento['idempotency_key']]);
            if (!$st->fetchColumn() && !empty($evento['pagamento_id'])) {
                // não modificar montantes automaticamente; atualizar observação se possível
                $stp = $pdo->prepare('SELECT id, observacoes FROM pagamentos WHERE id = ?');
                $stp->execute([(int)$evento['pagamento_id']]);
                if ($row = $stp->fetch()) {
                    $obs = trim((string)($row['observacoes'] ?? '') . "\n[Stripe] Evento de reembolso recebido (requer reconciliação administrativa).");
                    $pdo->prepare('UPDATE pagamentos SET observacoes = ?, idempotency_key = COALESCE(NULLIF(idempotency_key,\'\'), ?) WHERE id = ?')
                        ->execute([mb_substr($obs,0,255), (string)$evento['idempotency_key'], $row['id']]);
                }
            }
        } catch (Throwable $e) { /* não falhar o webhook */ }
        echo json_encode(['ok' => true, 'refund_event' => true]);
        exit;
    }
    if (empty($evento['idempotency_key'])) {
        echo json_encode(['ok' => false, 'erro' => 'Evento sem identificador idempotente.']);
        exit;
    }
    $res = gateway_confirmar_pagamento($pdo, $evento);
    echo json_encode(['ok' => true, 'deduplicado' => !empty($res['deduplicado']), 'estado' => $res['estado'] ?? null]);
} catch (Throwable $e) {
    error_log('Webhook pagamento erro: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'erro' => 'Erro interno.']);
}
