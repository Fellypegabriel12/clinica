<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

if (utilizador_atual() !== null) {
    redirecionar('area-pessoal.php');
}

$erros  = [];
$token  = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$valido = false;
$userId = 0;
$nomeUser = '';

// Valida o token na BD (só hash é guardado)
if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $tokenHash = hash('sha256', $token);
    $st = db()->prepare(
        "SELECT t.utilizador_id, u.nome_completo
           FROM tokens_conta t
           JOIN utilizadores u ON u.id = t.utilizador_id
          WHERE t.token_hash = ? AND t.tipo = 'recuperar_password'
            AND t.usado_em IS NULL AND t.expira_em > NOW()
            AND u.ativo = 1"
    );
    $st->execute([$tokenHash]);
    if ($row = $st->fetch()) {
        $valido   = true;
        $userId   = (int)$row['utilizador_id'];
        $nomeUser = (string)$row['nome_completo'];
    }
}

// Ação de repor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (!$valido) {
        $erros[] = 'Este link de recuperação é inválido ou já expirou.';
    }

    $password  = (string)($_POST['password']  ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if (strlen($password) < 8 || strlen($password) > 72
        || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $erros[] = 'A palavra-passe deve ter entre 8 e 72 caracteres, com pelo menos uma letra e um número.';
    }
    if ($password !== $password2) {
        $erros[] = 'As palavras-passe não coincidem.';
    }

    if ($erros === []) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // Atualiza a password
            $pdo->prepare('UPDATE utilizadores SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);

            // Marca o token como usado
            $tokenHash = hash('sha256', $token);
            $pdo->prepare('UPDATE tokens_conta SET usado_em = NOW() WHERE token_hash = ?')
                ->execute([$tokenHash]);

            // Invalida sessões antigas? (opcional — depende se guardas sessões na BD; neste projeto não)
            // Como as sessões estão em ficheiro, não conseguimos invalidar todas. Mas o próximo login
            // já usará a nova password.

            // Regista no log
            registar_admin('repor_password', 'utilizadores', $userId);

            // Notifica o utilizador por notificação interna (não por email)
            $pdo->prepare(
                "INSERT INTO notificacoes (utilizador_id, tipo, canal, assunto, mensagem, estado, enviada_em)
                 VALUES (?, 'alteracao_data', 'interna', 'Palavra-passe alterada', ?, 'enviada', NOW())"
            )->execute([
                $userId,
                'A tua palavra-passe foi alterada com sucesso. Se não foste tu, contacta a clínica imediatamente.',
            ]);

            $pdo->commit();

            flash_definir('sucesso', 'Palavra-passe alterada com sucesso. Já podes iniciar sessão.');
            redirecionar('login.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Erro repor-password: ' . $e->getMessage());
            $erros[] = 'Ocorreu um erro. Tenta novamente.';
        }
    }
}

$tituloPagina = 'Repor palavra-passe';
require __DIR__ . '/includes/cabecalho.php';
?>

<section class="cartao cartao-estreito">
    <h1>Repor palavra-passe</h1>

    <?php if (!$valido): ?>
        <div class="alerta alerta-erro" role="alert">
            Este link é inválido ou já expirou. Pede um novo link de recuperação.
        </div>
        <p>
            <a class="botao" href="recuperar-password.php">Pedir novo link</a>
            <a class="botao botao-secundario" href="login.php">Ir para o login</a>
        </p>

    <?php else: ?>
        <p class="ajuda">
            Olá <strong><?= e($nomeUser) ?></strong>. Define a nova palavra-passe. O link expira em 1 hora e só serve uma vez.
        </p>

        <?php foreach ($erros as $erro): ?>
            <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
        <?php endforeach; ?>

        <form method="post" action="repor-password.php" novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="campo">
                <label for="password">Nova palavra-passe</label>
                <input type="password" id="password" name="password" required minlength="8" maxlength="72"
                       autocomplete="new-password" aria-describedby="aj-pwd">
                <p id="aj-pwd" class="ajuda">Mínimo 8 caracteres, com pelo menos uma letra e um número.</p>
            </div>

            <div class="campo">
                <label for="password2">Repetir nova palavra-passe</label>
                <input type="password" id="password2" name="password2" required maxlength="72" autocomplete="new-password">
            </div>

            <button type="submit" class="botao botao-grande">Guardar nova palavra-passe</button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>