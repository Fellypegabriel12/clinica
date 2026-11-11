<?php
declare(strict_types=1);

if (!function_exists('redes_sociais_definicoes')) {
function redes_sociais_definicoes(): array
{
    return [
        'whatsapp'  => ['label' => 'WhatsApp',  'url_key' => 'whatsapp_url',  'numero_key' => 'whatsapp_numero', 'ativo_key' => 'rede_whatsapp_ativa',  'icon' => 'WA'],
        'facebook'  => ['label' => 'Facebook',  'url_key' => 'facebook_url',  'ativo_key' => 'rede_facebook_ativa',  'icon' => 'f'],
        'instagram' => ['label' => 'Instagram', 'url_key' => 'instagram_url', 'ativo_key' => 'rede_instagram_ativa', 'icon' => 'IG'],
        'tiktok'    => ['label' => 'TikTok',    'url_key' => 'tiktok_url',    'ativo_key' => 'rede_tiktok_ativa',    'icon' => 'TT'],
        'telegram'  => ['label' => 'Telegram',  'url_key' => 'telegram_url',  'ativo_key' => 'rede_telegram_ativa',  'icon' => 'TG'],
        'youtube'   => ['label' => 'YouTube',   'url_key' => 'youtube_url',   'ativo_key' => 'rede_youtube_ativa',   'icon' => 'YT'],
        'linkedin'  => ['label' => 'LinkedIn',  'url_key' => 'linkedin_url',  'ativo_key' => 'rede_linkedin_ativa',  'icon' => 'in'],
    ];
}
}

if (!function_exists('redes_sociais_url_resolvida')) {
function redes_sociais_url_resolvida(PDO $pdo, array $def): string
{
    $url = trim((string)config_valor_texto($pdo, (string)$def['url_key'], ''));
    if ($url !== '') {
        if (preg_match('#^https?://#i', $url) || str_starts_with($url, '/')) return $url;
        return 'https://' . ltrim($url);
    }
    if (!empty($def['numero_key'])) {
        $num = preg_replace('/\D+/', '', (string)config_valor_texto($pdo, (string)$def['numero_key'], ''));
        if ($num !== '') return 'https://wa.me/' . $num;
    }
    return '';
}
}

if (!function_exists('redes_sociais_ativas')) {
function redes_sociais_ativas(PDO $pdo): array
{
    $out = [];
    foreach (redes_sociais_definicoes() as $def) {
        $ativaKey = (string)($def['ativo_key'] ?? '');
        if ($ativaKey !== '' && function_exists('config_valor') && config_valor($pdo, $ativaKey, 1) !== 1) {
            continue;
        }
        $url = redes_sociais_url_resolvida($pdo, $def);
        if ($url === '') continue;
        $out[] = ['label' => (string)$def['label'], 'url' => $url, 'icon' => (string)$def['icon']];
    }
    return $out;
}
}

if (!function_exists('redes_sociais_render')) {
function redes_sociais_render(PDO $pdo): string
{
    $links = [];
    foreach (redes_sociais_ativas($pdo) as $r) {
        $links[] = '<a class="rede-social-link" href="' . e($r['url']) . '" target="_blank" rel="noopener noreferrer"'
                 . ' aria-label="' . e($r['label']) . '" title="' . e($r['label']) . '">'
                 . '<span aria-hidden="true">' . e($r['icon']) . '</span>'
                 . '<span class="so-leitor">' . e($r['label']) . '</span>'
                 . '</a>';
    }
    if ($links === []) return '';
    return '<div class="redes-sociais" aria-label="Redes sociais">' . implode('', $links) . '</div>';
}
}