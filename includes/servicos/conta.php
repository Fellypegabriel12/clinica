<?php
declare(strict_types=1);

function nao_lidas(PDO $pdo, int $utilizadorId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM notificacoes WHERE utilizador_id = ? AND lida = 0');
    $st->execute([$utilizadorId]);
    return (int)$st->fetchColumn();
}

function regra_antecedencia(PDO $pdo, string $inicio): array
{
    $horas  = config_valor($pdo, 'cancelamento_antecedencia_h', 24);
    $limite = agora_bd($pdo)->modify('+' . $horas . ' hours');
    return [utc($inicio) >= $limite, $horas];
}

function cancelar_consulta(PDO $pdo, int $consultaId, int $utilizadorId, bool $ignorarAntecedencia = false): float
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'SELECT c.id, c.inicio, c.estado, c.preco_total, m.id AS marcacao_id, m.referencia, m.utilizador_id, m.total_previsto
               FROM consultas c JOIN marcacoes m ON m.id = c.marcacao_id
              WHERE c.id = ? FOR UPDATE'
        );
        $st->execute([$consultaId]);
        $c = $st->fetch();

        if (!$c || (int)$c['utilizador_id'] !== $utilizadorId) {
            throw new RuntimeException('Consulta não encontrada.');
        }
        if (!in_array($c['estado'], ['pendente', 'confirmada'], true)) {
            throw new RuntimeException('Esta consulta já não pode ser cancelada.');
        }
        [$permitido, $horas] = regra_antecedencia($pdo, $c['inicio']);
        if (!$permitido && !$ignorarAntecedencia) {
            throw new RuntimeException('Já não é possível cancelar online: exigimos ' . $horas . ' horas de antecedência. Contacta a clínica.');
        }
        $marcacaoId = (int)$c['marcacao_id'];

        $pdo->prepare("UPDATE consultas SET estado = 'cancelada' WHERE id = ?")->execute([$consultaId]);
        $pdo->prepare('DELETE FROM horarios_ocupados WHERE consulta_id = ?')->execute([$consultaId]);

        $st = $pdo->prepare('SELECT COALESCE(SUM(preco_total), 0) FROM consultas WHERE marcacao_id = ?');
        $st->execute([$marcacaoId]);
        $somaTotal = (float)$st->fetchColumn();
        $parte = $somaTotal > 0 ? min(1.0, max(0.0, (float)$c['preco_total'] / $somaTotal)) : 0.0;

        $reembolso = 0.0;
        $st = $pdo->prepare("SELECT id, valor, valor_reembolsado, estado FROM pagamentos WHERE marcacao_id = ? AND estado IN ('pago','reembolsado','parcialmente_reembolsado') ORDER BY id ASC FOR UPDATE");
        $st->execute([$marcacaoId]);
        $pagos = $st->fetchAll();
        $netPago = 0.0;
        foreach ($pagos as $p) {
            $netPago += max(0.0, (float)$p['valor'] - (float)$p['valor_reembolsado']);
        }
        $alvo = round($netPago * $parte, 2);
        $resta = $alvo;
        foreach ($pagos as $p) {
            if ($resta <= 0.0) { break; }
            $disponivel = round(max(0.0, (float)$p['valor'] - (float)$p['valor_reembolsado']), 2);
            if ($disponivel <= 0.0) { continue; }
            $aplicar = min($disponivel, $resta);
            $novoTotal = round((float)$p['valor_reembolsado'] + $aplicar, 2);
            $novoEstado = $novoTotal >= round((float)$p['valor'], 2) - 0.0001 ? 'reembolsado' : 'parcialmente_reembolsado';
            $pdo->prepare('UPDATE pagamentos SET valor_reembolsado = ?, estado = ?, reembolsado_em = NOW() WHERE id = ?')
                ->execute([$novoTotal, $novoEstado, $p['id']]);
            $reembolso = round($reembolso + $aplicar, 2);
            $resta = round($resta - $aplicar, 2);
        }

        $pdo->prepare("UPDATE pagamentos SET valor = GREATEST(0, ROUND(valor * ?, 2)) WHERE marcacao_id = ? AND estado IN ('pendente','em_processamento')")
            ->execute([1 - $parte, $marcacaoId]);

        $pdo->prepare('UPDATE marcacoes SET total_previsto = GREATEST(0, ROUND(total_previsto - ?, 2)) WHERE id = ?')
            ->execute([(float)$c['preco_total'], $marcacaoId]);

        $st = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE marcacao_id = ? AND estado <> 'cancelada'");
        $st->execute([$marcacaoId]);
        if ((int)$st->fetchColumn() === 0) {
            $pdo->prepare("UPDATE marcacoes SET estado = 'cancelada', motivo_cancelamento = 'Cancelada pelo utilizador.', cancelada_em = NOW() WHERE id = ?")
                ->execute([$marcacaoId]);
            $pdo->prepare("UPDATE pagamentos SET estado = 'cancelado', cancelado_em = NOW() WHERE marcacao_id = ? AND estado IN ('pendente','em_processamento')")
                ->execute([$marcacaoId]);
        }

        $pdo->prepare('DELETE FROM pedidos_alteracao WHERE consulta_id = ? AND estado = \'pendente\'')->execute([$consultaId]);

        criar_notificacao($pdo, $utilizadorId, $marcacaoId, 'cancelamento', 'Consulta cancelada',
            'Cancelaste uma consulta da marcação ' . $c['referencia'] . '.'
            . ($reembolso > 0 ? ' Reembolso registado: ' . formatar_preco($reembolso) . '.' : ''));

        $pdo->commit();
        return $reembolso;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function estilos_conta(): string
{
    return <<<'HTML'
<style>
    .separadores { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.5rem; }
    .nao-lida { font-weight: 700; }
    .consulta-cartao { display: grid; gap: .25rem; }
    .consulta-cartao .acoes { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem; }
    .campo input[type="datetime-local"], .campo input[type="date"] {
        padding: .65rem .8rem; font-size: 1rem; font-family: inherit;
        border: 1px solid var(--borda); border-radius: 8px; background: var(--fundo); color: var(--texto);
    }
</style>
HTML;
}

function submenu_conta(string $ativo, int $naoLidas = 0): string
{
    $itens = [
        'inicio'       => ['area-pessoal.php',      'Resumo'],
        'marcacoes'    => ['minhas-marcacoes.php',  'Marcações'],
        'pagamentos'   => ['meus-pagamentos.php',   'Pagamentos e dívidas'],
        'espera'       => ['lista-espera.php',      'Lista de espera'],
        'seguros'      => ['seguros.php',           'Seguros e benefícios'],
        'notificacoes' => ['notificacoes.php',      'Notificações' . ($naoLidas > 0 ? ' (' . $naoLidas . ')' : '')],
        'perfil'       => ['perfil.php',            'Perfil e familiares'],
    ];
    $html = '<nav class="separadores" aria-label="Menu da conta">';
    foreach ($itens as $chave => [$url, $texto]) {
        $html .= '<a class="botao' . ($chave === $ativo ? '' : ' botao-secundario') . '" href="' . e($url) . '"'
               . ($chave === $ativo ? ' aria-current="page"' : '') . '>' . e($texto) . '</a>';
    }
    return $html . '</nav>';
}