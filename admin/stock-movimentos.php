<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';
require __DIR__ . '/../includes/stock.php';

$u   = exigir_login(['admin']);
$pdo = db();

$materialId = (int)($_GET['material'] ?? 0);
$material   = null;

if ($materialId > 0) {
    $st = $pdo->prepare('SELECT id, nome, unidade, quantidade FROM stock_materiais WHERE id = ?');
    $st->execute([$materialId]);
    $material = $st->fetch();
    if (!$material) {
        flash_definir('erro', 'Material não encontrado.');
        redirecionar('admin/stock.php');
    }
}

$where  = '';
$params = [];
if ($material !== null) {
    $where  = ' WHERE mv.material_id = ?';
    $params = [$materialId];
}

$movimentos = $pdo->prepare(
    'SELECT mv.*, m.nome AS material, m.unidade, ut.nome_completo AS utilizador
       FROM stock_movimentos mv
       JOIN stock_materiais m ON m.id = mv.material_id
       LEFT JOIN utilizadores ut ON ut.id = mv.utilizador_id' . $where . '
      ORDER BY mv.criado_em DESC LIMIT 500'
);
$movimentos->execute($params);
$movimentos = $movimentos->fetchAll();

$tituloPagina = 'Movimentos de stock';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
echo estilos_stock();
?>

<p><a href="admin/index.php">← Painel</a> · <a href="admin/stock.php">← Stock</a></p>
<h1><?= $material ? 'Histórico: ' . e($material['nome']) : 'Histórico de movimentos' ?></h1>

<?php if ($material): ?>
    <p class="ajuda">Stock atual: <strong><?= (int)$material['quantidade'] ?> <?= e($material['unidade']) ?></strong></p>
<?php endif; ?>

<?php if (!$movimentos): ?>
    <div class="alerta alerta-info">Ainda não há movimentos.</div>
<?php else: ?>
    <div class="tabela-scroll">
        <table class="tabela">
            <thead><tr><th>Data</th><th>Material</th><th>Tipo</th><th>Qtd.</th><th>Antes</th><th>Depois</th><th>Motivo</th><th>Utilizador</th></tr></thead>
            <tbody>
            <?php foreach ($movimentos as $mv): ?>
                <tr>
                    <td><?= e((new DateTime($mv['criado_em']))->format('d/m/Y H:i')) ?></td>
                    <td><?= e($mv['material']) ?></td>
                    <td>
                        <span class="estado-etiqueta estado-stock-<?= $mv['tipo'] === 'entrada' ? 'ok' : ($mv['tipo'] === 'ajuste' ? 'pendente' : 'esgotado') ?>">
                            <?= e(ucfirst($mv['tipo'])) ?>
                        </span>
                    </td>
                    <td><?= (int)$mv['quantidade'] ?> <?= e($mv['unidade']) ?></td>
                    <td><?= (int)$mv['quantidade_antes'] ?></td>
                    <td><?= (int)$mv['quantidade_depois'] ?></td>
                    <td><?= e($mv['motivo'] ?? '—') ?></td>
                    <td><?= e($mv['utilizador'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>