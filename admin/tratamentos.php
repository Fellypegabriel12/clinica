<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';

$u   = exigir_login(['admin']);
$pdo = db();

$erros = [];
$form  = [
    'id' => 0, 'nome' => '', 'descricao' => '', 'especialidade' => 0,
    'preco' => '', 'tipo_preco' => 'fixo', 'duracao_min' => 30,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'alternar_ativo') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE tratamentos SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        registar_admin('alternar_ativo_tratamento', 'tratamentos', $id);
        flash_definir('sucesso', 'Estado do tratamento atualizado.');
        redirecionar('admin/tratamentos.php');
    }

    if ($acao === 'guardar') {
        $form['id']            = (int)($_POST['id'] ?? 0);
        $form['nome']          = trim((string)($_POST['nome'] ?? ''));
        $form['descricao']     = trim((string)($_POST['descricao'] ?? ''));
        $form['especialidade'] = (int)($_POST['especialidade'] ?? 0);
        $form['preco']         = str_replace(',', '.', trim((string)($_POST['preco'] ?? '0')));
        $form['tipo_preco']    = (string)($_POST['tipo_preco'] ?? 'fixo');
        $form['duracao_min']   = (int)($_POST['duracao_min'] ?? 30);

        if (mb_strlen($form['nome']) < 2 || mb_strlen($form['nome']) > 120) {
            $erros[] = 'Indica o nome do tratamento (2 a 120 caracteres).';
        }
        if (!is_numeric($form['preco']) || (float)$form['preco'] < 0) {
            $erros[] = 'Indica um preço válido (≥ 0).';
        }
        if (!in_array($form['tipo_preco'], ['fixo', 'sujeito_avaliacao'], true)) {
            $erros[] = 'Tipo de preço inválido.';
        }
        if ($form['duracao_min'] < 15 || $form['duracao_min'] > 480) {
            $erros[] = 'A duração deve estar entre 15 e 480 minutos.';
        }
        if ($form['especialidade'] > 0) {
            $st = $pdo->prepare('SELECT 1 FROM especialidades WHERE id = ? AND ativo = 1');
            $st->execute([$form['especialidade']]);
            if (!$st->fetchColumn()) {
                $erros[] = 'Especialidade inválida.';
            }
        }

        if ($erros === []) {
            try {
                if ($form['id'] > 0) {
                    $stAnt = $pdo->prepare('SELECT id, nome, descricao, especialidade_id, preco, tipo_preco, duracao_min, ativo FROM tratamentos WHERE id = ?');
                    $stAnt->execute([$form['id']]);
                    $antes = $stAnt->fetch(PDO::FETCH_ASSOC) ?: null;
                    $pdo->prepare(
                        'UPDATE tratamentos SET nome = ?, descricao = ?, especialidade_id = ?,
                                preco = ?, tipo_preco = ?, duracao_min = ? WHERE id = ?'
                    )->execute([
                        $form['nome'], $form['descricao'] ?: null,
                        $form['especialidade'] ?: null,
                        $form['preco'], $form['tipo_preco'], $form['duracao_min'],
                        $form['id'],
                    ]);
                    $depois = [
                        'id' => $form['id'], 'nome' => $form['nome'], 'descricao' => $form['descricao'] ?: null,
                        'especialidade_id' => $form['especialidade'] ?: null, 'preco' => $form['preco'],
                        'tipo_preco' => $form['tipo_preco'], 'duracao_min' => $form['duracao_min'],
                    ];
                    registar_admin('editar_tratamento', 'tratamentos', $form['id'], $form['nome'], $antes, $depois, null);
                    flash_definir('sucesso', 'Tratamento atualizado.');
                } else {
                    $pdo->prepare(
                        'INSERT INTO tratamentos (nome, descricao, especialidade_id, preco, tipo_preco, duracao_min)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $form['nome'], $form['descricao'] ?: null,
                        $form['especialidade'] ?: null,
                        $form['preco'], $form['tipo_preco'], $form['duracao_min'],
                    ]);
                    $novoId = (int)$pdo->lastInsertId();
                    registar_admin('criar_tratamento', 'tratamentos', $novoId, $form['nome'], null, [
                        'id' => $novoId, 'nome' => $form['nome'], 'preco' => $form['preco'],
                        'tipo_preco' => $form['tipo_preco'], 'duracao_min' => $form['duracao_min'],
                    ], null);
                    flash_definir('sucesso', 'Tratamento criado.');
                }
                redirecionar('admin/tratamentos.php');
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    $erros[] = 'Já existe um tratamento com esse nome.';
                } else {
                    error_log('Erro tratamento: ' . $e->getMessage());
                    $erros[] = 'Ocorreu um erro. Tenta novamente.';
                }
            }
        }
    }
}

$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
if ($editarId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT id, nome, descricao, especialidade_id, preco, tipo_preco, duracao_min FROM tratamentos WHERE id = ?');
    $st->execute([$editarId]);
    if ($t = $st->fetch()) {
        $form = [
            'id' => (int)$t['id'],
            'nome' => $t['nome'],
            'descricao' => (string)$t['descricao'],
            'especialidade' => (int)($t['especialidade_id'] ?? 0),
            'preco' => (string)$t['preco'],
            'tipo_preco' => $t['tipo_preco'],
            'duracao_min' => (int)$t['duracao_min'],
        ];
    }
}

$especialidades = $pdo->query('SELECT id, nome FROM especialidades WHERE ativo = 1 ORDER BY nome')->fetchAll();
$tratamentos = $pdo->query(
    'SELECT t.id, t.nome, t.descricao, t.preco, t.tipo_preco, t.duracao_min, t.ativo,
            e.nome AS especialidade
       FROM tratamentos t
       LEFT JOIN especialidades e ON e.id = t.especialidade_id
      ORDER BY t.ativo DESC, t.nome'
)->fetchAll();

$tituloPagina = 'Gerir tratamentos';
require __DIR__ . '/../includes/cabecalho.php';
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Gerir tratamentos</h1>

<section class="seccao">
    <?php if (!$tratamentos): ?>
        <div class="alerta alerta-info">Ainda não há tratamentos. Cria o primeiro no formulário abaixo.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead>
                    <tr><th>Nome</th><th>Especialidade</th><th>Preço</th><th>Duração</th><th>Estado</th><th>Ações</th></tr>
                </thead>
                <tbody>
                <?php foreach ($tratamentos as $t): ?>
                    <tr>
                        <td><?= e($t['nome']) ?></td>
                        <td><?= e($t['especialidade'] ?? '—') ?></td>
                        <td>
                            <?= e(formatar_preco($t['preco'])) ?>
                            <?php if ($t['tipo_preco'] === 'sujeito_avaliacao'): ?>
                                <br><span class="ajuda">estimado</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(formatar_duracao((int)$t['duracao_min'])) ?></td>
                        <td><?= (int)$t['ativo'] ? 'Ativo' : '<span class="estado-inativo">Desativado</span>' ?></td>
                        <td>
                            <a class="botao botao-secundario" href="admin/tratamentos.php?editar=<?= (int)$t['id'] ?>">Editar</a>
                            <form method="post" action="admin/tratamentos.php" class="form-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="alternar_ativo">
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="botao botao-secundario">
                                    <?= (int)$t['ativo'] ? 'Desativar' : 'Ativar' ?>
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
    <h2><?= $form['id'] > 0 ? 'Editar tratamento' : 'Novo tratamento' ?></h2>

    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="admin/tratamentos.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="guardar">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="campo">
            <label for="nome">Nome <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="text" id="nome" name="nome" required maxlength="120" value="<?= e($form['nome']) ?>">
        </div>

        <div class="campo">
            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao" rows="3"><?= e($form['descricao']) ?></textarea>
        </div>

        <div class="campo">
            <label for="especialidade">Especialidade</label>
            <select id="especialidade" name="especialidade">
                <option value="0">— Nenhuma —</option>
                <?php foreach ($especialidades as $e): ?>
                    <option value="<?= (int)$e['id'] ?>" <?= $form['especialidade'] === (int)$e['id'] ? 'selected' : '' ?>>
                        <?= e($e['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="preco">Preço (€) <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="text" id="preco" name="preco" required maxlength="10" value="<?= e($form['preco']) ?>" placeholder="ex.: 50.00">
        </div>

        <div class="campo">
            <label for="tipo_preco">Tipo de preço</label>
            <select id="tipo_preco" name="tipo_preco">
                <option value="fixo" <?= $form['tipo_preco'] === 'fixo' ? 'selected' : '' ?>>Fixo</option>
                <option value="sujeito_avaliacao" <?= $form['tipo_preco'] === 'sujeito_avaliacao' ? 'selected' : '' ?>>Sujeito a avaliação</option>
            </select>
        </div>

        <div class="campo">
            <label for="duracao_min">Duração (minutos) <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="number" id="duracao_min" name="duracao_min" required min="15" max="480" step="15" value="<?= (int)$form['duracao_min'] ?>">
        </div>

        <button type="submit" class="botao"><?= $form['id'] > 0 ? 'Guardar alterações' : 'Criar tratamento' ?></button>
        <?php if ($form['id'] > 0): ?>
            <a class="botao botao-secundario" href="admin/tratamentos.php">Cancelar</a>
        <?php endif; ?>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>