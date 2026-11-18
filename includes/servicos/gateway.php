<?php
declare(strict_types=1);

interface GatewayPagamento {
    public function nome(): string;
    public function iniciarPagamento(array $ctx): array;
    public function validarWebhook(array $request, string $rawBody): array;
}

final class GatewayNaoConfigurado implements GatewayPagamento {
    public function nome(): string { return 'nao_configurado'; }
    public function iniciarPagamento(array $ctx): array {
        return ['ok' => false, 'erro' => 'Gateway de pagamento online não configurado. Configure credenciais/contrato com o fornecedor (ex.: Eupago) e defina gateway_pagamentos + segredo de webhook nas configurações de administração. Pagamento presencial permanece disponível.'];
    }
    public function validarWebhook(array $request, string $rawBody): array {
        return ['valido' => false, 'evento' => [], 'erro' => 'Gateway não configurado.'];
    }
}

final class GatewayEupagoScaffold implements GatewayPagamento {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function nome(): string { return 'eupago'; }
    public function iniciarPagamento(array $ctx): array {
        $apiKey = trim((string)config_valor_texto($this->pdo, 'eupago_api_key', ''));
        $ambiente = trim((string)config_valor_texto($this->pdo, 'gateway_ambiente', 'teste'));
        if ($apiKey === '') {
            return ['ok' => false, 'erro' => 'Eupago selecionado mas sem credenciais configuradas (eupago_api_key). Em ambiente de testes, configure apenas credenciais de sandbox fornecidas pelo Eupago; nunca use credenciais de produção em código.'];
        }
        return ['ok' => false, 'erro' => 'Integração API Eupago pendente de implementação com credenciais/contrato de teste. O sistema está preparado (abstração, webhook idempotente, estados), mas não inicia cobranças reais sem implementação/validação final do fornecedor.'];
    }
    public function validarWebhook(array $request, string $rawBody): array {
        $segredo = trim((string)config_valor_texto($this->pdo, 'gateway_webhook_segredo', ''));
        if ($segredo === '') {
            return ['valido' => false, 'evento' => [], 'erro' => 'Segredo de webhook não configurado.'];
        }
        $token = (string)($request['token'] ?? $request['chave_api'] ?? $request['webhook_secret'] ?? '');
        $headerToken = '';
        foreach (['HTTP_X_WEBHOOK_SECRET','HTTP_X_EUPAGO_SECRET','HTTP_AUTHORIZATION'] as $h) {
            if (!empty($_SERVER[$h])) { $headerToken = (string)$_SERVER[$h]; break; }
        }
        $valido = hash_equals($segredo, $token) || hash_equals($segredo, $headerToken);
        if (!$valido) {
            return ['valido' => false, 'evento' => [], 'erro' => 'Autenticação do webhook falhou.'];
        }
        $id = (string)($request['identificador'] ?? $request['referencia'] ?? $request['transaction_id'] ?? $request['mp'] ?? '');
        $estadoRaw = mb_strtolower((string)($request['estado'] ?? $request['status'] ?? ''));
        $confirmado = in_array($estadoRaw, ['pago','paid','success','ok','confirmado','aceite'], true);
        $marcacaoRef = (string)($request['marcacao_ref'] ?? $request['ref'] ?? '');
        $marcacaoId = (int)($request['marcacao_id'] ?? 0);
        $pagamentoId = (int)($request['pagamento_id'] ?? 0);
        $gatewayMetodo = (string)($request['metodo'] ?? $request['method'] ?? '');
        return ['valido' => true, 'evento' => [
            'gateway' => 'eupago',
            'idempotency_key' => $id !== '' ? ('eupago:'.$id) : '',
            'marcacao_id' => $marcacaoId,
            'pagamento_id' => $pagamentoId ?: null,
            'marcacao_ref' => $marcacaoRef,
            'confirmado' => $confirmado,
            'estado' => $confirmado ? 'pago' : 'falhado',
            'referencia_transacao' => $id,
            'gateway_metodo' => $gatewayMetodo,
        ], 'erro' => ''];
    }
}

final class GatewayStripe implements GatewayPagamento {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function nome(): string { return 'stripe'; }

    private function chaveSecreta(): string {
        $env = getenv('STRIPE_SECRET_KEY');
        if (is_string($env) && trim($env) !== '') { return trim($env); }
        return trim((string)config_valor_texto($this->pdo, 'stripe_secret_key', ''));
    }
    private function segredoWebhook(): string {
        $env = getenv('STRIPE_WEBHOOK_SECRET');
        if (is_string($env) && trim($env) !== '') { return trim($env); }
        return trim((string)config_valor_texto($this->pdo, 'stripe_webhook_secret', ''));
    }

    public function iniciarPagamento(array $ctx): array {
        $sk = $this->chaveSecreta();
        if ($sk === '') {
            return ['ok' => false, 'erro' => 'Stripe não configurado (chave secreta em falta). Configure STRIPE_SECRET_KEY (variável de ambiente) ou stripe_secret_key nas configurações.'];
        }
        $valor = round((float)($ctx['valor'] ?? 0), 2);
        if ($valor <= 0) { return ['ok' => false, 'erro' => 'Valor de pagamento inválido.']; }
        $amount = (int)round($valor * 100);
        $moeda = mb_strtolower(trim((string)config_valor_texto($this->pdo, 'moeda', 'EUR')));
        $ref = (string)($ctx['referencia'] ?? '');
        $marcacaoId = (int)($ctx['marcacao_id'] ?? 0);
        $pagamentoId = (int)($ctx['pagamento_id'] ?? 0);
        $tipo = (string)($ctx['tipo'] ?? 'total');
        $base = gateway_base_url($this->pdo);
        $success = $base . '/pagamento-retorno.php?ref=' . urlencode($ref) . '&stripe=success&session_id={CHECKOUT_SESSION_ID}';
        $cancel  = $base . '/marcar-pagamento.php?ref=' . urlencode($ref) . '&stripe=cancel';

        $params = [
            'mode' => 'payment',
            'success_url' => $success,
            'cancel_url' => $cancel,
            'client_reference_id' => $ref,
            'line_items[0][price_data][currency]' => $moeda ?: 'eur',
            'line_items[0][price_data][product_data][name]' => ($tipo === 'sinal' ? 'Sinal da consulta ' : 'Pagamento da consulta ') . $ref,
            'line_items[0][price_data][unit_amount]' => (string)$amount,
            'line_items[0][quantity]' => '1',
            'metadata[marcacao_id]' => (string)$marcacaoId,
            'metadata[pagamento_id]' => (string)$pagamentoId,
            'metadata[referencia]' => $ref,
            'metadata[tipo]' => $tipo,
            'payment_intent_data[metadata][marcacao_id]' => (string)$marcacaoId,
            'payment_intent_data[metadata][pagamento_id]' => (string)$pagamentoId,
            'payment_intent_data[metadata][referencia]' => $ref,
            'payment_intent_data[metadata][tipo]' => $tipo,
        ];

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $sk,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            return ['ok' => false, 'erro' => 'Falha na ligação ao Stripe: ' . $err];
        }
        $json = json_decode($resp, true);
        if ($http >= 400 || !is_array($json) || empty($json['id']) || empty($json['url'])) {
            $msg = is_array($json) && isset($json['error']['message']) ? (string)$json['error']['message'] : 'Resposta inválida do Stripe.';
            return ['ok' => false, 'erro' => 'Stripe: ' . $msg];
        }
        $sessionId = (string)$json['id'];
        return [
            'ok' => true,
            'redirect_url' => (string)$json['url'],
            'referencia' => $sessionId,
            'session_id' => $sessionId,
            'idempotency_key' => 'stripe:checkout:' . $sessionId,
            'gateway_metodo' => 'cartao_online',
        ];
    }

    public function validarWebhook(array $request, string $rawBody): array {
        $secret = $this->segredoWebhook();
        if ($secret === '') {
            return ['valido' => false, 'evento' => [], 'erro' => 'Segredo de webhook Stripe não configurado.'];
        }
        $sigHeader = '';
        foreach (['HTTP_STRIPE_SIGNATURE', 'Stripe-Signature', 'stripe-signature'] as $k) {
            if (!empty($_SERVER[$k])) { $sigHeader = (string)$_SERVER[$k]; break; }
            if (!empty($request[$k])) { $sigHeader = (string)$request[$k]; break; }
        }
        if ($sigHeader === '') {
            return ['valido' => false, 'evento' => [], 'erro' => 'Assinatura Stripe em falta.'];
        }
        $parts = [];
        foreach (explode(',', $sigHeader) as $item) {
            $kv = explode('=', trim($item), 2);
            if (count($kv) === 2) { $parts[$kv[0]] = $kv[1]; }
        }
        $t = $parts['t'] ?? '';
        $v1 = $parts['v1'] ?? '';
        if ($t === '' || $v1 === '') {
            return ['valido' => false, 'evento' => [], 'erro' => 'Formato de assinatura inválido.'];
        }
        if (abs(time() - (int)$t) > 300) {
            return ['valido' => false, 'evento' => [], 'erro' => 'Assinatura expirada.'];
        }
        $signed = $t . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signed, $secret);
        if (!hash_equals($expected, $v1)) {
            return ['valido' => false, 'evento' => [], 'erro' => 'Assinatura inválida.'];
        }
        $event = json_decode($rawBody, true);
        if (!is_array($event) || empty($event['type']) || empty($event['id'])) {
            return ['valido' => false, 'evento' => [], 'erro' => 'Evento Stripe inválido.'];
        }
        $type = (string)$event['type'];
        $obj = $event['data']['object'] ?? [];
        if (!is_array($obj)) { $obj = []; }

        $meta = $obj['metadata'] ?? [];
        if (!is_array($meta)) { $meta = []; }
        $marcacaoId = (int)($meta['marcacao_id'] ?? $request['marcacao_id'] ?? 0);
        $pagamentoId = (int)($meta['pagamento_id'] ?? $request['pagamento_id'] ?? 0);
        $referenciaMarc = (string)($meta['referencia'] ?? $obj['client_reference_id'] ?? '');

        $confirmado = null;
        $estado = 'em_processamento';
        $gatewayMetodo = 'cartao_online';
        $txRef = (string)($obj['id'] ?? $event['id']);

        // Valor e moeda do evento. O Stripe usa a unidade mínima da moeda;
        // a lista abaixo cobre as moedas sem casas decimais.
        $valorEvento = null;
        $moedaEvento = '';
        $centavos = $obj['amount_total'] ?? $obj['amount'] ?? null;
        if (is_numeric($centavos)) {
            $moedaEvento = mb_strtolower(trim((string)($obj['currency'] ?? '')));
            $semDecimais = in_array($moedaEvento, ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf'], true);
            $valorEvento = round(((float)$centavos) / ($semDecimais ? 1 : 100), 2);
        }

        if ($type === 'checkout.session.completed') {
            $paid = ($obj['payment_status'] ?? '') === 'paid';
            $confirmado = $paid;
            $estado = $paid ? 'pago' : 'em_processamento';
            $txRef = (string)($obj['payment_intent'] ?? $obj['id'] ?? $event['id']);
            $gatewayMetodo = 'cartao_online';
        } elseif ($type === 'checkout.session.async_payment_succeeded') {
            $confirmado = true; $estado = 'pago';
            $txRef = (string)($obj['payment_intent'] ?? $obj['id'] ?? $event['id']);
        } elseif ($type === 'checkout.session.async_payment_failed' || $type === 'checkout.session.expired') {
            $confirmado = false; $estado = 'falhado';
        } elseif ($type === 'payment_intent.succeeded') {
            $confirmado = true; $estado = 'pago';
            $txRef = (string)($obj['id'] ?? $event['id']);
        } elseif ($type === 'payment_intent.payment_failed') {
            $confirmado = false; $estado = 'falhado';
            $txRef = (string)($obj['id'] ?? $event['id']);
        } elseif ($type === 'charge.refunded') {
            return ['valido' => true, 'evento' => [
                'gateway' => 'stripe',
                'idempotency_key' => 'stripe:event:' . $event['id'],
                'tipo_evento' => $type,
                'marcacao_id' => $marcacaoId,
                'pagamento_id' => $pagamentoId ?: null,
                'confirmado' => false,
                'estado' => 'em_processamento',
                'referencia_transacao' => (string)($obj['payment_intent'] ?? $obj['id'] ?? ''),
                'gateway_metodo' => 'cartao_online',
                'valor' => $valorEvento,
                'moeda' => $moedaEvento !== '' ? $moedaEvento : null,
                'refund' => true,
            ], 'erro' => ''];
        } else {
            return ['valido' => true, 'evento' => [
                'gateway' => 'stripe',
                'idempotency_key' => 'stripe:event:' . $event['id'],
                'tipo_evento' => $type,
                'ignorar' => true,
            ], 'erro' => ''];
        }

        if ($confirmado === null) {
            return ['valido' => true, 'evento' => ['gateway'=>'stripe','idempotency_key'=>'stripe:event:'.$event['id'],'ignorar'=>true], 'erro' => ''];
        }

        return ['valido' => true, 'evento' => [
            'gateway' => 'stripe',
            'idempotency_key' => 'stripe:event:' . $event['id'],
            'tipo_evento' => $type,
            'marcacao_id' => $marcacaoId,
            'pagamento_id' => $pagamentoId ?: null,
            'marcacao_ref' => $referenciaMarc,
            'confirmado' => (bool)$confirmado,
            'estado' => $estado,
            'referencia_transacao' => $txRef,
            'gateway_metodo' => $gatewayMetodo,
            'valor' => $valorEvento,
            'moeda' => $moedaEvento !== '' ? $moedaEvento : null,
        ], 'erro' => ''];
    }
}

function gateway_obter(PDO $pdo): GatewayPagamento {
    $g = trim((string)config_valor_texto($pdo, 'gateway_pagamentos', ''));
    if ($g === 'stripe') return new GatewayStripe($pdo);
    if ($g === 'eupago') return new GatewayEupagoScaffold($pdo);
    return new GatewayNaoConfigurado();
}

/**
 * URL base absoluta para os retornos do gateway.
 * Prioridade: site_base_url (configuração) > domínio do pedido + BASE_URL.
 * Sem host (CLI) devolve apenas o caminho; nesse caso o Stripe rejeita a URL,
 * pelo que site_base_url deve estar configurado em produção.
 */
function gateway_base_url(PDO $pdo): string
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '' && !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = ''; // rejeita Host inválido (cabeçalho não confiável)
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $basePath = rtrim(defined('BASE_URL') ? (string)BASE_URL : '/', '/');

    $config = trim((string)config_valor_texto($pdo, 'site_base_url', ''));
    if ($config !== '') {
        if (preg_match('~^https?://~i', $config)) {
            return rtrim($config, '/');
        }
        if ($host !== '') {
            return $scheme . '://' . $host . '/' . ltrim($config, '/');
        }
        return rtrim($config, '/');
    }

    if ($host !== '') {
        return $scheme . '://' . $host . $basePath;
    }
    return $basePath;
}

function gateway_stripe_configurado(PDO $pdo): bool {
    $sk = getenv('STRIPE_SECRET_KEY');
    if (is_string($sk) && trim($sk) !== '') { return true; }
    return trim((string)config_valor_texto($pdo, 'stripe_secret_key', '')) !== '';
}
function gateway_stripe_webhook_configurado(PDO $pdo): bool {
    $s = getenv('STRIPE_WEBHOOK_SECRET');
    if (is_string($s) && trim($s) !== '') { return true; }
    return trim((string)config_valor_texto($pdo, 'stripe_webhook_secret', '')) !== '';
}

/**
 * Indica se existe um gateway realmente utilizável para iniciar e confirmar
 * pagamentos online. O Eupago está em scaffold (não inicia cobranças) e o
 * Stripe exige chave secreta + segredo de webhook. Sem isto, o método online
 * não deve aparecer aos pacientes, mesmo com pagamento_online_ativo=1.
 */
function gateway_pagamento_disponivel(PDO $pdo): bool
{
    return gateway_obter($pdo) instanceof GatewayStripe
        && gateway_stripe_configurado($pdo)
        && gateway_stripe_webhook_configurado($pdo);
}