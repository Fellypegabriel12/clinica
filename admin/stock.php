<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';
require __DIR__ . '/../includes/stock.php';

$u   = exigir_login(['admin']);
$pdo = db();

$erros = [];

// ---------- Ações ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'movimento') {
            $matId = (int)($_POST['material'] ?? 0);
            $tipo  = (string)($_POST['tipo'] ?? '');
            $qtd   = (int)($_POST['quantidade'] ?? 0);
            $motivo = mb_substr(trim((string)($_POST['motivo'] ?? '')), 0, 255);

            $depois = stock_registar_movimento($pdo, $matId, $tipo, $qtd, $motivo ?: null, $u['id']);
            registar_admin('stock_movimento', 'stock_materiais', $matId, $tipo . ' ' . $qtd);
            flash_definir('sucesso', 'Movimento registado. Stock atual: ' . $depois . '.');
            redirecionar('admin/stock.php');
        }

        if ($acao === 'novo_material') {
            $nome   = trim((string)($_POST['nome'] ?? ''));
            $ref    = trim((string)($_POST['referencia'] ?? ''));
            $catId  = (int)($_POST['categoria'] ?? 0) ?: null;
            $unid   = trim((string)($_POST['unidade'] ?? 'un'));
            $qtd    = max(0, (int)($_POST['quantidade'] ?? 0));
            $min    = max(0, (int)($_POST['stock_minimo'] ?? 0));
            $max    = ($_POST['stock_maximo'] ?? '') !== '' ? max(0, (int)$_POST['stock_maximo']) : null;
            $preco  = ($_POST['preco_unitario'] ?? '') !== '' ? (float)str_replace(',', '.', (string)$_POST['preco_unitario']) : null;
            $forn   = trim((string)($_POST['fornecedor'] ?? ''));
            $val    = trim((string)($_POST['validade'] ?? '')) ?: null;
            $desc   = mb_substr(trim((string)($_POST['descricao'] ?? '')), 0, 255);

            if (mb_strlen($nome) < 2 || mb_strlen($nome) > 150) {
                $erros[] = 'O nome do material deve ter entre 2 e 150 caracteres.';
            }
            if ($ref !== '' && mb_strlen($ref) > 60) {
                $erros[] = 'A referência é demasiado longa.';
            }
            if ($preco !== null && $preco < 0) {
                $erros[] = 'O preço não pode ser negativo.';
            }
            if ($val !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                $erros[] = 'Data de validade inválida.';
            }

            if ($erros === []) {
                $pdo->prepare(
                    'INSERT INTO stock_materiais
                        (categoria_id, referencia, nome, descricao, unidade, quantidade, stock_minimo, stock_maximo,
                         preco_unitario, fornecedor, validade)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $catId, $ref ?: null, $nome, $desc ?: null, $unid ?: 'un',
                    $qtd, $min, $max, $preco, $forn ?: null, $val,
                ]);
                registar_admin('stock_novo_material', 'stock_materiais', (int)$pdo->lastInsertId(), $nome);
                flash_definir('sucesso', 'Material criado.');
                redirecionar('admin/stock.php');
            }
        }

        if ($acao === 'alternar_ativo') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE stock_materiais SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
            registar_admin('stock_alternar_ativo', 'stock_materiais', $id);
            flash_definir('sucesso', 'Estado do material atualizado.');
            redirecionar('admin/stock.php');
        }
    } catch (RuntimeException $e) {
        $erros[] = $e->getMessage();
    } catch (PDOException $e) {
        error_log('Erro stock: ' . $e->getMessage());
        $erros[] = 'Ocorreu um erro. Tenta novamente.';
    }
}

// ---------- Filtros ----------
$q         = trim((string)($_GET['q'] ?? ''));
$catFiltro = (int)($_GET['categoria'] ?? 0);
$estFiltro = (string)($_GET['estado'] ?? 'todos');
if (!in_array($estFiltro, ['todos', 'baixo', 'esgotado', 'ok'], true)) { $estFiltro = 'todos'; }

$where  = ['m.ativo = 1'];
$params = [];

if ($q !== '') {
    $where[] = '(m.nome LIKE ? OR m.referencia LIKE ? OR m.fornecedor LIKE ?)';
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($catFiltro > 0) {
    $where[] = 'm.categoria_id = ?';
    $params[] = $catFiltro;
}
if ($estFiltro === 'esgotado') { $where[] = 'm.quantidade <= 0'; }
if ($estFiltro === 'baixo')    { $where[] = 'm.quantidade > 0 AND m.quantidade <= m.stock_minimo'; }
if ($estFiltro === 'ok')       { $where[] = 'm.quantidade > m.stock_minimo'; }

$sql = 'SELECT m.*, c.nome AS categoria
          FROM stock_materiais m
          LEFT JOIN stock_categorias c ON c.id = m.categoria_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY (m.quantidade = 0) DESC, (m.quantidade <= m.stock_minimo) DESC, m.nome';
$st = $pdo->prepare($sql);
$st->execute($params);
$materiais = $st->fetchAll();

$categorias = $pdo->query('SELECT id, nome FROM stock_categorias WHERE ativo = 1 ORDER BY nome')->fetchAll();

$alertas = stock_materiais_alerta($pdo);

$tituloPagina = 'Stock de materiais';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
echo estilos_stock();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Stock de materiais</h1>

<div class="stock-resumo">
    <div class="cartao"><p class="numero"><?= count($materiais) ?></p><p class="ajuda">Materiais ativos</p></div>
    <div class="cartao"><p class="numero"><?= count(array_filter($alertas, fn($a) => (int)$a['quantidade'] > 0)) ?></p><p class="ajuda">Com stock baixo</p></div>
    <div class="cartao"><p class="numero"><?= count(array_filter($alertas, fn($a) => (int)$a['quantidade'] === 0)) ?></p><p class="ajuda">Esgotados</p></div>
    <div class="cartao"><p class="numero"><?= count($alertas) ?></p><p class="ajuda">Alertas totais</p></div>
</div>

<?php if ($alertas): ?>
    <section class="cartao seccao">
        <h2>⚠ Alertas de stock</h2>
        <div class="grelha grelha-larga">
            <?php foreach ($alertas as $a): ?>
                <article class="cartao stock-alerta-linha <?= (int)$a['quantidade'] > 0 ? 'baixo' : '' ?>">
                    <strong><?= e($a['nome']) ?></strong>
                    <span class="ajuda"><?= e($a['categoria'] ?? 'Sem categoria') ?> · <?= e($a['unidade']) ?></span>
                    <span>
                        <?= stock_etiqueta((int)$a['quantidade'], (int)$a['stock_minimo']) ?>
                        Restam <strong><?= (int)$a['quantidade'] ?></strong>
                        (mínimo: <?= (int)$a['stock_minimo'] ?>)
                    </span>
                    <form method="post" action="admin/stock.php" class="acoes" style="margin-top:.5rem;">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="movimento">
                        <input type="hidden" name="material" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="tipo" value="entrada">
                        <input type="hidden" name="motivo" value="Reposição rápida">
                        <label class="ajuda" for="q-<?= (int)$a['id'] ?>">Repor</label>
                        <input type="number" id="q-<?= (int)$a['id'] ?>" name="quantidade" min="1" value="<?= max(1, (int)$a['stock_minimo']) ?>" required>
                        <button type="submit" class="botao botao-secundario">Entrada</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <p><a class="botao" href="admin/stock-encomendas.php?novo=1">Criar encomenda de reposição →</a></p>
    </section>
<?php endif; ?>

<?php foreach ($erros as $erro): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endforeach; ?>

<form method="get" action="admin/stock.php" class="stock-filtros">
    <div class="campo">
        <label for="q">Procurar</label>
        <input type="text" id="q" name="q" maxlength="100" value="<?= e($q) ?>" placeholder="nome, referência, fornecedor">
    </div>
    <div class="campo">
        <label for="categoria">Categoria</label>
        <select id="categoria" name="categoria">
            <option value="0">Todas</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $catFiltro === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <?php foreach (['todos' => 'Todos', 'baixo' => 'Stock baixo', 'esgotado' => 'Esgotados', 'ok' => 'OK'] as $v => $t): ?>
                <option value="<?= e($v) ?>" <?= $estFiltro === $v ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo"><button type="submit" class="botao">Filtrar</button></div>
</form>

<?php if (!$materiais): ?>
    <div class="alerta alerta-info">Não há materiais com estes filtros.</div>
<?php else: ?>
    <div class="tabela-scroll">
        <table class="tabela stock-tabela">
            <thead>
                <tr><th>Material</th><th>Categoria</th><th>Quantidade</th><th>Mínimo</th><th>Estado</th><th>Movimento</th><th>Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ($materiais as $m): ?>
                <tr>
                    <td>
                        <strong><?= e($m['nome']) ?></strong>
                        <?php if ($m['referencia']): ?><br><span class="ajuda">Ref. <?= e($m['referencia']) ?></span><?php endif; ?>
                        <br><span class="ajuda">Unid.: <?= e($m['unidade']) ?><?= $m['fornecedor'] ? ' · ' . e($m['fornecedor']) : '' ?></span>
                    </td>
                    <td><?= e($m['categoria'] ?? '—') ?></td>
                    <td><strong><?= (int)$m['quantidade'] ?></strong></td>
                    <td><?= (int)$m['stock_minimo'] ?></td>
                    <td><?= stock_etiqueta((int)$m['quantidade'], (int)$m['stock_minimo']) ?></td>
                    <td>
                        <form method="post" action="admin/stock.php" class="form-inline" style="gap:.3rem;">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="movimento">
                            <input type="hidden" name="material" value="<?= (int)$m['id'] ?>">
                            <select name="tipo" aria-label="Tipo">
                                <option value="entrada">Entrada</option>
                                <option value="saida">Saída</option>
                                <option value="perda">Perda</option>
                                <option value="ajuste">Ajuste</option>
                            </select>
                            <input type="number" name="quantidade" min="1" placeholder="qtd" required>
                            <button type="submit" class="botao botao-secundario">Registar</button>
                        </form>
                    </td>
                    <td>
                        <a class="botao botao-secundario" href="admin/stock-movimentos.php?material=<?= (int)$m['id'] ?>">Histórico</a>
                        <form method="post" action="admin/stock.php" class="form-inline">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="alternar_ativo">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="botao botao-secundario" title="Desativar sem apagar o histórico">Desativar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<section class="cartao seccao">
    <h2>Novo material</h2>
    <form method="post" action="admin/stock.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="novo_material">
        <div class="campo">
            <label for="nome">Nome <span class="obrigatorio">*</span></label>
            <input type="text" id="nome" name="nome" required maxlength="150">
        </div>
        <div class="campo">
            <label for="referencia">Referência (SKU)</label>
            <input type="text" id="referencia" name="referencia" maxlength="60">
        </div>
        <div class="campo">
            <label for="categoria">Categoria</label>
            <select id="categoria" name="categoria">
                <option value="0">— nenhuma —</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="unidade">Unidade</label>
            <input type="text" id="unidade" name="unidade" maxlength="20" value="un">
        </div>
        <div class="campo">
            <label for="quantidade">Quantidade inicial</label>
            <input type="number" id="quantidade" name="quantidade" min="0" value="0">
        </div>
        <div class="campo">
            <label for="stock_minimo">Stock mínimo</label>
            <input type="number" id="stock_minimo" name="stock_minimo" min="0" value="0">
        </div>
        <div class="campo">
            <label for="stock_maximo">Stock máximo (opcional)</label>
            <input type="number" id="stock_maximo" name="stock_maximo" min="0">
        </div>
        <div class="campo">
            <label for="preco_unitario">Preço unitário (€)</label>
            <input type="text" id="preco_unitario" name="preco_unitario" maxlength="10" placeholder="ex.: 8.50">
        </div>
        <div class="campo">
            <label for="fornecedor">Fornecedor</label>
            <input type="text" id="fornecedor" name="fornecedor" maxlength="120">
        </div>
        <div class="campo">
            <label for="validade">Validade (opcional)</label>
            <input type="date" id="validade" name="validade">
        </div>
        <div class="campo">
            <label for="descricao">Descrição (opcional)</label>
            <input type="text" id="descricao" name="descricao" maxlength="255">
        </div>
        <button type="submit" class="botao">Criar material</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>