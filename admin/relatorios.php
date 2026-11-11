<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/marcacao.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin', 'rececao']);
$pdo = db();

limpar_reservas_expiradas($pdo);
$agora = agora_bd($pdo);

$de  = (string)($_GET['de']  ?? $agora->modify('-30 days')->format('Y-m-d'));
$ate = (string)($_GET['ate'] ?? $agora->format('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de))  { $de  = $agora->modify('-30 days')->format('Y-m-d'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $ate = $agora->format('Y-m-d'); }
if ($ate < $de) { $ate = $de; }
$ini = $de . ' 00:00:00';
$fim = date('Y-m-d', strtotime($ate . ' +1 day')) . ' 00:00:00';

// ---------- Números principais ----------

$st = $pdo->prepare('SELECT COUNT(*) FROM consultas WHERE inicio >= ? AND inicio < ?');
$st->execute([$ini, $fim]);
$totalConsultas = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE inicio >= ? AND inicio < ? AND estado = 'concluida'");
$st->execute([$ini, $fim]);
$totalConcluidas = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE inicio >= ? AND inicio < ? AND estado = 'cancelada'");
$st->execute([$ini, $fim]);
$totalCanceladas = (int)$st->fetchColumn();

// BUGFIX (bug 1): receita líquida coerente com financeiro.php e pagamentos.php.
// Antes só contava estado = 'pago' → pagamentos parcialmente reembolsados ficavam
// excluídos do SUM (receita subestimada). Passa a usar CASE + GREATEST + COALESCE de datas.
$st = $pdo->prepare(
    "SELECT COALESCE(SUM(
        CASE WHEN estado IN ('pago','reembolsado','parcialmente_reembolsado')
             THEN GREATEST(0, valor - valor_reembolsado)
             ELSE 0 END
    ), 0)
       FROM pagamentos
      WHERE COALESCE(data_pagamento, pago_em, criado_em) >= ?
        AND COALESCE(data_pagamento, pago_em, criado_em) <  ?"
);
$st->execute([$ini, $fim]);
$receita = (float)$st->fetchColumn();

$st = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE estado IN ('pendente','em_processamento') AND criado_em >= ? AND criado_em < ?");
$st->execute([$ini, $fim]);
$pendente = (float)$st->fetchColumn();

// ---------- Consultas por estado ----------
$st = $pdo->prepare("SELECT estado, COUNT(*) AS n FROM consultas WHERE inicio >= ? AND inicio < ? GROUP BY estado");
$st->execute([$ini, $fim]);
$porEstado = [];
foreach ($st as $r) { $porEstado[$r['estado']] = (int)$r['n']; }

// ---------- Consultas por dentista ----------
$st = $pdo->prepare(
    'SELECT d.id, d.nome_completo,
            COUNT(c.id) AS total,
            SUM(c.estado = \'concluida\') AS concluidas,
            SUM(c.estado = \'cancelada\') AS canceladas
       FROM dentistas d
       LEFT JOIN consultas c ON c.dentista_id = d.id AND c.inicio >= ? AND c.inicio < ?
      WHERE d.ativo = 1
      GROUP BY d.id, d.nome_completo
      ORDER BY total DESC'
);
$st->execute([$ini, $fim]);
$porDentista = $st->fetchAll();

// ---------- Tratamentos mais procurados ----------
// BUGFIX (bug 2): excluir consultas canceladas da receita_prevista
// (coerente com financeiro.php que já filtrava; antes inflava).
$st = $pdo->prepare(
    'SELECT t.id, t.nome, COUNT(ct.id) AS total, SUM(ct.preco_final) AS receita_prevista
       FROM consultas_tratamentos ct
       JOIN consultas c ON c.id = ct.consulta_id
       JOIN tratamentos t ON t.id = ct.tratamento_id
      WHERE c.inicio >= ? AND c.inicio < ?
        AND c.estado <> \'cancelada\'
      GROUP BY t.id, t.nome
      ORDER BY total DESC
      LIMIT 10'
);
$st->execute([$ini, $fim]);
$topTratamentos = $st->fetchAll();

// ---------- Top pacientes (mais consultas) ----------
$st = $pdo->prepare(
    'SELECT p.id, p.nome_completo, COUNT(c.id) AS total
       FROM pacientes p
       JOIN consultas c ON c.paciente_id = p.id AND c.inicio >= ? AND c.inicio < ?
      GROUP BY p.id, p.nome_completo
      ORDER BY total DESC
      LIMIT 10'
);
$st->execute([$ini, $fim]);
$topPacientes = $st->fetchAll();

// ---------- Novos utilizadores por mês (últimos 6 meses) ----------
$st = $pdo->prepare(
    "SELECT DATE_FORMAT(criado_em, '%Y-%m') AS mes, COUNT(*) AS n
       FROM utilizadores
      WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      GROUP BY mes
      ORDER BY mes"
);
$st->execute();
$novosUsers = $st->fetchAll();

$tituloPagina = 'Relatórios';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Relatórios</h1>

<form method="get" action="admin/relatorios.php" class="filtros-gestao">
    <div class="campo">
        <label for="de">De</label>
        <input type="date" id="de" name="de" value="<?= e($de) ?>">
    </div>
    <div class="campo">
        <label for="ate">Até</label>
        <input type="date" id="ate" name="ate" value="<?= e($ate) ?>">
    </div>
    <div class="campo"><button type="submit" class="botao">Atualizar</button></div>
    <div class="campo">
        <a class="botao botao-secundario" href="admin/relatorios.php?de=<?= e($agora->format('Y-m-d')) ?>&ate=<?= e($agora->format('Y-m-d')) ?>">Hoje</a>
        <a class="botao botao-secundario" href="admin/relatorios.php?de=<?= e($agora->modify('-30 days')->format('Y-m-d')) ?>&ate=<?= e($agora->format('Y-m-d')) ?>">Últimos 30 dias</a>
    </div>
</form>

<section class="grelha seccao">
    <div class="cartao texto-centro">
        <p class="numero"><?= $totalConsultas ?></p>
        <p class="ajuda">Consultas no período</p>
    </div>
    <div class="cartao texto-centro">
        <p class="numero"><?= $totalConcluidas ?></p>
        <p class="ajuda">Concluídas</p>
    </div>
    <div class="cartao texto-centro">
        <p class="numero"><?= $totalCanceladas ?></p>
        <p class="ajuda">Canceladas</p>
    </div>
    <div class="cartao texto-centro">
        <p class="numero"><?= e(formatar_preco($receita)) ?></p>
        <p class="ajuda">Receita líquida</p>
    </div>
    <div class="cartao texto-centro">
        <p class="numero"><?= e(formatar_preco($pendente)) ?></p>
        <p class="ajuda">Por receber</p>
    </div>
</section>

<section class="seccao">
    <h2>Consultas por estado</h2>
    <?php if ($porEstado === []): ?>
        <div class="alerta alerta-info">Sem consultas neste período.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Estado</th><th>Nº</th></tr></thead>
                <tbody>
                <?php foreach ($porEstado as $estado => $n): ?>
                    <tr>
                        <td><?= e(rotulo('marcacao', $estado)) ?></td>
                        <td><?= $n ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="seccao">
    <h2>Atividade por dentista</h2>
    <?php if (!$porDentista): ?>
        <div class="alerta alerta-info">Sem dados.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Dentista</th><th>Total</th><th>Concluídas</th><th>Canceladas</th></tr></thead>
                <tbody>
                <?php foreach ($porDentista as $d): ?>
                    <tr>
                        <td><?= e($d['nome_completo']) ?></td>
                        <td><?= (int)$d['total'] ?></td>
                        <td><?= (int)$d['concluidas'] ?></td>
                        <td><?= (int)$d['canceladas'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="seccao">
    <h2>Tratamentos mais procurados</h2>
    <?php if (!$topTratamentos): ?>
        <div class="alerta alerta-info">Sem dados.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Tratamento</th><th>Nº de consultas</th><th>Receita prevista</th></tr></thead>
                <tbody>
                <?php foreach ($topTratamentos as $t): ?>
                    <tr>
                        <td><?= e($t['nome']) ?></td>
                        <td><?= (int)$t['total'] ?></td>
                        <td><?= e(formatar_preco($t['receita_prevista'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="seccao">
    <h2>Pacientes com mais consultas</h2>
    <?php if (!$topPacientes): ?>
        <div class="alerta alerta-info">Sem dados.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Paciente</th><th>Consultas</th></tr></thead>
                <tbody>
                <?php foreach ($topPacientes as $p): ?>
                    <tr>
                        <td><?= e($p['nome_completo']) ?></td>
                        <td><?= (int)$p['total'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="seccao">
    <h2>Novos utilizadores (últimos 6 meses)</h2>
    <?php if (!$novosUsers): ?>
        <div class="alerta alerta-info">Sem dados.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Mês</th><th>Novos registos</th></tr></thead>
                <tbody>
                <?php foreach ($novosUsers as $n): ?>
                    <tr>
                        <td><?= e($n['mes']) ?></td>
                        <td><?= (int)$n['n'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>