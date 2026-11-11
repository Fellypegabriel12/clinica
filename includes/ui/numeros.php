<?php
declare(strict_types=1);

function numeros_render(PDO $pdo): string
{
    $itens = [];

    $anos = (int)config_valor_texto($pdo, 'anos_existencia', '0');
    if ($anos > 0) {
        $itens[] = ['+' . $anos, 'anos de experiência'];
    }

    $pacientes = (int)config_valor_texto($pdo, 'pacientes_satisfeitos', '0');
    if ($pacientes > 0) {
        $itens[] = ['+' . number_format($pacientes, 0, ',', ' '), 'pacientes satisfeitos'];
    }

    $consultas = (int)config_valor_texto($pdo, 'consultas_realizadas', '0');
    if ($consultas > 0) {
        $itens[] = ['+' . number_format($consultas, 0, ',', ' '), 'consultas realizadas'];
    }

    if ($itens === []) {
        return '';
    }

    $html  = '<section class="seccao numeros-clinica">';
    $html .= '<div class="grelha numeros-grelha">';
    foreach ($itens as [$valor, $rotulo]) {
        $html .= '<div class="cartao texto-centro numero-cartao">';
        $html .= '<p class="numero numero-grande">' . e($valor) . '</p>';
        $html .= '<p class="ajuda">' . e($rotulo) . '</p>';
        $html .= '</div>';
    }
    $html .= '</div></section>';
    return $html;
}