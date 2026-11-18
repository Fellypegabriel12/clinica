<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';
require __DIR__ . '/../includes/conteudo.php';

$u   = exigir_login(['admin']);
$pdo = db();

$grupos = conteudo_grupos();
$grupoAtual = (string)($_GET['grupo'] ?? 'hero');
if (!isset($grupos[$grupoAtual])) { $grupoAtual = 'hero'; }

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $valores = $_POST['c'] ?? [];

    try {
        $pdo->beginTransaction();
        $up = $pdo->prepare('UPDATE conteudo_site SET valor = ? WHERE chave = ?');
        foreach ($valores as $chave => $valor) {
            $chave = (string)$chave;
            if (!preg_match('/^[a-z0-9_]+$/', $chave)) { continue; }

            $st = $pdo->prepare('SELECT tipo FROM conteudo_site WHERE chave = ?');
            $st->execute([$chave]);
            $tipo = $st->fetchColumn();

            $valor = (string)$valor;
            if ($tipo === 'texto') {
                $valor = mb_substr(trim($valor), 0, 500);
            } elseif ($tipo === 'html') {
                // Allowlist de tags/atributos e validação de href (A9).
                $valor = sanitizar_html_editor($valor);
            } else {
                $valor = mb_substr(trim($valor), 0, 10000);
            }

            $up->execute([$valor, $chave]);
        }
        $pdo->commit();
        registar_admin('editar_conteudo_site', 'conteudo_site', null, 'grupo=' . $grupoAtual);
        flash_definir('sucesso', 'Conteúdo atualizado.');
        redirecionar('admin/editor-site.php?grupo=' . urlencode($grupoAtual));
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Erro editor: ' . $e->getMessage());
        $erros[] = 'Ocorreu um erro. Tenta novamente.';
    }
}

$campos = conteudo_por_grupo($pdo, $grupoAtual);

$tituloPagina = 'Editor do site';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Editor do site</h1>
<p class="ajuda">
    Aqui podes alterar todos os textos do site. As alterações aplicam-se imediatamente.
    Se apagares um campo, o site usa o texto por defeito.
</p>

<?php foreach ($erros as $erro): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endforeach; ?>

<nav class="separadores" aria-label="Grupos de conteúdo" style="flex-wrap:wrap;">
    <?php foreach ($grupos as $chave => $rotulo): ?>
        <a class="botao <?= $grupoAtual === $chave ? '' : 'botao-secundario' ?>"
           href="admin/editor-site.php?grupo=<?= e($chave) ?>"><?= e($rotulo) ?></a>
    <?php endforeach; ?>
</nav>

<section class="cartao seccao">
    <h2><?= e($grupos[$grupoAtual]) ?></h2>

    <?php if (!$campos): ?>
        <div class="alerta alerta-info">Ainda não há campos neste grupo. Corre o SQL inicial.</div>
    <?php else: ?>
        <form method="post" action="admin/editor-site.php?grupo=<?= e($grupoAtual) ?>" novalidate>
            <?= csrf_campo() ?>

            <?php foreach ($campos as $c): ?>
                <div class="campo">
                    <label for="c-<?= e($c['chave']) ?>">
                        <?= e($c['rotulo']) ?>
                        <span class="ajuda">(<?= e($c['chave']) ?>)</span>
                    </label>

                    <?php if ($c['tipo'] === 'textarea'): ?>
                        <textarea id="c-<?= e($c['chave']) ?>" name="c[<?= e($c['chave']) ?>]" rows="4"><?= e($c['valor']) ?></textarea>
                    <?php elseif ($c['tipo'] === 'html'): ?>
                        <textarea id="c-<?= e($c['chave']) ?>" name="c[<?= e($c['chave']) ?>]" rows="8"><?= e($c['valor']) ?></textarea>
                    <?php else: ?>
                        <input type="text" id="c-<?= e($c['chave']) ?>" name="c[<?= e($c['chave']) ?>]" maxlength="500" value="<?= e($c['valor']) ?>">
                    <?php endif; ?>

                    <?php if (!empty($c['ajuda'])): ?>
                        <p class="ajuda"><?= e($c['ajuda']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="botao">Guardar <?= e($grupos[$grupoAtual]) ?></button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>