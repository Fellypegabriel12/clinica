<?php
declare(strict_types=1);

function hero_imagens(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, caminho, legenda FROM hero_imagens WHERE visivel = 1 ORDER BY ordem, id'
    )->fetchAll();
}

function hero_render(array $imagens, string $h1, string $p, string $botaoTexto, string $botaoUrl): string
{
    // CORRECAO: validar cada caminho antes de o usar em style="background-image:url(...)"
    $imagens = array_values(array_filter($imagens, static function ($img) {
        $c = (string)($img['caminho'] ?? '');
        if ($c === '' || str_contains($c, '..')) { return false; }
        return (bool)preg_match('~^[A-Za-z0-9_:/.\-?=&%]+$~', $c);
    }));

    $temImagens = $imagens !== [];

    $html  = '<section class="hero' . ($temImagens ? ' hero-com-slides' : '') . '"';
    if ($temImagens) {
        $html .= ' data-slides="' . count($imagens) . '"';
    }
    $html .= '>';

    if ($temImagens) {
        $html .= '<div class="hero-slides" aria-hidden="true">';
        foreach ($imagens as $i => $img) {
            $html .= '<div class="hero-slide' . ($i === 0 ? ' ativo' : '') . '"'
                   . ' style="background-image:url(' . e($img['caminho']) . ')"'
                   . ($img['legenda'] ? ' data-legenda="' . e($img['legenda']) . '"' : '')
                   . '></div>';
        }
        $html .= '</div>';
        $html .= '<div class="hero-overlay"></div>';
    }

    $html .= '<div class="hero-content">';
    $html .= '<h1>' . e($h1) . '</h1>';
    $html .= '<p>' . e($p) . '</p>';
    $html .= '<a class="botao botao-hero" href="' . e($botaoUrl) . '">' . e($botaoTexto) . '</a>';
    $html .= '</div>';

    $html .= '</section>';

    if ($temImagens && count($imagens) > 1) {
        $html .= '<script>(function(){try{if(window.matchMedia&&matchMedia("(prefers-reduced-motion: reduce)").matches)return;'
               . 'var h=document.currentScript&&document.currentScript.previousElementSibling;'
               . 'if(!h||!h.classList||!h.classList.contains("hero-com-slides"))h=document.querySelector(".hero-com-slides");'
               . 'if(!h||h.dataset.slideshowInit==="1")return;h.dataset.slideshowInit="1";'
               . 'var slides=h.querySelectorAll(".hero-slide");if(slides.length<=1)return;var i=0,t=null;'
               . 'function go(n){slides[i].classList.remove("ativo");i=(n+slides.length)%slides.length;slides[i].classList.add("ativo");}'
               . 'function start(){if(t!==null)return;t=setInterval(function(){go(i+1);},5000);}function stop(){if(t!==null){clearInterval(t);t=null;}}'
               . 'document.addEventListener("visibilitychange",function(){document.hidden?stop():start();});start();}catch(e){}})();</script>';
    }

    return $html;
}