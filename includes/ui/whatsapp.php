<?php
declare(strict_types=1);

function whatsapp_render(PDO $pdo): string
{
    $numero = preg_replace('/\D+/', '', config_valor_texto($pdo, 'whatsapp_numero', ''));
    if ($numero === '') {
        return '';
    }

    $numeroLink = ltrim($numero, '0');
    if (strlen($numeroLink) === 9) {
        $numeroLink = '351' . $numeroLink;
    }

    $tel = config_valor_texto($pdo, 'contacto_telefone', CLINICA_TELEFONE);
    $telLink = preg_replace('/\s+/', '', $tel);

    $html  = '<div class="contacto-flutuante" aria-label="Contacto rápido">';
    $html .= '<a class="botao-flutuante botao-whatsapp"'
           . ' href="https://wa.me/' . e($numeroLink) . '"'
           . ' target="_blank" rel="noopener"'
           . ' aria-label="Contactar por WhatsApp">'
           . '<span aria-hidden="true">💬</span>'
           . '<span class="botao-flutuante-texto">WhatsApp</span>'
           . '</a>';
    if ($telLink !== '') {
        $html .= '<a class="botao-flutuante botao-telefone"'
               . ' href="tel:' . e($telLink) . '"'
               . ' aria-label="Ligar para a clínica">'
               . '<span aria-hidden="true">📞</span>'
               . '<span class="botao-flutuante-texto">Ligar</span>'
               . '</a>';
    }
    $html .= '</div>';
    return $html;
}