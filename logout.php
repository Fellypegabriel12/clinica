<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

// Só aceita POST (o botão "Terminar sessão" do cabeçalho) com token CSRF válido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('login.php');
}
csrf_validar();

// Apaga os dados da sessão e o cookie
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?: 'Lax',
    ]);
}
session_destroy();

// Nova sessão limpa só para poder mostrar a mensagem
session_start();
flash_definir('sucesso', 'Sessão terminada com sucesso.');
redirecionar('login.php');
