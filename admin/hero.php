<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin']);
$pdo = db();

function processar_imagem_hero(array $f): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a imagem.');
    }
    if ($f['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException('A imagem não pode ter mais de 4 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($ext[$mime]) || @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('A imagem deve ser JPG, PNG ou WEBP.');
    }
    $pasta = __DIR__ . '/../uploads/hero';
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
        throw new RuntimeException('Não foi possível criar a pasta.');
    }
    $nome = bin2hex(random_bytes(16)) . '.' . $ext[$mime];
    if (!move_uploaded_file($f['tmp_name'], $pasta . '/' . $nome)) {
        throw new RuntimeException('Não foi possível guardar a imagem.');
    }
    return 'uploads/hero/' . $nome;
}

function apagar_imagem_hero(?string $caminho): void
{
    if ($caminho && str_starts_with($caminho, 'uploads/hero/')) {
        $f = __DIR__ . '/../' . $caminho;
        if (is_file($f)) { @unlink($f); }
    }
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'apagar') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT caminho FROM hero_imagens WHERE id = ?');
        $st->execute([$id]);
        $cam = $st->fetchColumn() ?: null;
        $pdo->prepare('DELETE FROM hero_imagens WHERE id = ?')->execute([$id]);
        apagar_imagem_hero($cam);
        registar_admin('apagar_hero', 'hero_imagens', $id);
        flash_definir('sucesso', 'Imagem removida.');
        redirecionar('admin/hero.php');
    }

    if ($acao === 'alternar_visivel') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE hero_imagens SET visivel = 1 - visivel WHERE id = ?')->execute([$id]);
        registar_admin('alternar_hero', 'hero_imagens', $id);
        flash_definir('sucesso', 'Visibilidade atualizada.');
        redirecionar('admin/hero.php');
    }

    if ($acao === 'guardar') {
        $legenda = mb_substr(trim((string)($_POST['legenda'] ?? '')), 0, 200);
        $ordem   = (int)($_POST['ordem'] ?? 0);
        $visivel = isset($_POST['visivel']) ? 1 : 0;

        try {
            $nova = processar_imagem_hero($_FILES['imagem'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
            if ($nova === null) {
                $erros[] = 'Escolhe uma imagem.';
            } else {
                $pdo->prepare(
                    'INSERT INTO hero_imagens (caminho, legenda, ordem, visivel) VALUES (?, ?, ?, ?)'
                )->execute([$nova, $legenda ?: null, $ordem, $visivel]);
                registar_admin('criar_hero', 'hero_imagens', (int)$pdo->lastInsertId());
                flash_definir('sucesso', 'Imagem adicionada.');
                redirecionar('admin/hero.php');
            }
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
        } catch (PDOException $e) {
            error_log('Erro hero: ' . $e->getMessage());
            $erros[] = 'Ocorreu um erro. Tenta novamente.';
        }
    }
}

$imagens = $pdo->query('SELECT * FROM hero_imagens ORDER BY ordem, id')->fetchAll();

$tituloPagina = 'Imagens do hero';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Imagens do hero (página inicial)</h1>
<p class="ajuda">
    Estas imagens aparecem em slideshow no topo da página inicial, atrás do botão "Marcar consulta".
    Recomenda-se fotos da clínica, da equipa e dos pacientes (com consentimento) — formato horizontal (ex.: 1600×900), otimizadas.
</p>

<?php foreach ($erros as $erro): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="seccao">
    <?php if (!$imagens): ?>
        <div class="alerta alerta-info">Ainda não há imagens. Adiciona a primeira no formulário abaixo.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Pré-visualização</th><th>Legenda</th><th>Ordem</th><th>Estado</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($imagens as $img): ?>
                    <tr>
                        <td><img src="<?= e($img['caminho']) ?>" alt="" style="max-width:120px;max-height:70px;border-radius:6px;"></td>
                        <td><?= e($img['legenda'] ?? '—') ?></td>
                        <td><?= (int)$img['ordem'] ?></td>
                        <td><?= (int)$img['visivel'] ? 'Visível' : '<span class="estado-inativo">Oculta</span>' ?></td>
                        <td>
                            <form method="post" action="admin/hero.php" class="form-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="alternar_visivel">
                                <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                                <button type="submit" class="botao botao-secundario"><?= (int)$img['visivel'] ? 'Ocultar' : 'Mostrar' ?></button>
                            </form>
                            <form method="post" action="admin/hero.php" class="form-inline" onsubmit="return confirm('Apagar esta imagem?');">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="apagar">
                                <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
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
    <h2>Adicionar imagem</h2>
    <form method="post" action="admin/hero.php" enctype="multipart/form-data" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="guardar">

        <div class="campo">
            <label for="imagem">Imagem (JPG, PNG ou WEBP, máx. 4 MB) <span class="obrigatorio">*</span></label>
            <input type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp" required>
            <p class="ajuda">Formato horizontal recomendado (ex.: 1600×900 px).</p>
        </div>
        <div class="campo">
            <label for="legenda">Legenda (opcional)</label>
            <input type="text" id="legenda" name="legenda" maxlength="200" placeholder="ex.: Receção da clínica">
        </div>
        <div class="campo">
            <label for="ordem">Ordem (0 = primeiro)</label>
            <input type="number" id="ordem" name="ordem" min="0" max="999" value="0">
        </div>
        <div class="campo campo-checkbox">
            <input type="checkbox" id="visivel" name="visivel" value="1" checked>
            <label for="visivel">Mostrar no slideshow</label>
        </div>

        <button type="submit" class="botao">Adicionar imagem</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>