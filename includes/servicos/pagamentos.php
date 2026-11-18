<?php
declare(strict_types=1);

/**
 * Evento de gateway inválido de forma permanente (ex.: valor/moeda não
 * correspondem ao pagamento registado). O webhook responde 4xx para evitar
 * retentativas infinitas.
 */
final class GatewayEventoInvalidoException extends RuntimeException {}

function pagamentos_metodos_presenciais(): array
{
    $raw = (string)config_valor_texto(db(), 'metodos_pagamento_presenciais', 'dinheiro,cartao_debito,cartao_credito,mbway_presencial');
    $permitidos = [
        'dinheiro' => 'Dinheiro',
        'cartao_debito' => 'Cartão de débito',
        'cartao_credito' => 'Cartão de crédito',
        'mbway_presencial' => 'MB WAY (presencial)',
        'transferencia' => 'Transferência',
        'outro' => 'Outro',
    ];
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', $raw))) as $k) {
        if (isset($permitidos[$k])) { $out[$k] = $permitidos[$k]; }
    }
    return $out ?: $permitidos;
}

function pagamento_estado_legivel(string $estado): string
{
    $map = [
        'pendente' => 'Pendente',
        'em_processamento' => 'Em processamento',
        'pago' => 'Pago',
        'parcialmente_pago' => 'Parcialmente pago',
        'recusado' => 'Recusado',
        'falhado' => 'Falhado',
        'cancelado' => 'Cancelado',
        'reembolsado' => 'Reembolsado',
        'parcialmente_reembolsado' => 'Parcialmente reembolsado',
    ];
    return $map[$estado] ?? $estado;
}

function pagamento_metodo_legivel(string $metodo, ?string $detalhe = null): string
{
    $base = [
        'presencial' => 'Presencial',
        'eletronico_antecipado' => 'Online (antecipado)',
        'sinal' => 'Sinal',
        'mbway' => 'MB WAY',
        'multibanco' => 'Multibanco',
        'cartao_online' => 'Cartão online',
        'outro' => 'Outro',
    ];
    $det = [
        'dinheiro' => 'Dinheiro',
        'cartao_debito' => 'Cartão de débito',
        'cartao_credito' => 'Cartão de crédito',
        'mbway_presencial' => 'MB WAY presencial',
        'transferencia' => 'Transferência',
        'outro' => 'Outro',
    ];
    $b = $base[$metodo] ?? $metodo;
    if ($detalhe !== null && $detalhe !== '' && isset($det[$detalhe])) {
        return $det[$detalhe];
    }
    return $b;
}

function estado_financeiro_marcacao(PDO $pdo, int $marcacaoId): array
{
    $st = $pdo->prepare('SELECT id, referencia, total_previsto, estado, utilizador_id FROM marcacoes WHERE id = ?');
    $st->execute([$marcacaoId]);
    $m = $st->fetch();
    if (!$m) {
        throw new RuntimeException('Marcação não encontrada.');
    }
    $total = round((float)$m['total_previsto'], 2);
    $st = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN estado IN ('pago','reembolsado','parcialmente_reembolsado') THEN GREATEST(0, valor - valor_reembolsado) ELSE 0 END),0) AS pago_liq,
                                COALESCE(SUM(CASE WHEN estado IN ('pendente','em_processamento','parcialmente_pago') THEN valor ELSE 0 END),0) AS pendente,
                                COALESCE(SUM(valor_reembolsado),0) AS reembolsado
                           FROM pagamentos WHERE marcacao_id = ?");
    $st->execute([$marcacaoId]);
    $r = $st->fetch() ?: ['pago_liq'=>0,'pendente'=>0,'reembolsado'=>0];
    $pago = round((float)$r['pago_liq'], 2);
    $divida = max(0.0, round($total - $pago, 2));
    $pendenteReg = round((float)$r['pendente'], 2);
    $reembolsado = round((float)$r['reembolsado'], 2);
    $estado = 'Pendente';
    if ($pago <= 0.0001 && $divida > 0) { $estado = 'Pendente'; }
    elseif ($pago > 0.0001 && $divida > 0.0001) { $estado = 'Parcialmente pago'; }
    elseif ($divida <= 0.0001 && $total > 0) { $estado = 'Pago'; }
    if ($reembolsado > 0.0001 && $pago <= 0.0001) { $estado = 'Reembolsado'; }
    elseif ($reembolsado > 0.0001 && $divida > 0.0001) { $estado = 'Parcialmente reembolsado / com dívida'; }
    return [
        'marcacao' => $m,
        'total' => $total,
        'pago' => $pago,
        'divida' => $divida,
        'pendente_registado' => $pendenteReg,
        'reembolsado' => $reembolsado,
        'estado_financeiro' => $estado,
    ];
}

function pagamento_pode_gerir_staff(array $u): bool
{
    return in_array($u['papel'] ?? '', ['admin', 'rececao'], true);
}

function registar_pagamento_presencial_detalhado(PDO $pdo, array $ctx): array
{
    $marcacaoId = (int)($ctx['marcacao_id'] ?? 0);
    $valor = round((float)($ctx['valor'] ?? 0), 2);
    $metodoDetalhe = (string)($ctx['metodo_detalhe'] ?? 'dinheiro');
    $consultaId = !empty($ctx['consulta_id']) ? (int)$ctx['consulta_id'] : null;
    $dataPagamento = (string)($ctx['data_pagamento'] ?? '');
    $observacoes = mb_substr(trim((string)($ctx['observacoes'] ?? '')), 0, 255);
    $staffId = (int)($ctx['staff_id'] ?? 0);
    $metodos = pagamentos_metodos_presenciais();
    if (!isset($metodos[$metodoDetalhe])) {
        throw new RuntimeException('Método de pagamento presencial inválido.');
    }
    if ($dataPagamento !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $dataPagamento) ?: DateTime::createFromFormat('Y-m-d H:i:s', $dataPagamento) ?: DateTime::createFromFormat('Y-m-d', $dataPagamento);
        if (!$dt) { throw new RuntimeException('Data de pagamento inválida.'); }
        $dataPagamentoSql = $dt->format('Y-m-d H:i:s');
    } else {
        $dataPagamentoSql = date('Y-m-d H:i:s');
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT id, estado, utilizador_id, referencia FROM marcacoes WHERE id = ? FOR UPDATE');
        $st->execute([$marcacaoId]);
        $m = $st->fetch();
        if (!$m || !in_array($m['estado'], ['confirmada', 'concluida'], true)) {
            throw new RuntimeException('Só se registam pagamentos em marcações confirmadas ou concluídas.');
        }
        if ($consultaId !== null) {
            $st = $pdo->prepare('SELECT id FROM consultas WHERE id = ? AND marcacao_id = ?');
            $st->execute([$consultaId, $marcacaoId]);
            if (!$st->fetchColumn()) { throw new RuntimeException('Consulta inválida para esta marcação.'); }
        }
        $fin = estado_financeiro_marcacao($pdo, $marcacaoId);
        $falta = (float)$fin['divida'];
        if ($falta <= 0.0001) {
            throw new RuntimeException('Esta marcação já está liquidada.');
        }
        if ($valor <= 0.0001 || $valor > $falta + 0.0001) {
            throw new RuntimeException('O valor tem de ser entre 0,01 € e ' . formatar_preco($falta) . '.');
        }
        $valor = min($valor, $falta);
        $referencia = 'CLIN-' . strtoupper(bin2hex(random_bytes(5)));

        $st = $pdo->prepare("SELECT id, valor FROM pagamentos WHERE marcacao_id = ? AND metodo = 'presencial' AND estado = 'pendente' ORDER BY id LIMIT 1 FOR UPDATE");
        $st->execute([$marcacaoId]);
        $pend = $st->fetch();
        if ($pend) {
            $pdo->prepare("UPDATE pagamentos SET valor=?, metodo='presencial', metodo_detalhe=?, estado='pago', prestador='clinica', referencia_transacao=?, consulta_id=?, registado_por=?, data_pagamento=?, pago_em=?, observacoes=? WHERE id=?")
                ->execute([$valor, $metodoDetalhe, $referencia, $consultaId, $staffId ?: null, $dataPagamentoSql, $dataPagamentoSql, $observacoes !== '' ? $observacoes : null, $pend['id']]);
        } else {
            $pdo->prepare("INSERT INTO pagamentos (marcacao_id, consulta_id, metodo, metodo_detalhe, valor, estado, prestador, referencia_transacao, registado_por, data_pagamento, pago_em, observacoes) VALUES (?, ?, 'presencial', ?, ?, 'pago', 'clinica', ?, ?, ?, ?, ?)")
                ->execute([$marcacaoId, $consultaId, $metodoDetalhe, $valor, $referencia, $staffId ?: null, $dataPagamentoSql, $dataPagamentoSql, $observacoes !== '' ? $observacoes : null]);
        }
        $pdo->prepare("DELETE FROM pagamentos WHERE marcacao_id = ? AND metodo = 'presencial' AND estado = 'pendente'")->execute([$marcacaoId]);
        $finDepois = estado_financeiro_marcacao($pdo, $marcacaoId);
        $resto = (float)$finDepois['divida'];
        if ($resto > 0.0001) {
            $pdo->prepare("INSERT INTO pagamentos (marcacao_id, consulta_id, metodo, valor, estado, observacoes) VALUES (?, ?, 'presencial', ?, 'pendente', ?)")
                ->execute([$marcacaoId, $consultaId, round($resto, 2), 'Valor em dívida (gerado automaticamente)']);
        }

        $msg = 'Recebemos ' . formatar_preco($valor) . ' da marcação ' . $m['referencia'] . ' (' . pagamento_metodo_legivel('presencial', $metodoDetalhe) . ').';
        $msg .= $resto > 0.0001 ? ' Falta pagar ' . formatar_preco($resto) . '.' : ' Valor liquidado.';
        criar_notificacao($pdo, (int)$m['utilizador_id'], $marcacaoId, 'pagamento_confirmado', 'Pagamento registado', $msg);
        registar_admin('registar_pagamento', 'marcacoes', $marcacaoId, formatar_preco($valor) . ' ' . $metodoDetalhe . ($consultaId ? ' consulta#'.$consultaId : ''));
        $pdo->commit();
        return ['resto' => round($resto, 2), 'referencia' => $referencia];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function registar_reembolso_administrativo(PDO $pdo, int $pagamentoId, float $valor, string $motivo, int $staffId): array
{
    $valor = round($valor, 2);
    $motivo = mb_substr(trim($motivo), 0, 255);
    if ($valor <= 0) { throw new RuntimeException('Valor de reembolso inválido.'); }
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT p.*, m.utilizador_id, m.referencia FROM pagamentos p JOIN marcacoes m ON m.id=p.marcacao_id WHERE p.id=? FOR UPDATE");
        $st->execute([$pagamentoId]);
        $p = $st->fetch();
        if (!$p) { throw new RuntimeException('Pagamento não encontrado.'); }
        if (!in_array($p['estado'], ['pago','parcialmente_reembolsado'], true)) {
            throw new RuntimeException('Só é possível reembolsar pagamentos no estado Pago (ou parcialmente reembolsado).');
        }
        $disponivel = round((float)$p['valor'] - (float)$p['valor_reembolsado'], 2);
        if ($valor > $disponivel + 0.0001) {
            throw new RuntimeException('O valor excede o disponível para reembolso (' . formatar_preco($disponivel) . ').');
        }
        $novo = round((float)$p['valor_reembolsado'] + min($valor, $disponivel), 2);
        $estado = $novo >= round((float)$p['valor'], 2) - 0.0001 ? 'reembolsado' : 'parcialmente_reembolsado';
        $obs = trim(((string)($p['observacoes'] ?? '')) . ($motivo !== '' ? "\n[Reembolso admin] ".$motivo : "\n[Reembolso admin registado]"));
        $pdo->prepare('UPDATE pagamentos SET valor_reembolsado=?, estado=?, reembolsado_em=NOW(), observacoes=? WHERE id=?')
            ->execute([$novo, $estado, mb_substr($obs,0,255), $pagamentoId]);
        registar_admin('registar_reembolso', 'pagamentos', $pagamentoId, formatar_preco($novo - (float)$p['valor_reembolsado']) . ($motivo?': '.$motivo:'') . ' [registo administrativo; devolução real depende do meio de pagamento]');
        criar_notificacao($pdo, (int)$p['utilizador_id'], (int)$p['marcacao_id'], 'pagamento_confirmado', 'Reembolso registado',
            'Foi registado um reembolso de ' . formatar_preco($novo - (float)$p['valor_reembolsado']) . ' na marcação ' . $p['referencia'] . '. Se aplicável, a devolução pelo meio de pagamento segue o processo da clínica/prestador.');
        $pdo->commit();
        return ['novo_estado' => $estado, 'valor_reembolsado_total' => $novo];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

/**
 * Valida o valor/moeda de um evento de gateway contra o pagamento registado.
 * Eventos sem valor (gateways que não o enviam) não são bloqueados; quando o
 * valor existe, a tolerância máxima é 1 cêntimo.
 *
 * @return array{ok:bool, verificado:bool, erro:string}
 */
function gateway_validar_valor_evento(?float $valorEvento, ?string $moedaEvento, float $valorEsperado, string $moedaEsperada): array
{
    $moedaEsperada = mb_strtolower(trim($moedaEsperada));
    $moedaEvento   = $moedaEvento === null ? '' : mb_strtolower(trim($moedaEvento));

    if ($valorEvento === null) {
        return ['ok' => true, 'verificado' => false, 'erro' => ''];
    }
    if (!is_finite($valorEvento) || $valorEvento < 0) {
        return ['ok' => false, 'verificado' => true, 'erro' => 'valor do evento inválido.'];
    }
    if (abs(round($valorEvento, 2) - round($valorEsperado, 2)) > 0.01) {
        return ['ok' => false, 'verificado' => true, 'erro' => 'valor do evento não corresponde ao pagamento.'];
    }
    if ($moedaEvento !== '' && $moedaEsperada !== '' && $moedaEvento !== $moedaEsperada) {
        return ['ok' => false, 'verificado' => true, 'erro' => 'moeda do evento não corresponde à configuração.'];
    }
    return ['ok' => true, 'verificado' => true, 'erro' => ''];
}

function gateway_confirmar_pagamento(PDO $pdo, array $evt): array
{
    $gateway = (string)($evt['gateway'] ?? '');
    $idempotency = (string)($evt['idempotency_key'] ?? '');
    $marcacaoId = (int)($evt['marcacao_id'] ?? 0);
    $pagamentoId = !empty($evt['pagamento_id']) ? (int)$evt['pagamento_id'] : null;
    $confirmado = (bool)($evt['confirmado'] ?? false);
    $estadoDestino = $confirmado ? 'pago' : (string)($evt['estado'] ?? 'falhado');
    $referencia = (string)($evt['referencia_transacao'] ?? '');
    $metodoGateway = (string)($evt['gateway_metodo'] ?? '');
    if ($gateway === '' || $idempotency === '') {
        throw new RuntimeException('Evento de gateway inválido.');
    }
    if (!in_array($estadoDestino, ['pago','falhado','em_processamento','cancelado'], true)) {
        $estadoDestino = 'falhado';
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT id, estado, marcacao_id FROM pagamentos WHERE gateway = ? AND idempotency_key = ? LIMIT 1 FOR UPDATE');
        $st->execute([$gateway, $idempotency]);
        $exist = $st->fetch();
        if ($exist) {
            $pdo->commit();
            return ['deduplicado' => true, 'pagamento_id' => (int)$exist['id'], 'estado' => $exist['estado']];
        }
        if ($pagamentoId) {
            $st = $pdo->prepare('SELECT p.*, m.utilizador_id, m.referencia AS marc_ref, m.estado AS marc_estado FROM pagamentos p JOIN marcacoes m ON m.id=p.marcacao_id WHERE p.id=? FOR UPDATE');
            $st->execute([$pagamentoId]);
        } else {
            $st = $pdo->prepare("SELECT p.*, m.utilizador_id, m.referencia AS marc_ref, m.estado AS marc_estado FROM pagamentos p JOIN marcacoes m ON m.id=p.marcacao_id WHERE p.marcacao_id=? AND p.estado IN ('pendente','em_processamento') ORDER BY p.id DESC LIMIT 1 FOR UPDATE");
            $st->execute([$marcacaoId]);
        }
        $p = $st->fetch();
        if (!$p) { throw new RuntimeException('Pagamento alvo não encontrado.'); }
        if ($p['marc_estado'] === 'cancelada' && $estadoDestino === 'pago') {
            $estadoDestino = 'em_processamento';
        }
        if ($estadoDestino === 'pago') {
            $valorEvento = null;
            if (array_key_exists('valor', $evt) && $evt['valor'] !== null && $evt['valor'] !== '') {
                if (!is_numeric($evt['valor'])) {
                    throw new GatewayEventoInvalidoException('Pagamento rejeitado: valor do evento inválido.');
                }
                $valorEvento = (float)$evt['valor'];
            }
            $moedaEvento = isset($evt['moeda']) && is_scalar($evt['moeda']) ? (string)$evt['moeda'] : null;
            $validacao = gateway_validar_valor_evento(
                $valorEvento,
                $moedaEvento,
                (float)$p['valor'],
                (string)config_valor_texto($pdo, 'moeda', 'EUR')
            );
            if (!$validacao['ok']) {
                throw new GatewayEventoInvalidoException('Pagamento rejeitado: ' . $validacao['erro']);
            }
        }
        $sql = "UPDATE pagamentos SET estado=?, gateway=?, gateway_metodo=?, idempotency_key=?, prestador=?, referencia_transacao=?, atualizado_em=NOW()";
        $params = [$estadoDestino, $gateway, $metodoGateway ?: null, $idempotency, $gateway, $referencia ?: ($p['referencia_transacao'] ?? null)];
        if ($estadoDestino === 'pago') { $sql .= ", pago_em=NOW(), data_pagamento=COALESCE(data_pagamento,NOW())"; }
        if ($estadoDestino === 'falhado') { $sql .= ", falhado_em=NOW()"; }
        $sql .= " WHERE id=?";
        $params[] = $p['id'];
        $pdo->prepare($sql)->execute($params);

        if ($estadoDestino === 'pago') {
            $pdo->prepare("UPDATE marcacoes SET estado='confirmada' WHERE id=? AND estado <> 'cancelada'")->execute([(int)$p['marcacao_id']]);
            $pdo->prepare("UPDATE consultas SET estado='confirmada' WHERE marcacao_id=? AND estado <> 'cancelada'")->execute([(int)$p['marcacao_id']]);
            $pdo->prepare('UPDATE horarios_ocupados SET expira_em=NULL WHERE consulta_id IN (SELECT id FROM consultas WHERE marcacao_id=?)')->execute([(int)$p['marcacao_id']]);
            criar_notificacao($pdo, (int)$p['utilizador_id'], (int)$p['marcacao_id'], 'pagamento_confirmado', 'Pagamento confirmado',
                'O pagamento da marcação ' . $p['marc_ref'] . ' foi confirmado.');
        } elseif ($estadoDestino === 'falhado') {
            criar_notificacao($pdo, (int)$p['utilizador_id'], (int)$p['marcacao_id'], 'pagamento_pendente', 'Pagamento não confirmado',
                'Não foi possível confirmar o pagamento da marcação ' . $p['marc_ref'] . '. Contacte a clínica se necessário.');
        }
        $pdo->commit();
        return ['deduplicado' => false, 'pagamento_id' => (int)$p['id'], 'estado' => $estadoDestino];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}