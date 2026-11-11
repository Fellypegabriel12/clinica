<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';
require __DIR__ . '/../includes/stock.php';

$u   = exigir_login(['admin']);
$pdo = db();

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'criar') {
            $fornecedor = mb_substr(trim((string)($_POST['fornecedor'] ?? '')), 0, 120);
            $notas      = mb_substr(trim((string)($_POST['notas'] ?? '')), 0, 500);
            $linhas     = [];
            foreach ((array)($_POST['qtd'] ?? []) as $matId => $qtd) {
                $qtd = (int)$qtd;
                if ($qtd > 0) { $linhas[(int)$matId] = $qtd; }
            }
            $id = stock_criar_encomenda($pdo, $fornecedor ?: null, $notas ?: null, $linhas, $u['id']);
            registar_admin('stock_criar_encomenda', 'stock_encomendas', $id);
            flash_definir('sucesso', 'Encomenda criada.');
            redirecionar('admin/stock-encomendas.php?ver=' . $id);
        }

        if ($acao === 'estado') {
            $id    = (int)($_POST['id'] ?? 0);
            $novo  = (string)($_POST['estado'] ?? '');
            if (!in_array($novo, ['pendente', 'enviada', 'recebida', 'cancelada'], true)) {
                throw new RuntimeException('Estado inválido.');
            }
            if ($novo === 'recebida') {
                stock_receber_encomenda($pdo, $id, $u['id']);
                flash_definir('sucesso', 'Encomenda recebida e stock atualizado.');
            } else {
                $pdo->prepare('UPDATE stock_encomendas SET estado = ? WHERE id = ?')->execute([$novo, $id]);
                flash_definir('sucesso', 'Estado da encomenda atualizado.');
            }
            redirecionar('admin/stock-encomendas.php');
        }
    } catch (RuntimeException $e) {
        $erros[] = $e->getMessage();
    } catch (PDOException $e) {
        error_log('Erro encomendas: ' . $e->getMessage());
        $erros[] = 'Ocorreu um erro. Tenta novamente.';
    }
}

$verId = (int)($_GET['ver'] ?? 0);

if ($verId > 0) {
    // Detalhe de uma encomenda
    $st = $pdo->prepare('SELECT e.*, ut.nome_completo AS autor FROM stock_encomendas e LEFT JOIN utilizadores ut ON ut.id = e.criado_por WHERE e.id = ?');
    $st->execute([$verId]);
    $enc = $st->fetch();
    if (!$enc) {
        flash_definir('erro', 'Encomenda não encontrada.');
        redirecionar('admin/stock-encomendas.php');
    }
    $st = $pdo->prepare(
        'SELECT l.*, m.nome, m.unidade, m.referencia
           FROM stock_encomenda_linhas l
           JOIN stock_materiais m ON m.id = l.material_id
          WHERE l.encomenda_id = ? ORDER BY m.nome'
    );
    $st->execute([$verId]);
    $linhas = $st->fetchAll();

    $tituloPagina = 'Encomenda ' . $enc['referencia'];
    require __DIR__ . '/../includes/cabecalho.php';
    echo estilos_gestao();
    echo estilos_stock();
    ?>
    <p><a href="admin/stock-encomendas.php">← Encomendas</a></p>
    <h1>Encomenda <?= e($enc['referencia']) ?></h1>

    <section class="cartao seccao">
        <dl class="dados">
            <dt>Estado</dt><dd><span class="estado-etiqueta estado-stock-<?= e($enc['estado']) ?>"><?= e(ucfirst($enc['estado'])) ?></span></dd>
            <dt>Fornecedor</dt><dd><?= e($enc['fornecedor'] ?? '—') ?></dd>
            <dt>Total</dt><dd><?= e(formatar_preco((float)$enc['total'])) ?></dd>
            <dt>Criada</dt><dd><?= e((new DateTime($enc['criado_em']))->format('d/m/Y H:i')) ?> por <?= e($enc['autor'] ?? '—') ?></dd>
            <?php if ($enc['recebida_em']): ?><dt>Recebida</dt><dd><?= e((new DateTime($enc['recebida_em']))->format('d/m/Y H:i')) ?></dd><?php endif; ?>
            <?php if ($enc['notas']): ?><dt>Notas</dt><dd><?= e($enc['notas']) ?></dd><?php endif; ?>
        </dl>
    </section>

    <section class="cartao seccao">
        <h2>Linhas</h2>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Material</th><th>Qtd.</th><th>Preço unit.</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td><?= e($l['nome']) ?><?= $l['referencia'] ? ' <span class="ajuda">(' . e($l['referencia']) . ')</span>' : '' ?></td>
                        <td><?= (int)$l['quantidade'] ?> <?= e($l['unidade']) ?></td>
                        <td><?= $l['preco_unitario'] !== null ? e(formatar_preco((float)$l['preco_unitario'])) : '—' ?></td>
                        <td><?= $l['preco_unitario'] !== null ? e(formatar_preco((float)$l['preco_unitario'] * (int)$l['quantidade'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="3" scope="row">Total</th><th><?= e(formatar_preco((float)$enc['total'])) ?></th></tr></tfoot>
            </table>
        </div>
    </section>

    <?php if (in_array($enc['estado'], ['pendente', 'enviada'], true)): ?>
        <form method="post" action="admin/stock-encomendas.php" class="acoes">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="estado">
            <input type="hidden" name="id" value="<?= (int)$enc['id'] ?>">
            <?php if ($enc['estado'] === 'pendente'): ?>
                <button type="submit" name="estado" value="enviada" class="botao botao-secundario">Marcar como enviada</button>
            <?php endif; ?>
            <button type="submit" name="estado" value="recebida" class="botao">Marcar como recebida (dar entrada)</button>
            <button type="submit" name="estado" value="cancelada" class="botao botao-secundario"
                    onclick="return confirm('Cancelar esta encomenda?');">Cancelar encomenda</button>
        </form>
    <?php endif; ?>
    <?php
    require __DIR__ . '/../includes/rodape.php';
    exit;
}

// ---------- Lista + criação ----------
$encomendas = $pdo->query(
    'SELECT e.*, ut.nome_completo AS autor,
            (SELECT COUNT(*) FROM stock_encomenda_linhas l WHERE l.encomenda_id = e.id) AS n_linhas
       FROM stock_encomendas e
       LEFT JOIN utilizadores ut ON ut.id = e.criado_por
      ORDER BY e.criado_em DESC LIMIT 200'
)->fetchAll();

$materiais = $pdo->query(
    'SELECT id, nome, referencia, unidade, quantidade, stock_minimo, preco_unitario
       FROM stock_materiais WHERE ativo = 1 ORDER BY nome'
)->fetchAll();

$novo = isset($_GET['novo']);
$sugeridos = [];
foreach (stock_materiais_alerta($pdo) as $a) {
    $qtd = max((int)$a['stock_minimo'] - (int)$a['quantidade'], 1);
    $sugeridos[(int)$a['id']] = $qtd;
}

$tituloPagina = 'Encomendas de stock';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
echo estilos_stock();
?>

<p><a href="admin/index.php">← Painel</a> · <a href="admin/stock.php">← Stock</a></p>
<h1>Encomendas de stock</h1>

<?php foreach ($erros as $erro): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endforeach; ?>

<?php if ($encomendas): ?>
    <section class="seccao">
        <h2>Histórico</h2>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Referência</th><th>Fornecedor</th><th>Linhas</th><th>Total</th><th>Estado</th><th>Data</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($encomendas as $e): ?>
                    <tr>
                        <td><strong><?= e($e['referencia']) ?></strong></td>
                        <td><?= e($e['fornecedor'] ?? '—') ?></td>
                        <td><?= (int)$e['n_linhas'] ?></td>
                        <td><?= e(formatar_preco((float)$e['total'])) ?></td>
                        <td><span class="estado-etiqueta estado-stock-<?= e($e['estado']) ?>"><?= e(ucfirst($e['estado'])) ?></span></td>
                        <td><?= e((new DateTime($e['criado_em']))->format('d/m/Y H:i')) ?></td>
                        <td><a class="botao botao-secundario" href="admin/stock-encomendas.php?ver=<?= (int)$e['id'] ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="cartao seccao" id="nova-encomenda">
    <h2><?= $novo ? 'Nova encomenda' : 'Criar encomenda' ?></h2>
    <p class="ajuda">Indica as quantidades dos materiais que queres encomendar. Deixa a zero para ignorar.</p>

    <form method="post" action="admin/stock-encomendas.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="criar">

        <div class="campo">
            <label for="fornecedor">Fornecedor (opcional)</label>
            <input type="text" id="fornecedor" name="fornecedor" maxlength="120">
        </div>
        <div class="campo">
            <label for="notas">Notas (opcional)</label>
            <input type="text" id="notas" name="notas" maxlength="500">
        </div>

        <div class="tabela-scroll">
            <table class="tabela stock-tabela">
                <thead><tr><th>Material</th><th>Stock</th><th>Mín.</th><th>Preço unit.</th><th>Quantidade a encomendar</th></tr></thead>
                <tbody>
                <?php foreach ($materiais as $m): $sug = $sugeridos[(int)$m['id']] ?? 0; ?>
                    <tr>
                        <td>
                            <strong><?= e($m['nome']) ?></strong>
                            <?= $m['referencia'] ? '<br><span class="ajuda">Ref. ' . e($m['referencia']) . '</span>' : '' ?>
                            <br><span class="ajuda"><?= e($m['unidade']) ?></span>
                        </td>
                        <td><?= (int)$m['quantidade'] ?></td>
                        <td><?= (int)$m['stock_minimo'] ?></td>
                        <td><?= $m['preco_unitario'] !== null ? e(formatar_preco((float)$m['preco_unitario'])) : '—' ?></td>
                        <td>
                            <input type="number" name="qtd[<?= (int)$m['id'] ?>]" min="0" value="<?= $sug > 0 ? $sug : 0 ?>"
                                   aria-label="Quantidade de <?= e($m['nome']) ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p><button type="submit" class="botao">Criar encomenda</button></p>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>