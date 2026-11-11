<?php
declare(strict_types=1);
require_once __DIR__ . '/../core/conteudo.php';

function consulta_preparacao_render(): string
{
    $passos = [
        ['1', conteudo('prep_passo1_titulo', 'Raio-X odontológico'), conteudo('prep_passo1_texto', 'Realizamos um raio-x para avaliar o estado dos dentes e ossos.')],
        ['2', conteudo('prep_passo2_titulo', 'Exame oral'), conteudo('prep_passo2_texto', 'O médico dentista examina a boca e identifica necessidades clínicas.')],
        ['3', conteudo('prep_passo3_titulo', 'Plano personalizado'), conteudo('prep_passo3_texto', 'Apresentamos um plano de tratamento com opções, prazos e custos.')],
        ['4', conteudo('prep_passo4_titulo', 'Tratamento ou marcação'), conteudo('prep_passo4_texto', 'O tratamento é realizado na hora ou agendado conforme a tua disponibilidade.')],
    ];

    $html  = '<section class="seccao consulta-preparacao">';
    $html .= '<h2>' . e(conteudo('prep_titulo', 'Consulta de Preparação')) . '</h2>';
    $html .= '<p class="ajuda">' . e(conteudo('prep_subtitulo', 'O que acontece na tua primeira consulta.')) . '</p>';
    $html .= '<ol class="passos-preparacao">';
    foreach ($passos as [$n, $titulo, $texto]) {
        $html .= '<li>';
        $html .= '<span class="passo-numero" aria-hidden="true">' . e($n) . '</span>';
        $html .= '<strong>' . e($titulo) . '</strong>';
        $html .= '<span class="ajuda">' . e($texto) . '</span>';
        $html .= '</li>';
    }
    $html .= '</ol></section>';
    return $html;
}