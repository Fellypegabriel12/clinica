<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';

$u   = exigir_login(['admin']);
$pdo = db();

// Contas de staff. Pacientes registam-se na área pública (registo.php).
const PAPEIS_PERMITIDOS = [
    'rececao'  => 'Receção',
    'dentista' => 'Dentista',
    'admin'    => 'Administrador',
];

$erros = [];
$form  = [
    'nome' => '', 'email' => '', 'telemovel' => '',
    'papel' => 'rececao', 'dentista' => 0,
    'password' => '', 'password2' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $form['nome']      = trim((string)($_POST['nome'] ?? ''));
    $form['email']     = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $form['telemovel'] = preg_replace('/\s+/', '', trim((string)($_POST['telemovel'] ?? '')));
    $form['papel']     = (string)($_POST['papel'] ?? 'rececao');
    $form['dentista']  = (int)($_POST['dentista'] ?? 0);
    $form['password']  = (string)($_POST['password'] ?? '');
    $form['password2'] = (string)($_POST['password2'] ?? '');

    if (mb_strlen($form['nome']) < 3 || mb_strlen($form['nome']) > 150) {
        $erros[] = 'Indica o nome completo (3 a 150 caracteres).';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 190) {
        $erros[] = 'Indica um email válido.';
    }
    if (!preg_match('/^\+?\d{9,15}$/', $form['telemovel'])) {
        $erros[] = 'Indica um telemóvel válido (só números).';
    }
    if (!isset(PAPEIS_PERMITIDOS[$form['papel']])) {
        $erros[] = 'Papel inválido.';
    }
    if (strlen($form['password']) < 8 || strlen($form['password']) > 72
        || !preg_match('/[A-Za-z]/', $form['password']) || !preg_match('/\d/', $form['password'])) {
        $erros[] = 'A palavra-passe deve ter entre 8 e 72 caracteres, com letras e números.';
    }
    if ($form['password'] !== $form['password2']) {
        $erros[] = 'As palavras-passe não coincidem.';
    }

    // Se for dentista, tem de escolher um perfil de dentista livre
    $dentistaEscolhido = null;
    if ($form['papel'] === 'dentista' && $erros === []) {
        $st = $pdo->prepare('SELECT id FROM dentistas WHERE id = ? AND utilizador_id IS NULL');
        $st->execute([$form['dentista']]);
        if (!$st->fetchColumn()) {
            $erros[] = 'Escolhe um perfil de dentista disponível.';
        } else {
            $dentistaEscolhido = $form['dentista'];
        }
    }

    // Email já existe?
    if ($erros === []) {
        $st = $pdo->prepare('SELECT 1 FROM utilizadores WHERE email = ?');
        $st->execute([$form['email']]);
        if ($st->fetchColumn()) {
            $erros[] = 'Já existe um utilizador com este email.';
        }
    }

    if ($erros === []) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($form['password'], PASSWORD_DEFAULT);
            $pdo->prepare(
                'INSERT INTO utilizadores (nome_completo, email, telemovel, password_hash, papel, email_verificado_em)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            )->execute([$form['nome'], $form['email'], $form['telemovel'], $hash, $form['papel']]);
            $novoId = (int)$pdo->lastInsertId();

            // Pacientes não são criados por aqui (usam registo público). Mantido por segurança.
            if ($form['papel'] === 'paciente') {
                throw new RuntimeException('Criação de contas de paciente deve ser feita pelo registo público.');
            }

            // Se for dentista, liga ao perfil escolhido
            if ($dentistaEscolhido !== null) {
                $pdo->prepare('UPDATE dentistas SET utilizador_id = ? WHERE id = ?')
                    ->execute([$novoId, $dentistaEscolhido]);
            }

            $pdo->commit();
            registar_admin('criar_utilizador', 'utilizadores', $novoId, $form['papel']);
            flash_definir('sucesso', 'Utilizador criado com sucesso.');
            redirecionar('admin/utilizadores.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Erro ao criar utilizador: ' . $e->getMessage());
            $erros[] = 'Ocorreu um erro. Tenta novamente.';
        }
    }
}

// Dentistas sem utilizador associado
$dentistasLivres = $pdo->query(
    'SELECT id, nome_completo FROM dentistas WHERE utilizador_id IS NULL AND ativo = 1 ORDER BY nome_completo'
)->fetchAll();

$tituloPagina = 'Novo utilizador';
require __DIR__ . '/../includes/cabecalho.php';
?>

<p><a href="admin/index.php">← Painel</a> · <a href="admin/utilizadores.php">← Utilizadores</a></p>
<h1>Criar novo utilizador</h1>
<p class="ajuda">Crie aqui contas de staff (receção, dentista, administrador). Os pacientes criam a própria conta no <a href="registo.php">registo público</a>.</p>

<?php foreach ($erros as $erro): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="cartao">
    <form method="post" action="admin/utilizadores-novo.php" novalidate>
        <?= csrf_campo() ?>

        <div class="campo">
            <label for="nome">Nome completo <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="text" id="nome" name="nome" required maxlength="150" value="<?= e($form['nome']) ?>">
        </div>

        <div class="campo">
            <label for="email">Email <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="email" id="email" name="email" required maxlength="190" value="<?= e($form['email']) ?>">
        </div>

        <div class="campo">
            <label for="telemovel">Telemóvel <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="tel" id="telemovel" name="telemovel" required maxlength="20" value="<?= e($form['telemovel']) ?>">
        </div>

        <div class="campo">
            <label for="papel">Papel <span class="obrigatorio" aria-hidden="true">*</span></label>
            <select id="papel" name="papel" onchange="mostrarDentista()">
                <?php foreach (PAPEIS_PERMITIDOS as $v => $t): ?>
                    <option value="<?= e($v) ?>" <?= $form['papel'] === $v ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo" id="campo-dentista" style="display:<?= $form['papel'] === 'dentista' ? 'block' : 'none' ?>;">
            <label for="dentista">Perfil de dentista a associar</label>
            <?php if (!$dentistasLivres): ?>
                <div class="alerta alerta-info">
                    Não há perfis de dentista livres.
                    <a href="admin/dentistas.php">Criar dentista primeiro</a>.
                </div>
                <input type="hidden" name="dentista" value="0">
            <?php else: ?>
                <select id="dentista" name="dentista">
                    <option value="0">— escolher —</option>
                    <?php foreach ($dentistasLivres as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $form['dentista'] === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['nome_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="campo">
            <label for="password">Palavra-passe <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="password" id="password" name="password" required minlength="8" maxlength="72"
                   autocomplete="new-password" aria-describedby="aj-pwd">
            <p id="aj-pwd" class="ajuda">Mínimo 8 caracteres, com pelo menos uma letra e um número.</p>
        </div>

        <div class="campo">
            <label for="password2">Repetir palavra-passe <span class="obrigatorio" aria-hidden="true">*</span></label>
            <input type="password" id="password2" name="password2" required maxlength="72" autocomplete="new-password">
        </div>

        <button type="submit" class="botao">Criar utilizador</button>
        <a class="botao botao-secundario" href="admin/utilizadores.php">Cancelar</a>
    </form>
</section>

<script>
function mostrarDentista() {
    var papel = document.getElementById('papel').value;
    document.getElementById('campo-dentista').style.display = (papel === 'dentista') ? 'block' : 'none';
}
</script>

<?php require __DIR__ . '/../includes/rodape.php'; ?>