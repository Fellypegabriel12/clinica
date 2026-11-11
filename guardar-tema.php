<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

// Chamado pelo js/tema.js (só utilizadores com sessão, só por POST).
// Devolve SEMPRE JSON, mesmo em caso de erro (o cliente não espera HTML).
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'erro' => 'Método não permitido.']));
}

$u = utilizador_atual();
if ($u === null) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'erro' => 'Sessão expirada.']));
}

// CSRF: validamos à mão em vez de usar csrf_validar(), porque essa função
// termina com exit() em HTML — aqui queremos JSON.
$csrf = $_POST['csrf'] ?? '';
if (!is_string($csrf) || !hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'erro' => 'Token de segurança inválido.']));
}

$tema = (string)($_POST['tema'] ?? '');
if (!in_array($tema, ['claro', 'escuro'], true)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'erro' => 'Tema inválido.']));
}

try {
    db()->prepare('UPDATE utilizadores SET tema = ? WHERE id = ?')
        ->execute([$tema, $u['id']]);
    $_SESSION['utilizador']['tema'] = $tema;
} catch (PDOException $e) {
    error_log('Erro guardar-tema: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['ok' => false, 'erro' => 'Erro na base de dados.']));
}

// Liberta o lock da sessão antes de responder (não bloqueia outros pedidos do mesmo utilizador)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

echo json_encode(['ok' => true]);
exit;