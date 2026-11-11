<?php
declare(strict_types=1);

function testemunhos_visiveis(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, nome, texto, local, foto, classificacao
           FROM testemunhos
          WHERE visivel = 1 AND consentimento_confirmado = 1
          ORDER BY ordem, id DESC'
    )->fetchAll();
}

function testemunhos_render(array $testemunhos): string
{
    if ($testemunhos === []) {
        return '';
    }
    $html  = '<section class="seccao testemunhos">';
    $html .= '<h2>Histórias de Sucesso</h2>';
    $html .= '<p class="ajuda">Testemunhos de pacientes reais. As experiências individuais variam e não constituem promessa de resultados.</p>';
    $html .= '<div class="grelha grelha-larga">';

    foreach ($testemunhos as $t) {
        $html .= '<article class="cartao testemunho">';
        $html .= '<div class="testemunho-cabecalho">';
        if (!empty($t['foto'])) {
            $html .= '<img class="avatar" src="' . e($t['foto']) . '" alt="Fotografia de ' . e($t['nome']) . '">';
        } else {
            $html .= '<div class="avatar" aria-hidden="true">' . e(iniciais($t['nome'])) . '</div>';
        }
        $html .= '<div>';
        $html .= '<strong>' . e($t['nome']) . '</strong>';
        if (!empty($t['local'])) {
            $html .= '<br><span class="ajuda">' . e($t['local']) . '</span>';
        }
        if (!empty($t['classificacao'])) {
            $html .= '<br><span class="estrelas" aria-label="' . (int)$t['classificacao'] . ' de 5 estrelas">'
                   . str_repeat('★', (int)$t['classificacao'])
                   . str_repeat('☆', 5 - (int)$t['classificacao'])
                   . '</span>';
        }
        $html .= '</div></div>';
        $html .= '<p class="testemunho-texto">' . nl2br(e($t['texto'])) . '</p>';
        $html .= '</article>';
    }

    $html .= '</div></section>';
    return $html;
}