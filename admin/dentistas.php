<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';

$u   = exigir_login(['admin']);   // só administradores
$pdo = db();

/**
 * Valida e guarda a fotografia enviada. Devolve o caminho relativo (ex.: uploads/dentistas/abc.jpg),
 * ou null se não foi enviada nenhuma. Lança RuntimeException com a mensagem de erro.
 */
function processar_fotografia(array $f): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a fotografia. Tenta outra vez.');
    }
    if ($f['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('A fotografia não pode ter mais de 2 MB.');
    }

    // Verifica o tipo REAL do ficheiro (não confiar na extensão que o utilizador deu)
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensoes[$mime]) || @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('A fotografia tem de ser uma imagem JPG, PNG ou WEBP.');
    }

    $pasta = __DIR__ . '/../uploads/dentistas';
    if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
        throw new RuntimeException('Não foi possível criar a pasta das fotografias.');
    }

    $nome = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];   // nome aleatório
    if (!move_uploaded_file($f['tmp_name'], $pasta . '/' . $nome)) {
        throw new RuntimeException('Não foi possível guardar a fotografia.');
    }
    return 'uploads/dentistas/' . $nome;
}

function apagar_fotografia(?string $caminho): void
{
    if ($caminho && str_starts_with($caminho, 'uploads/dentistas/')) {
        $ficheiro = __DIR__ . '/../' . $caminho;
        if (is_file($ficheiro)) { @unlink($ficheiro); }
    }
}

$erros = [];
$form  = ['id' => 0, 'nome' => '', 'cedula' => '', 'descricao' => '', 'esp' => [], 'trat' => [], 'fotografia' => null];

// ---------- Ações (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    // --- Ativar / desativar ---
    if ($acao === 'alternar_ativo') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE dentistas SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        registar_admin('alternar_ativo_dentista', 'dentistas', $id);
        flash_definir('sucesso', 'Estado do dentista atualizado.');
        redirecionar('admin/dentistas.php');
    }

    // --- Criar / editar ---
    if ($acao === 'guardar') {
        $form['id']        = (int)($_POST['id'] ?? 0);
        $form['nome']      = trim((string)($_POST['nome'] ?? ''));
        $form['cedula']    = trim((string)($_POST['cedula'] ?? ''));
        $form['descricao'] = trim((string)($_POST['descricao'] ?? ''));
        $form['esp']       = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['esp'] ?? [])))));
        $form['trat']      = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['trat'] ?? [])))));

        if (mb_strlen($form['nome']) < 3 || mb_strlen($form['nome']) > 150) {
            $erros[] = 'Indica o nome completo do dentista (3 a 150 caracteres).';
        }
        if (mb_strlen($form['cedula']) > 30) {
            $erros[] = 'A cédula profissional é demasiado longa.';
        }

        if ($erros === []) {
            $novaFoto = null;
            try {
                $novaFoto = processar_fotografia($_FILES['fotografia'] ?? ['error' => UPLOAD_ERR_NO_FILE]);

                $pdo->beginTransaction();

                $fotoAntiga = null;
                if ($form['id'] > 0) {
                    $st = $pdo->prepare('SELECT fotografia FROM dentistas WHERE id = ?');
                    $st->execute([$form['id']]);
                    $fotoAntiga = $st->fetchColumn() ?: null;

                    $pdo->prepare(
                        'UPDATE dentistas
                            SET nome_completo = ?, cedula_profissional = ?, descricao = ?,
                                fotografia = COALESCE(?, fotografia)
                          WHERE id = ?'
                    )->execute([
                        $form['nome'], $form['cedula'] ?: null, $form['descricao'] ?: null,
                        $novaFoto, $form['id'],
                    ]);
                    $id = $form['id'];
                } else {
                    $pdo->prepare(
                        'INSERT INTO dentistas (nome_completo, cedula_profissional, descricao, fotografia)
                         VALUES (?, ?, ?, ?)'
                    )->execute([$form['nome'], $form['cedula'] ?: null, $form['descricao'] ?: null, $novaFoto]);
                    $id = (int)$pdo->lastInsertId();
                }

                // Volta a gravar as especialidades e tratamentos escolhidos
                $pdo->prepare('DELETE FROM dentistas_especialidades WHERE dentista_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM dentistas_tratamentos WHERE dentista_id = ?')->execute([$id]);

                $insEsp = $pdo->prepare('INSERT INTO dentistas_especialidades (dentista_id, especialidade_id) VALUES (?, ?)');
                foreach ($form['esp'] as $eid) { $insEsp->execute([$id, $eid]); }

                $insTr = $pdo->prepare('INSERT INTO dentistas_tratamentos (dentista_id, tratamento_id) VALUES (?, ?)');
                foreach ($form['trat'] as $tid) { $insTr->execute([$id, $tid]); }

                $pdo->commit();

                if ($novaFoto !== null) { apagar_fotografia($fotoAntiga); }   // só depois de tudo gravado
                registar_admin($form['id'] > 0 ? 'editar_dentista' : 'criar_dentista', 'dentistas', $id, $form['nome']);
                flash_definir('sucesso', $form['id'] > 0 ? 'Dentista atualizado.' : 'Dentista criado.');
                redirecionar('admin/dentistas.php');
            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                apagar_fotografia($novaFoto);
                $erros[] = $e->getMessage();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                apagar_fotografia($novaFoto);
                error_log('Erro ao guardar dentista: ' . $e->getMessage());
                $erros[] = 'Ocorreu um erro. Tenta novamente.';
            }
        }
    }
}

// ---------- Editar: carregar dados do dentista (GET ?editar=ID) ----------
$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
if ($editarId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT id, nome_completo, cedula_profissional, descricao, fotografia FROM dentistas WHERE id = ?');
    $st->execute([$editarId]);
    if ($d = $st->fetch()) {
        $form = [
            'id' => (int)$d['id'], 'nome' => $d['nome_completo'], 'cedula' => (string)$d['cedula_profissional'],
            'descricao' => (string)$d['descricao'], 'fotografia' => $d['fotografia'], 'esp' => [], 'trat' => [],
        ];
        $st = $pdo->prepare('SELECT especialidade_id FROM dentistas_especialidades WHERE dentista_id = ?');
        $st->execute([$editarId]);
        $form['esp'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        $st = $pdo->prepare('SELECT tratamento_id FROM dentistas_tratamentos WHERE dentista_id = ?');
        $st->execute([$editarId]);
        $form['trat'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}

// ---------- Dados para mostrar ----------
$especialidades = $pdo->query('SELECT id, nome FROM especialidades WHERE ativo = 1 ORDER BY nome')->fetchAll();
$tratamentos    = $pdo->query('SELECT id, nome FROM tratamentos WHERE ativo = 1 ORDER BY nome')->fetchAll();
$dentistas      = $pdo->query(
    'SELECT d.id, d.nome_completo, d.fotografia, d.ativo,
            (SELECT GROUP_CONCAT(e.nome ORDER BY e.nome SEPARATOR \', \')
               FROM dentistas_especialidades de JOIN especialidades e ON e.id = de.especialidade_id
              WHERE de.dentista_id = d.id) AS especialidades
       FROM dentistas d ORDER BY d.ativo DESC, d.nome_completo'
)->fetchAll();

$tituloPagina = 'Gerir dentistas';
require __DIR__ . '/../includes/cabecalho.php';
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Gerir dentistas</h1>

<section class="seccao">
    <?php if (!$dentistas): ?>
        <div class="alerta alerta-info">Ainda não há dentistas. Cria o primeiro no formulário abaixo.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th></th><th>Nome</th><th>Especialidades</th><th>Estado</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($dentistas as $d): ?>
                    <tr>
                        <td><?= avatar_dentista($d) ?></td>
                        <td><?= e($d['nome_completo']) ?></td>
                        <td><?= e($d['especialidades'] ?? '—') ?></td>
                        <td><?= (int)$d['ativo'] ? 'Ativo' : '<span class="estado-inativo">Desativado</span>' ?></td>
                        <td>
                            <a class="botao botao-secundario" href="admin/dentistas.php?editar=<?= (int)$d['id'] ?>">Editar</a>
                            <form method="post" action="admin/dentistas.php" class="form-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="alternar_ativo">
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="botao botao-secundario">
                                    <?= (int)$d['ativo'] ? 'Desativar' : 'Ativar' ?>
                                </button>
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
    <h2><?= $form['id'] > 0 ? 'Editar dentista' : 'Novo dentista' ?></h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="admin/dentistas.php" enctype="multipart/form-data" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="campo">
            <label for="nome">Nome completo <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="text" id="nome" name="nome" required maxlength="150" value="<?= e($form['nome']) ?>">
        </div>

        <div class="campo">
            <label for="cedula">Cédula profissional</label>
            <input type="text" id="cedula" name="cedula" maxlength="30" value="<?= e($form['cedula']) ?>">
        </div>

        <div class="campo">
            <label for="descricao">Breve descrição</label>
            <textarea id="descricao" name="descricao" rows="4"><?= e($form['descricao']) ?></textarea>
        </div>

        <fieldset class="campo">
            <legend>Especialidades</legend>
            <div class="checks">
                <?php foreach ($especialidades as $e): ?>
                    <label class="check">
                        <input type="checkbox" name="esp[]" value="<?= (int)$e['id'] ?>"
                               <?= in_array((int)$e['id'], $form['esp'], true) ? 'checked' : '' ?>>
                        <?= e($e['nome']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="campo">
            <legend>Tratamentos que realiza</legend>
            <div class="checks">
                <?php foreach ($tratamentos as $t): ?>
                    <label class="check">
                        <input type="checkbox" name="trat[]" value="<?= (int)$t['id'] ?>"
                               <?= in_array((int)$t['id'], $form['trat'], true) ? 'checked' : '' ?>>
                        <?= e($t['nome']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="campo">
            <label for="fotografia">Fotografia (JPG, PNG ou WEBP, máx. 2 MB)</label>
            <?php if ($form['fotografia']): ?>
                <p><?= avatar_dentista(['fotografia' => $form['fotografia'], 'nome_completo' => $form['nome']]) ?></p>
                <p class="ajuda">Se escolheres outra fotografia, substitui a atual.</p>
            <?php endif; ?>
            <input type="file" id="fotografia" name="fotografia" accept="image/jpeg,image/png,image/webp">
        </div>

        <button type="submit" class="botao"><?= $form['id'] > 0 ? 'Guardar alterações' : 'Criar dentista' ?></button>
        <?php if ($form['id'] > 0): ?>
            <a class="botao botao-secundario" href="admin/dentistas.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
