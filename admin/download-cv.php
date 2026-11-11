<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';

exigir_login(['admin', 'rececao']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Pedido inválido.');
}

$st = db()->prepare('SELECT cv, nome FROM candidaturas WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row || empty($row['cv'])) {
    http_response_code(404);
    exit('Ficheiro não encontrado.');
}

$cv = (string)$row['cv'];
if (!str_starts_with($cv, 'uploads/cv/') || str_contains($cv, '..') || str_contains($cv, '\\')) {
    http_response_code(400);
    exit('Pedido inválido.');
}

$base = realpath(__DIR__ . '/../uploads/cv');
$full = realpath(__DIR__ . '/../' . $cv);
if ($base === false || $full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Ficheiro não encontrado.');
}
if (!is_file($full) || !is_readable($full)) {
    http_response_code(404);
    exit('Ficheiro não encontrado.');
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$tipos = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$mime = $tipos[$ext] ?? 'application/octet-stream';
$nome = 'cv-' . $id . '.' . $ext;

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($full));
header('Content-Disposition: inline; filename="' . $nome . '"');
readfile($full);
exit;
