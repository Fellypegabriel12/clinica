<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';

$u   = exigir_login(['admin']);
$pdo = db();

function slugify(string $texto): string
{
    $texto = mb_strtolower(trim($texto));
    $texto = preg_replace('/[^\p{L}\p{N}]+/u', '-', $texto);
    return trim($texto, '-');
}

$erros = [];
$form  = ['id' => 0, 'titulo' => '', 'slug' => '', 'resumo' => '', 'conteudo' => '', 'categoria' => '', 'visivel' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'alternar_visivel') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE artigos SET visivel = 1 - visivel WHERE id = ?')->execute([$id]);
        flash_definir('sucesso', 'Visibilidade atualizada.');
        redirecionar('admin/artigos.php');
    }

    if ($acao === 'apagar') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM artigos WHERE id = ?')->execute([$id]);
        flash_definir('sucesso', 'Artigo apagado.');
        redirecionar('admin/artigos.php');
    }

    if ($acao === 'guardar') {
        $form['id']        = (int)($_POST['id'] ?? 0);
        $form['titulo']    = trim((string)($_POST['titulo'] ?? ''));
        $form['slug']      = trim((string)($_POST['slug'] ?? ''));
        $form['resumo']    = trim((string)($_POST['resumo'] ?? ''));
        $form['conteudo']  = trim((string)($_POST['conteudo'] ?? ''));
        $form['categoria'] = trim((string)($_POST['categoria'] ?? ''));
        $form['visivel']   = isset($_POST['visivel']) ? 1 : 0;

        if ($form['slug'] === '') {
            $form['slug'] = slugify($form['titulo']);
        } else {
            $form['slug'] = slugify($form['slug']);
        }

        if (mb_strlen($form['titulo']) < 5 || mb_strlen($form['titulo']) > 200) {
            $erros[] = 'O título deve ter entre 5 e 200 caracteres.';
        }
        if ($form['slug'] === '') {
            $erros[] = 'O slug (endereço do artigo) é inválido.';
        }
        if (mb_strlen($form['conteudo']) < 50) {
            $erros[] = 'O conteúdo é demasiado curto.';
        }
        if (mb_strlen($form['resumo']) > 300) {
            $erros[] = 'O resumo não pode ter mais de 300 caracteres.';
        }

        // slug único?
        if ($erros === []) {
            $st = $pdo->prepare('SELECT id FROM artigos WHERE slug = ? AND id <> ?');
            $st->execute([$form['slug'], $form['id']]);
            if ($st->fetchColumn()) {
                $erros[] = 'Já existe um artigo com esse endereço (slug).';
            }
        }

        if ($erros === []) {
            try {
                if ($form['id'] > 0) {
                    $pdo->prepare(
                        'UPDATE artigos SET titulo = ?, slug = ?, resumo = ?, conteudo = ?, categoria = ?, visivel = ?, atualizado_em = NOW()
                          WHERE id = ?'
                    )->execute([
                        $form['titulo'], $form['slug'], $form['resumo'] ?: null,
                        $form['conteudo'], $form['categoria'] ?: null,
                        $form['visivel'], $form['id'],
                    ]);
                    flash_definir('sucesso', 'Artigo atualizado.');
                } else {
                    $pdo->prepare(
                        'INSERT INTO artigos (titulo, slug, resumo, conteudo, categoria, visivel)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $form['titulo'], $form['slug'], $form['resumo'] ?: null,
                        $form['conteudo'], $form['categoria'] ?: null, $form['visivel'],
                    ]);
                    flash_definir('sucesso', 'Artigo criado.');
                }
                redirecionar('admin/artigos.php');
            } catch (PDOException $e) {
                error_log('Erro artigo: ' . $e->getMessage());
                $erros[] = 'Ocorreu um erro. Tenta novamente.';
            }
        }
    }
}

$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
if ($editarId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT id, titulo, slug, resumo, conteudo, categoria, visivel FROM artigos WHERE id = ?');
    $st->execute([$editarId]);
    if ($a = $st->fetch()) {
        $form = [
            'id' => (int)$a['id'], 'titulo' => $a['titulo'], 'slug' => $a['slug'],
            'resumo' => (string)$a['resumo'], 'conteudo' => $a['conteudo'],
            'categoria' => (string)$a['categoria'], 'visivel' => (int)$a['visivel'],
        ];
    }
}

$artigos = $pdo->query('SELECT id, titulo, slug, categoria, visivel, criado_em FROM artigos ORDER BY criado_em DESC')->fetchAll();

$tituloPagina = 'Artigos';
require __DIR__ . '/../includes/cabecalho.php';
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Artigos de informação ao paciente</h1>

<section class="seccao">
    <?php if (!$artigos): ?>
        <div class="alerta alerta-info">Ainda não há artigos.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Título</th><th>Categoria</th><th>Estado</th><th>Data</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($artigos as $a): ?>
                    <tr>
                        <td><?= e($a['titulo']) ?></td>
                        <td><?= e($a['categoria'] ?? '—') ?></td>
                        <td><?= (int)$a['visivel'] ? 'Visível' : '<span class="estado-inativo">Oculto</span>' ?></td>
                        <td><?= e((new DateTime($a['criado_em']))->format('d/m/Y')) ?></td>
                        <td>
                            <a class="botao botao-secundario" href="admin/artigos.php?editar=<?= (int)$a['id'] ?>">Editar</a>
                            <form method="post" action="admin/artigos.php" class="form-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="alternar_visivel">
                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="botao botao-secundario"><?= (int)$a['visivel'] ? 'Ocultar' : 'Mostrar' ?></button>
                            </form>
                            <form method="post" action="admin/artigos.php" class="form-inline" onsubmit="return confirm('Apagar este artigo?');">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="apagar">
                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="botao botao-secundario">Apagar</button>
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
    <h2><?= $form['id'] > 0 ? 'Editar artigo' : 'Novo artigo' ?></h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="admin/artigos.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="campo">
            <label for="titulo">Título <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="text" id="titulo" name="titulo" required maxlength="200" value="<?= e($form['titulo']) ?>">
        </div>
        <div class="campo">
            <label for="slug">Endereço (slug)</label>
            <input type="text" id="slug" name="slug" maxlength="200" value="<?= e($form['slug']) ?>">
            <p class="ajuda">Deixa em branco para gerar a partir do título.</p>
        </div>
        <div class="campo">
            <label for="categoria">Categoria</label>
            <input type="text" id="categoria" name="categoria" maxlength="60" value="<?= e($form['categoria']) ?>">
        </div>
        <div class="campo">
            <label for="resumo">Resumo (opcional, máx. 300)</label>
            <textarea id="resumo" name="resumo" rows="2" maxlength="300"><?= e($form['resumo']) ?></textarea>
        </div>
        <div class="campo">
            <label for="conteudo">Conteúdo <span class="obrigatorio" aria-hidden="true">*</span></label>
            <textarea id="conteudo" name="conteudo" required rows="12"><?= e($form['conteudo']) ?></textarea>
            <p class="ajuda">Texto simples. As quebras de linha são respeitadas.</p>
        </div>
        <div class="campo campo-checkbox">
            <input type="checkbox" id="visivel" name="visivel" value="1" <?= $form['visivel'] ? 'checked' : '' ?>>
            <label for="visivel">Visível no site</label>
        </div>

        <button type="submit" class="botao"><?= $form['id'] > 0 ? 'Guardar alterações' : 'Criar artigo' ?></button>
        <?php if ($form['id'] > 0): ?>
            <a class="botao botao-secundario" href="admin/artigos.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>