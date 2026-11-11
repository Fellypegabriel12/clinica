<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';

$u = exigir_login(['admin']);
$pdo = db();

$acao = trim((string)($_GET['acao'] ?? ''));
$entidade = trim((string)($_GET['entidade'] ?? ''));
$limite = max(20, min(200, (int)($_GET['limite'] ?? 100)));

$where = [];
$params = [];
if ($acao !== '') { $where[] = 'r.acao LIKE ?'; $params[] = '%' . $acao . '%'; }
if ($entidade !== '') { $where[] = 'r.entidade = ?'; $params[] = $entidade; }
$sql = 'SELECT r.*, u.nome_completo, u.email FROM registo_admin r LEFT JOIN utilizadores u ON u.id = r.utilizador_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY r.id DESC LIMIT ' . $limite;
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$tituloPagina = 'Auditoria e rastreabilidade';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>
<p><a href="admin/index.php">← Painel</a></p>
<h1>Auditoria e rastreabilidade</h1>
<p class="ajuda">Registo de operações administrativas e alterações sensíveis (quem, quando, ação, entidade, detalhes e, quando disponível, antes/depois/motivo). Acesso restrito a administradores.</p>

<form method="get" action="admin/auditoria.php" class="filtros-gestao" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:end;">
    <label>Ação <input type="text" name="acao" value="<?= e($acao) ?>" maxlength="80"></label>
    <label>Entidade <input type="text" name="entidade" value="<?= e($entidade) ?>" maxlength="60"></label>
    <label>Limite <input type="number" name="limite" min="20" max="200" value="<?= (int)$limite ?>"></label>
    <button type="submit" class="botao botao-secundario">Filtrar</button>
</form>

<div class="tabela-scroll">
<table class="tabela">
<thead><tr><th>Quando</th><th>Utilizador</th><th>Ação</th><th>Entidade</th><th>Detalhes</th><th>Antes</th><th>Depois</th><th>Motivo</th><th>IP</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
    <td><?= e($r['criado_em'] ?? '') ?></td>
    <td><?= e($r['nome_completo'] ?? ('#' . ($r['utilizador_id'] ?? ''))) ?><br><span class="ajuda"><?= e($r['email'] ?? '') ?></span></td>
    <td><?= e($r['acao'] ?? '') ?></td>
    <td><?= e((string)($r['entidade'] ?? '')) ?><?= !empty($r['entidade_id']) ? ' #' . (int)$r['entidade_id'] : '' ?></td>
    <td style="max-width:260px;white-space:pre-wrap;"><?= e((string)($r['detalhes'] ?? '')) ?></td>
    <td style="max-width:220px;"><pre style="white-space:pre-wrap;margin:0;font-size:.8rem;"><?= e((string)($r['antes_json'] ?? '')) ?></pre></td>
    <td style="max-width:220px;"><pre style="white-space:pre-wrap;margin:0;font-size:.8rem;"><?= e((string)($r['depois_json'] ?? '')) ?></pre></td>
    <td><?= e((string)($r['motivo'] ?? '')) ?></td>
    <td><?= e((string)($r['ip'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="9" class="ajuda">Sem registos para os filtros indicados.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/rodape.php'; ?>
