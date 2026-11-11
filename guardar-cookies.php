<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/cookies.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('index.php');
}
csrf_validar();

$analiticos = ($_POST['analiticos'] ?? '0') === '1';
guardar_consentimento_cookies($analiticos);

// Volta para a página de onde veio (ou para a inicial)
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref !== '' && str_starts_with($ref, BASE_URL)) {
    redirecionar($ref);
}
redirecionar('index.php');