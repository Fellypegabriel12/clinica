<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';

$u   = exigir_login(['admin']);
$pdo = db();

$erros = [];
$form  = ['id' => 0, 'nome' => '', 'descricao' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'alternar_ativo') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE especialidades SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        registar_admin('alternar_ativo_especialidade', 'especialidades', $id);
        flash_definir('sucesso', 'Estado da especialidade atualizado.');
        redirecionar('admin/especialidades.php');
    }

    if ($acao === 'guardar') {
        $form['id']        = (int)($_POST['id'] ?? 0);
        $form['nome']      = trim((string)($_POST['nome'] ?? ''));
        $form['descricao'] = trim((string)($_POST['descricao'] ?? ''));

        if (mb_strlen($form['nome']) < 2 || mb_strlen($form['nome']) > 100) {
            $erros[] = 'Indica o nome da especialidade (2 a 100 caracteres).';
        }
        if (mb_strlen($form['descricao']) > 255) {
            $erros[] = 'A descrição é demasiado longa.';
        }

        if ($erros === []) {
            try {
                if ($form['id'] > 0) {
                    $pdo->prepare('UPDATE especialidades SET nome = ?, descricao = ? WHERE id = ?')
                        ->execute([$form['nome'], $form['descricao'] ?: null, $form['id']]);
                    registar_admin('editar_especialidade', 'especialidades', $form['id'], $form['nome']);
                    flash_definir('sucesso', 'Especialidade atualizada.');
                } else {
                    $pdo->prepare('INSERT INTO especialidades (nome, descricao) VALUES (?, ?)')
                        ->execute([$form['nome'], $form['descricao'] ?: null]);
                    registar_admin('criar_especialidade', 'especialidades', (int)$pdo->lastInsertId(), $form['nome']);
                    flash_definir('sucesso', 'Especialidade criada.');
                }
                redirecionar('admin/especialidades.php');
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    $erros[] = 'Já existe uma especialidade com esse nome.';
                } else {
                    error_log('Erro especialidade: ' . $e->getMessage());
                    $erros[] = 'Ocorreu um erro. Tenta novamente.';
                }
            }
        }
    }
}

$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
if ($editarId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT id, nome, descricao FROM especialidades WHERE id = ?');
    $st->execute([$editarId]);
    if ($e = $st->fetch()) {
        $form = [
            'id' => (int)$e['id'],
            'nome' => $e['nome'],
            'descricao' => (string)$e['descricao'],
        ];
    }
}

$especialidades = $pdo->query(
    'SELECT id, nome, descricao, ativo,
            (SELECT COUNT(*) FROM dentistas_especialidades de WHERE de.especialidade_id = especialidades.id) AS total_dentistas
       FROM especialidades ORDER BY ativo DESC, nome'
)->fetchAll();

$tituloPagina = 'Gerir especialidades';
require __DIR__ . '/../includes/cabecalho.php';
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Gerir especialidades</h1>

<section class="seccao">
    <?php if (!$especialidades): ?>
        <div class="alerta alerta-info">Ainda não há especialidades. Cria a primeira no formulário abaixo.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Nome</th><th>Descrição</th><th>Dentistas</th><th>Estado</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($especialidades as $e): ?>
                    <tr>
                        <td><?= e($e['nome']) ?></td>
                        <td><?= e($e['descricao'] ?? '—') ?></td>
                        <td><?= (int)$e['total_dentistas'] ?></td>
                        <td><?= (int)$e['ativo'] ? 'Ativa' : '<span class="estado-inativo">Desativada</span>' ?></td>
                        <td>
                            <a class="botao botao-secundario" href="admin/especialidades.php?editar=<?= (int)$e['id'] ?>">Editar</a>
                            <form method="post" action="admin/especialidades.php" class="form-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="alternar_ativo">
                                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                                <button type="submit" class="botao botao-secundario">
                                    <?= (int)$e['ativo'] ? 'Desativar' : 'Ativar' ?>
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
    <h2><?= $form['id'] > 0 ? 'Editar especialidade' : 'Nova especialidade' ?></h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="admin/especialidades.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="campo">
            <label for="nome">Nome <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="text" id="nome" name="nome" required maxlength="100" value="<?= e($form['nome']) ?>">
        </div>

        <div class="campo">
            <label for="descricao">Descrição (opcional)</label>
            <input type="text" id="descricao" name="descricao" maxlength="255" value="<?= e($form['descricao']) ?>">
        </div>

        <button type="submit" class="botao"><?= $form['id'] > 0 ? 'Guardar alterações' : 'Criar especialidade' ?></button>
        <?php if ($form['id'] > 0): ?>
            <a class="botao botao-secundario" href="admin/especialidades.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>