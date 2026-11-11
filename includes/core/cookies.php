<?php
declare(strict_types=1);

if (!defined('COOKIE_CONSENTIMENTO')) define('COOKIE_CONSENTIMENTO', 'consentimento_cookies');
if (!defined('COOKIE_CONSENTIMENTO_DIAS')) define('COOKIE_CONSENTIMENTO_DIAS', 180);

if (!function_exists('consentimento_cookies')) {
function consentimento_cookies(): ?array
{
    if (empty($_COOKIE[COOKIE_CONSENTIMENTO])) { return null; }
    $raw = (string)$_COOKIE[COOKIE_CONSENTIMENTO];
    $partes = explode('|', $raw);
    if (count($partes) !== 2) { return null; }
    return ['analiticos' => $partes[0] === '1', 'data' => $partes[1]];
}
}
if (!function_exists('guardar_consentimento_cookies')) {
function guardar_consentimento_cookies(bool $analiticos): void
{
    $valor = ($analiticos ? '1' : '0') . '|' . date('Y-m-d H:i:s');
    setcookie(COOKIE_CONSENTIMENTO, $valor, [
        'expires'  => time() + COOKIE_CONSENTIMENTO_DIAS * 86400,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[COOKIE_CONSENTIMENTO] = $valor;
    try {
        $u = utilizador_atual();
        db()->prepare(
            'INSERT INTO consentimentos_cookies (sessao_id, utilizador_id, aceitou_analiticos, ip)
             VALUES (?, ?, ?, ?)'
        )->execute([session_id(), $u['id'] ?? null, $analiticos ? 1 : 0, ip_cliente()]);
    } catch (Throwable $e) {
        error_log('Erro ao guardar consentimento de cookies: ' . $e->getMessage());
    }
}
}
if (!function_exists('pode_carregar_analiticos')) {
function pode_carregar_analiticos(): bool
{
    $c = consentimento_cookies();
    return $c !== null && $c['analiticos'] === true;
}
}