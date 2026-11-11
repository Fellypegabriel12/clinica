<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';
require __DIR__ . '/../includes/stock.php';

$u   = exigir_login(['admin']);
$pdo = db();

$erros = [];
$form  = ['id' => 0, 'nome' => '', 'descricao' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'guardar') {
        $form['id']        = (int)($_POST['id'] ?? 0);
        $form['nome']      = trim((string)($_POST['nome'] ?? ''));
        $form['descricao'] = mb_substr(trim((string)($_POST['descricao'] ?? '')), 0, 255);

        if (mb_strlen($form['nome']) < 2 || mb_strlen($form['nome']) > 80) {
            $erros[] = 'O nome da categoria deve ter entre 2 e 80 caracteres.';
        }
        if ($erros === []) {
            try {
                if ($form['id'] > 0) {
                    $pdo->prepare('UPDATE stock_categorias SET nome = ?, descricao = ? WHERE id = ?')
                        ->execute([$form['nome'], $form['descricao'] ?: null, $form['id']]);
                    flash_definir('sucesso', 'Categoria atualizada.');
                } else {
                    $pdo->prepare('INSERT INTO stock_categorias (nome, descricao) VALUES (?, ?)')
                        ->execute([$form['nome'], $form['descricao'] ?: null]);
                    flash_definir('sucesso', 'Categoria criada.');
                }
                redirecionar('admin/stock-categorias.php');
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    $erros[] = 'Já existe uma categoria com esse nome.';
                } else {
                    error_log('Erro categoria: ' . $e->getMessage());
                    $erros[] = 'Ocorreu um erro. Tenta novamente.';
                }
            }
        }
    }

    if ($acao === 'alternar') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE stock_categorias SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        flash_definir('sucesso', 'Estado da categoria atualizado.');
        redirecionar('admin/stock-categorias.php');
    }
}

$editarId = (int)($_GET['editar'] ?? 0);
if ($editarId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT id, nome, descricao FROM stock_categorias WHERE id = ?');
    $st->execute([$editarId]);
    if ($c = $st->fetch()) {
        $form = ['id' => (int)$c['id'], 'nome' => $c['nome'], 'descricao' => (string)$c['descricao']];
    }
}

$categorias = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM stock_materiais m WHERE m.categoria_id = c.id AND m.ativo = 1) AS n
       FROM stock_categorias c ORDER BY c.ativo DESC, c.nome'
)->fetchAll();

$tituloPagina = 'Categorias de stock';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
echo estilos_stock();
?>

<p><a href="admin/index.php">← Painel</a> · <a href="admin/stock.php">← Stock</a></p>
<h1>Categorias de stock</h1>

<?php foreach ($erros as $erro): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="seccao">
    <?php if (!$categorias): ?>
        <div class="alerta alerta-info">Ainda não há categorias.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Nome</th><th>Descrição</th><th>Materiais</th><th>Estado</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($categorias as $c): ?>
                    <tr>
                        <td><?= e($c['nome']) ?></td>
                        <td><?= e($c['descricao'] ?? '—') ?></td>
                        <td><?= (int)$c['n'] ?></td>
                        <td><?= (int)$c['ativo'] ? 'Ativa' : '<span class="estado-inativo">Desativada</span>' ?></td>
                        <td>
                            <a class="botao botao-secundario" href="admin/stock-categorias.php?editar=<?= (int)$c['id'] ?>">Editar</a>
                            <form method="post" action="admin/stock-categorias.php" class="form-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="alternar">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="botao botao-secundario"><?= (int)$c['ativo'] ? 'Desativar' : 'Ativar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="cartao">
    <h2><?= $form['id'] > 0 ? 'Editar categoria' : 'Nova categoria' ?></h2>
    <form method="post" action="admin/stock-categorias.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
        <div class="campo">
            <label for="nome">Nome <span class="obrigatorio">*</span></label>
            <input type="text" id="nome" name="nome" required maxlength="80" value="<?= e($form['nome']) ?>">
        </div>
        <div class="campo">
            <label for="descricao">Descrição</label>
            <input type="text" id="descricao" name="descricao" maxlength="255" value="<?= e($form['descricao']) ?>">
        </div>
        <button type="submit" class="botao"><?= $form['id'] > 0 ? 'Guardar' : 'Criar categoria' ?></button>
        <?php if ($form['id'] > 0): ?><a class="botao botao-secundario" href="admin/stock-categorias.php">Cancelar</a><?php endif; ?>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>