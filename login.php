<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

if (utilizador_atual() !== null) {
    redirecionar('area-pessoal.php');
}

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $email    = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $ip       = ip_cliente();
    $pdo      = db();

    // --- 1. Está bloqueado por tentativas a mais? (configurável na BD)
    $maxEmail = max(1, (int)config_valor($pdo, 'max_tentativas_login', (int)MAX_TENTATIVAS_EMAIL));
    $maxIp    = max(1, (int)config_valor($pdo, 'max_tentativas_ip', (int)MAX_TENTATIVAS_IP));
    $bloqMin  = max(1, (int)config_valor($pdo, 'bloqueio_login_min', (int)BLOQUEIO_MINUTOS));

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM tentativas_login
         WHERE email = ? AND sucesso = 0
           AND criado_em > (NOW() - INTERVAL ' . $bloqMin . ' MINUTE)'
    );
    $stmt->execute([$email]);
    $falhasEmail = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM tentativas_login
         WHERE ip = ? AND sucesso = 0
           AND criado_em > (NOW() - INTERVAL ' . $bloqMin . ' MINUTE)'
    );
    $stmt->execute([ip_cliente()]);
    $falhasIp = (int)$stmt->fetchColumn();

    if ($falhasEmail >= $maxEmail || $falhasIp >= $maxIp) {
        $erro = 'Demasiadas tentativas falhadas. Tenta novamente mais tarde.';
    } else {
        // --- 2. Procurar o utilizador e verificar a password ---
        $stmt = $pdo->prepare(
            'SELECT id, nome_completo, papel, tema, password_hash, ativo
             FROM utilizadores WHERE email = ?'
        );
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if ($u) {
            $passwordCorreta = password_verify($password, $u['password_hash']);
        } else {
            // Email não existe: faz na mesma uma operação "pesada" para a resposta
            // demorar o mesmo tempo e não se perceber que o email não existe.
            password_hash($password, PASSWORD_DEFAULT);
            $passwordCorreta = false;
        }

        if ($u && $passwordCorreta && (int)$u['ativo'] === 1) {
            // Exigir confirmação de email antes de permitir acesso (contas novas e não confirmadas)
            $stmtEv = $pdo->prepare('SELECT email_verificado_em FROM utilizadores WHERE id = ?');
            $stmtEv->execute([(int)$u['id']]);
            $emailVerificadoEm = $stmtEv->fetchColumn();
            if ($emailVerificadoEm === false || $emailVerificadoEm === null) {
                // Contas no novo fluxo (têm token de verificação) exigem confirmação.
                // Contas antigas sem qualquer token de verificação são migradas como verificadas no login.
                $stTok = $pdo->prepare("SELECT 1 FROM tokens_conta WHERE utilizador_id = ? AND tipo = 'verificar_email' LIMIT 1");
                $stTok->execute([(int)$u['id']]);
                if ($stTok->fetchColumn()) {
                    $_SESSION['confirmar_email_pendente'] = $email;
                    $pdo->prepare('INSERT INTO tentativas_login (email, ip, sucesso) VALUES (?, ?, 0)')
                        ->execute([mb_substr($email, 0, 190), $ip]);
                    flash_definir('info', 'Confirma o teu email antes de iniciar sessão. Introduz o código de confirmação.');
                    redirecionar('confirmar-email.php?email=' . urlencode($email));
                }
                $pdo->prepare('UPDATE utilizadores SET email_verificado_em = NOW() WHERE id = ? AND email_verificado_em IS NULL')
                    ->execute([(int)$u['id']]);
            }

            // --- 3. Sucesso ---
            session_regenerate_id(true);   // novo ID de sessão (previne session fixation)
            unset($_SESSION['csrf']);      // novo token CSRF

            $_SESSION['utilizador'] = [
                'id'    => (int)$u['id'],
                'nome'  => $u['nome_completo'],
                'papel' => $u['papel'],
                'tema'  => $u['tema'],
            ];

            // Se o algoritmo de hash mudou entre versões do PHP, atualiza o hash
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                $novoHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE utilizadores SET password_hash = ? WHERE id = ?')
                    ->execute([$novoHash, $u['id']]);
            }

            $pdo->prepare('UPDATE utilizadores SET ultimo_login = NOW() WHERE id = ?')->execute([$u['id']]);

            // Limpa as falhas deste email e regista o sucesso
            $pdo->prepare('DELETE FROM tentativas_login WHERE email = ? AND sucesso = 0')->execute([$email]);
            $pdo->prepare('INSERT INTO tentativas_login (email, ip, sucesso) VALUES (?, ?, 1)')
                ->execute([$email, $ip]);

            // Admin e receção vão para o painel; os outros para a área pessoal
            if (in_array($u['papel'], ['admin', 'rececao'], true)) {
                redirecionar('admin/index.php');
            }
            if (($u['papel'] ?? '') === 'dentista') {
                redirecionar('admin/marcacoes.php');
            }
            redirecionar('area-pessoal.php');
        }

        // --- 4. Falhou: regista e mostra mensagem genérica ---
        $pdo->prepare('INSERT INTO tentativas_login (email, ip, sucesso) VALUES (?, ?, 0)')
            ->execute([mb_substr($email, 0, 190), $ip]);
        $erro = 'Email ou palavra-passe incorretos.';
    }
}

$tituloPagina = 'Iniciar sessão';
require __DIR__ . '/includes/cabecalho.php';
?>

<section class="cartao cartao-estreito">
    <h1>Iniciar sessão</h1>

    <?php if ($erro !== ''): ?>
        <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate>
        <?= csrf_campo() ?>

        <div class="campo">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required maxlength="190"
                   autocomplete="email" value="<?= e($email) ?>">
        </div>

        <div class="campo">
            <label for="password">Palavra-passe</label>
            <input type="password" id="password" name="password" required maxlength="72"
                   autocomplete="current-password">
        </div>

        <button type="submit" class="botao botao-grande">Entrar</button>
    </form>

    <p class="texto-centro">Ainda não tens conta? <a href="registo.php">Criar conta</a></p>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
