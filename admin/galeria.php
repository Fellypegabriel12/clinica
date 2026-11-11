<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';

$u   = exigir_login(['admin']);
$pdo = db();

function processar_imagem_galeria(array $f): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a imagem.');
    }
    if ($f['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('A imagem não pode ter mais de 3 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($ext[$mime]) || @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('A imagem deve ser JPG, PNG ou WEBP.');
    }
    $pasta = __DIR__ . '/../uploads/galeria';
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
        throw new RuntimeException('Não foi possível criar a pasta de uploads.');
    }
    $nome = bin2hex(random_bytes(16)) . '.' . $ext[$mime];
    if (!move_uploaded_file($f['tmp_name'], $pasta . '/' . $nome)) {
        throw new RuntimeException('Não foi possível guardar a imagem.');
    }
    return 'uploads/galeria/' . $nome;
}

function apagar_imagem_galeria(?string $caminho): void
{
    if ($caminho && str_starts_with($caminho, 'uploads/galeria/')) {
        $f = __DIR__ . '/../' . $caminho;
        if (is_file($f)) { @unlink($f); }
    }
}

$erros = [];
$form  = ['id' => 0, 'tipo' => 'instalacoes', 'legenda' => '', 'ordem' => 0, 'visivel' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'apagar') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT caminho FROM galeria WHERE id = ?');
        $st->execute([$id]);
        $cam = $st->fetchColumn();
        $pdo->prepare('DELETE FROM galeria WHERE id = ?')->execute([$id]);
        apagar_imagem_galeria($cam ?: null);
        registar_admin('apagar_galeria', 'galeria', $id);
        flash_definir('sucesso', 'Imagem removida.');
        redirecionar('admin/galeria.php');
    }

    if ($acao === 'alternar_visivel') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE galeria SET visivel = 1 - visivel WHERE id = ?')->execute([$id]);
        registar_admin('alternar_visivel_galeria', 'galeria', $id);
        flash_definir('sucesso', 'Visibilidade atualizada.');
        redirecionar('admin/galeria.php');
    }

    if ($acao === 'guardar') {
        $form['id']       = (int)($_POST['id'] ?? 0);
        $form['tipo']     = (string)($_POST['tipo'] ?? 'instalacoes');
        $form['legenda']  = trim((string)($_POST['legenda'] ?? ''));
        $form['ordem']    = (int)($_POST['ordem'] ?? 0);
        $form['visivel']  = isset($_POST['visivel']) ? 1 : 0;

        if (!in_array($form['tipo'], ['instalacoes', 'dentista', 'resultado'], true)) {
            $erros[] = 'Tipo de imagem inválido.';
        }
        if (mb_strlen($form['legenda']) > 200) {
            $erros[] = 'A legenda é demasiado longa.';
        }
        if ($form['tipo'] === 'resultado') {
            // resultados só com consentimento explícito
            if (!isset($_POST['consentimento'])) {
                $erros[] = 'Imagens de resultados exigem consentimento confirmado do paciente.';
            }
        }

        $novaImg = null;
        if ($erros === []) {
            try {
                $novaImg = processar_imagem_galeria($_FILES['imagem'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
                if ($form['id'] === 0 && $novaImg === null) {
                    $erros[] = 'Escolhe uma imagem.';
                } else {
                    $antiga = null;
                    if ($form['id'] > 0) {
                        $st = $pdo->prepare('SELECT caminho FROM galeria WHERE id = ?');
                        $st->execute([$form['id']]);
                        $antiga = $st->fetchColumn() ?: null;

                        if ($novaImg !== null) {
                            $pdo->prepare(
                                'UPDATE galeria SET tipo = ?, caminho = ?, legenda = ?, ordem = ?, visivel = ?,
                                        consentimento_confirmado = ?,
                                        consentimento_em = IF(?, NOW(), consentimento_em)
                                  WHERE id = ?'
                            )->execute([
                                $form['tipo'], $novaImg, $form['legenda'] ?: null,
                                $form['ordem'], $form['visivel'],
                                $form['tipo'] === 'resultado' ? 1 : 0,
                                $form['tipo'] === 'resultado' ? 1 : 0,
                                $form['id'],
                            ]);
                        } else {
                            $pdo->prepare(
                                'UPDATE galeria SET tipo = ?, legenda = ?, ordem = ?, visivel = ?,
                                        consentimento_confirmado = ?
                                  WHERE id = ?'
                            )->execute([
                                $form['tipo'], $form['legenda'] ?: null,
                                $form['ordem'], $form['visivel'],
                                $form['tipo'] === 'resultado' ? 1 : 0,
                                $form['id'],
                            ]);
                        }
                        if ($novaImg !== null) { apagar_imagem_galeria($antiga); }
                        registar_admin('editar_galeria', 'galeria', $form['id']);
                        flash_definir('sucesso', 'Imagem atualizada.');
                    } else {
                        $pdo->prepare(
                            'INSERT INTO galeria (tipo, caminho, legenda, ordem, visivel, consentimento_confirmado, consentimento_em)
                             VALUES (?, ?, ?, ?, ?, ?, IF(?, NOW(), NULL))'
                        )->execute([
                            $form['tipo'], $novaImg, $form['legenda'] ?: null,
                            $form['ordem'], $form['visivel'],
                            $form['tipo'] === 'resultado' ? 1 : 0,
                            $form['tipo'] === 'resultado' ? 1 : 0,
                        ]);
                        registar_admin('criar_galeria', 'galeria', (int)$pdo->lastInsertId());
                        flash_definir('sucesso', 'Imagem adicionada.');
                    }
                    redirecionar('admin/galeria.php');
                }
            } catch (RuntimeException $e) {
                apagar_imagem_galeria($novaImg);
                $erros[] = $e->getMessage();
            } catch (PDOException $e) {
                apagar_imagem_galeria($novaImg);
                error_log('Erro galeria: ' . $e->getMessage());
                $erros[] = 'Ocorreu um erro. Tenta novamente.';
            }
        }
    }
}

$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
if ($editarId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT id, tipo, caminho, legenda, ordem, visivel FROM galeria WHERE id = ?');
    $st->execute([$editarId]);
    if ($g = $st->fetch()) {
        $form = [
            'id' => (int)$g['id'],
            'tipo' => $g['tipo'],
            'legenda' => (string)$g['legenda'],
            'ordem' => (int)$g['ordem'],
            'visivel' => (int)$g['visivel'],
        ];
    }
}

$galeria = $pdo->query('SELECT id, tipo, caminho, legenda, ordem, visivel, consentimento_confirmado FROM galeria ORDER BY ordem, id')->fetchAll();

$tipos = ['instalacoes' => 'Instalações', 'dentista' => 'Dentista', 'resultado' => 'Resultado clínico'];

$tituloPagina = 'Galeria';
require __DIR__ . '/../includes/cabecalho.php';
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Galeria</h1>

<p class="ajuda">Imagens mostradas na página inicial. As imagens de resultados clínicos só aparecem com consentimento confirmado do paciente (RGPD).</p>

<section class="seccao">
    <?php if (!$galeria): ?>
        <div class="alerta alerta-info">Ainda não há imagens. Adiciona a primeira no formulário abaixo.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Pré-visualização</th><th>Tipo</th><th>Legenda</th><th>Ordem</th><th>Estado</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($galeria as $g): ?>
                    <tr>
                        <td><img src="<?= e($g['caminho']) ?>" alt="" style="max-width:80px;max-height:60px;border-radius:6px;"></td>
                        <td><?= e($tipos[$g['tipo']] ?? $g['tipo']) ?></td>
                        <td><?= e($g['legenda'] ?? '—') ?></td>
                        <td><?= (int)$g['ordem'] ?></td>
                        <td><?= (int)$g['visivel'] ? 'Visível' : '<span class="estado-inativo">Oculta</span>' ?></td>
                        <td>
                            <a class="botao botao-secundario" href="admin/galeria.php?editar=<?= (int)$g['id'] ?>">Editar</a>
                            <form method="post" action="admin/galeria.php" class="form-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="alternar_visivel">
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                <button type="submit" class="botao botao-secundario">
                                    <?= (int)$g['visivel'] ? 'Ocultar' : 'Mostrar' ?>
                                </button>
                            </form>
                            <form method="post" action="admin/galeria.php" class="form-inline" onsubmit="return confirm('Apagar esta imagem?');">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="apagar">
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
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
    <h2><?= $form['id'] > 0 ? 'Editar imagem' : 'Nova imagem' ?></h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="admin/galeria.php" enctype="multipart/form-data" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="campo">
            <label for="tipo">Tipo <span class="obrigatorio" aria-hidden="true">*</span></label>
            <select id="tipo" name="tipo">
                <?php foreach ($tipos as $v => $t): ?>
                    <option value="<?= e($v) ?>" <?= $form['tipo'] === $v ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="legenda">Legenda (opcional)</label>
            <input type="text" id="legenda" name="legenda" maxlength="200" value="<?= e($form['legenda']) ?>">
        </div>

        <div class="campo">
            <label for="ordem">Ordem (0 = primeiro)</label>
            <input type="number" id="ordem" name="ordem" min="0" max="999" value="<?= (int)$form['ordem'] ?>">
        </div>

        <div class="campo">
            <label for="imagem">Imagem (JPG, PNG ou WEBP, máx. 3 MB)<?= $form['id'] === 0 ? ' <span class="obrigatorio" aria-hidden="true">*</span>' : '' ?></label>
            <?php if ($form['id'] > 0): ?>
                <p class="ajuda">Deixa em branco para manter a imagem atual.</p>
            <?php endif; ?>
            <input type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="visivel" name="visivel" value="1" <?= $form['visivel'] ? 'checked' : '' ?>>
            <label for="visivel">Mostrar esta imagem na página inicial</label>
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="consentimento" name="consentimento" value="1">
            <label for="consentimento">Confirmo que tenho o consentimento do paciente (obrigatório para imagens de resultados)</label>
        </div>

        <button type="submit" class="botao"><?= $form['id'] > 0 ? 'Guardar alterações' : 'Adicionar imagem' ?></button>
        <?php if ($form['id'] > 0): ?>
            <a class="botao botao-secundario" href="admin/galeria.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>