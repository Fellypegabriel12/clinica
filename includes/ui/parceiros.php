<?php
declare(strict_types=1);

function parceiros_visiveis(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, nome, logo, url FROM parceiros WHERE visivel = 1 ORDER BY ordem, id'
    )->fetchAll();
}

function parceiros_render(array $parceiros): string
{
    if ($parceiros === []) {
        return '';
    }
    $html  = '<section class="seccao parceiros">';
    $html .= '<h2>Trabalhamos com</h2>';
    $html .= '<p class="ajuda">Marcas líderes que nos permitem garantir qualidade e segurança nos tratamentos.</p>';
    $html .= '<ul class="parceiros-lista">';
    foreach ($parceiros as $p) {
        $html .= '<li>';
        $img = '<img src="' . e($p['logo']) . '" alt="' . e($p['nome']) . '" loading="lazy">';
        if (!empty($p['url'])) {
            $html .= '<a href="' . e($p['url']) . '" target="_blank" rel="noopener">' . $img . '</a>';
        } else {
            $html .= $img;
        }
        $html .= '</li>';
    }
    $html .= '</ul></section>';
    return $html;
}