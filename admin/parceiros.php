<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin']);
$pdo = db();

function processar_logo_parceiro(array $f): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar o logótipo.');
    }
    if ($f['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('O logótipo não pode ter mais de 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
    if (!isset($ext[$mime])) {
        throw new RuntimeException('O logótipo deve ser JPG, PNG, WEBP ou SVG.');
    }
    if ($ext[$mime] !== 'svg' && @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('Imagem inválida.');
    }
    $pasta = __DIR__ . '/../uploads/parceiros';
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
        throw new RuntimeException('Não foi possível criar a pasta.');
    }
    $nome = bin2hex(random_bytes(16)) . '.' . $ext[$mime];
    if (!move_uploaded_file($f['tmp_name'], $pasta . '/' . $nome)) {
        throw new RuntimeException('Não foi possível guardar o logótipo.');
    }
    return 'uploads/parceiros/' . $nome;
}

function apagar_logo_parceiro(?string $caminho): void
{
    if ($caminho && str_starts_with($caminho, 'uploads/parceiros/')) {
        $f = __DIR__ . '/../' . $caminho;
        if (is_file($f)) { @unlink($f); }
    }
}

$erros = [];
$form  = ['id' => 0, 'nome' => '', 'url' => '', 'ordem' => 0, 'visivel' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'apagar') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT logo FROM parceiros WHERE id = ?');
        $st->execute([$id]);
        $logo = $st->fetchColumn() ?: null;
        $pdo->prepare('DELETE FROM parceiros WHERE id = ?')->execute([$id]);
        apagar_logo_parceiro($logo);
        registar_admin('apagar_parceiro', 'parceiros', $id);
        flash_definir('sucesso', 'Parceiro apagado.');
        redirecionar('admin/parceiros.php');
    }

    if ($acao === 'alternar_visivel') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE parceiros SET visivel = 1 - visivel WHERE id = ?')->execute([$id]);
        registar_admin('parceiro_visibilidade', 'parceiros', $id);
        flash_definir('sucesso', 'Visibilidade atualizada.');
        redirecionar('admin/parceiros.php');
    }

    if ($acao === 'guardar') {
        $form['id']      = (int)($_POST['id'] ?? 0);
        $form['nome']    = trim((string)($_POST['nome'] ?? ''));
        $form['url']     = trim((string)($_POST['url'] ?? ''));
        $form['ordem']   = (int)($_POST['ordem'] ?? 0);
        $form['visivel'] = isset($_POST['visivel']) ? 1 : 0;

        if (mb_strlen($form['nome']) < 2 || mb_strlen($form['nome']) > 120) {
            $erros[] = 'Indica o nome do parceiro (2 a 120 caracteres).';
        }
        if ($form['url'] !== '' && !filter_var($form['url'], FILTER_VALIDATE_URL)) {
            $erros[] = 'O URL do parceiro não é válido.';
        }
        $temNovoLogo = isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($form['id'] <= 0 && !$temNovoLogo) {
            $erros[] = 'Adiciona um logótipo para o parceiro.';
        }

        if ($erros === []) {
            try {
                $novoLogo = processar_logo_parceiro($_FILES['logo'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
                $antigo   = null;

                if ($form['id'] > 0) {
                    $st = $pdo->prepare('SELECT logo FROM parceiros WHERE id = ?');
                    $st->execute([$form['id']]);
                    $antigo = $st->fetchColumn() ?: null;
                    $logoFinal = $novoLogo !== null ? $novoLogo : $antigo;
                    if (!$logoFinal) {
                        $erros[] = 'O parceiro precisa de um logótipo.';
                    } else {
                        $pdo->prepare(
                            'UPDATE parceiros SET nome = ?, logo = ?, url = ?, visivel = ?, ordem = ? WHERE id = ?'
                        )->execute([
                            $form['nome'], $logoFinal, $form['url'] ?: null, $form['visivel'], $form['ordem'], $form['id'],
                        ]);
                        if ($novoLogo !== null) { apagar_logo_parceiro($antigo); }
                        registar_admin('editar_parceiro', 'parceiros', $form['id']);
                        flash_definir('sucesso', 'Parceiro atualizado.');
                        redirecionar('admin/parceiros.php');
                    }
                } else {
                    if ($novoLogo === null) {
                        $erros[] = 'Adiciona um logótipo.';
                    } else {
                        $pdo->prepare(
                            'INSERT INTO parceiros (nome, logo, url, visivel, ordem) VALUES (?, ?, ?, ?, ?)'
                        )->execute([$form['nome'], $novoLogo, $form['url'] ?: null, $form['visivel'], $form['ordem']]);
                        registar_admin('criar_parceiro', 'parceiros', (int)$pdo->lastInsertId());
                        flash_definir('sucesso', 'Parceiro criado.');
                        redirecionar('admin/parceiros.php');
                    }
                }
            } catch (RuntimeException $e) {
                $erros[] = $e->getMessage();
            } catch (PDOException $e) {
                error_log('Erro parceiro: ' . $e->getMessage());
                $erros[] = 'Ocorreu um erro ao guardar o parceiro.';
            }
        }
    }
}

$editarId = (int)($_GET['editar'] ?? 0);
if ($editarId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT * FROM parceiros WHERE id = ?');
    $st->execute([$editarId]);
    if ($p = $st->fetch()) {
        $form = [
            'id' => (int)$p['id'], 'nome' => (string)$p['nome'], 'url' => (string)($p['url'] ?? ''),
            'ordem' => (int)$p['ordem'], 'visivel' => (int)$p['visivel'],
        ];
    }
}

$parceiros = $pdo->query('SELECT * FROM parceiros ORDER BY ordem, id DESC')->fetchAll();

$tituloPagina = 'Parceiros';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Parceiros</h1>

<?php foreach ($erros as $erro): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="seccao">
    <?php if (!$parceiros): ?>
        <div class="alerta alerta-info">Ainda não há parceiros.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Logótipo</th><th>Nome</th><th>URL</th><th>Ordem</th><th>Estado</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($parceiros as $p): ?>
                    <tr>
                        <td><?php if (!empty($p['logo'])): ?><img src="<?= e($p['logo']) ?>" alt="" style="max-height:40px;max-width:120px"><?php endif; ?></td>
                        <td><?= e($p['nome']) ?></td>
                        <td><?php if (!empty($p['url'])): ?><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener">Abrir</a><?php endif; ?></td>
                        <td><?= (int)$p['ordem'] ?></td>
                        <td><?= (int)$p['visivel'] ? 'Visível' : '<span class="estado-inativo">Oculto</span>' ?></td>
                        <td>
                            <a class="botao botao-secundario" href="admin/parceiros.php?editar=<?= (int)$p['id'] ?>">Editar</a>
                            <form method="post" action="admin/parceiros.php" class="form-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="alternar_visivel">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="botao botao-secundario"><?= (int)$p['visivel'] ? 'Ocultar' : 'Mostrar' ?></button>
                            </form>
                            <form method="post" action="admin/parceiros.php" class="form-inline" onsubmit="return confirm('Apagar este parceiro?');">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="apagar">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
    <h2><?= $form['id'] > 0 ? 'Editar parceiro' : 'Novo parceiro' ?></h2>
    <form method="post" action="admin/parceiros.php" enctype="multipart/form-data" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="campo">
            <label for="nome">Nome <span class="obrigatorio">*</span></label>
            <input type="text" id="nome" name="nome" required maxlength="120" value="<?= e($form['nome']) ?>">
        </div>
        <div class="campo">
            <label for="url">URL (opcional)</label>
            <input type="url" id="url" name="url" maxlength="255" value="<?= e($form['url']) ?>" placeholder="https://...">
        </div>
        <div class="campo">
            <label for="logo">Logótipo <?= $form['id'] > 0 ? '(opcional para manter o atual)' : '<span class="obrigatorio">*</span>' ?></label>
            <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp,image/svg+xml">
        </div>
        <div class="campo">
            <label for="ordem">Ordem (0 = primeiro)</label>
            <input type="number" id="ordem" name="ordem" min="0" max="999" value="<?= (int)$form['ordem'] ?>">
        </div>
        <div class="campo campo-checkbox">
            <input type="checkbox" id="visivel" name="visivel" value="1" <?= $form['visivel'] ? 'checked' : '' ?>>
            <label for="visivel">Mostrar no site</label>
        </div>

        <button type="submit" class="botao"><?= $form['id'] > 0 ? 'Guardar alterações' : 'Criar parceiro' ?></button>
        <?php if ($form['id'] > 0): ?><a class="botao botao-secundario" href="admin/parceiros.php">Cancelar</a><?php endif; ?>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
