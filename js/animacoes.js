/* =====================================================================
   animacoes.js - animações ao scroll (reveal) + stagger + cursor
   Sem dependências. Respeita prefers-reduced-motion.

   Modo automático:
     - Anima automaticamente <h1>, <section class="seccao">, .cartao,
       .editorial, .faq, .testemunho, .passos-preparacao li, .tabela-editorial tr,
       .grelha > *, .grelha-larga > *, .grelha-info > *.
     - Os elementos com [data-animar] continuam a funcionar (override manual).
     - Os elementos com [data-animar="off"] são ignorados.
     - Os elementos com [data-stagger] animam os filhos em sequência.
   ===================================================================== */
(function () {
    'use strict';

    var reduzir = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- 1. Seletores que animam automaticamente ---------- */
    var SELETORES_AUTO = [
        'main > h1',
        'main > section.seccao',
        'main > section.cartao',
        'main > .cartao',
        '.grelha > .cartao',
        '.grelha-larga > .cartao',
        '.grelha-info > .cartao',
        '.editorial',
        '.faq',
        '.testemunho',
        '.passos-preparacao > li',
        '.tabela-editorial > tbody > tr',
        '.consulta-cartao',
        '.linha-consulta'
    ];

    function prepararElementosAutomaticos() {
        var vistos = new Set();

        SELETORES_AUTO.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (el) {
                if (vistos.has(el)) { return; }
                if (el.hasAttribute('data-animar')) { return; } // já tem override
                if (el.hasAttribute('data-animar-off')) { return; }
                vistos.add(el);

                // Tipo de animação por contexto
                var tipo = 'up';
                if (el.matches('.grelha > .cartao, .grelha-larga > .cartao, .grelha-info > .cartao')) {
                    tipo = 'up';
                } else if (el.matches('.tabela-editorial > tbody > tr')) {
                    tipo = 'fade';
                } else if (el.matches('.editorial')) {
                    tipo = 'up';
                } else if (el.matches('.passos-preparacao > li')) {
                    tipo = 'up';
                }

                el.setAttribute('data-animar', tipo);
            });
        });
    }

    /* ---------- 2. Reveal ao scroll ---------- */
    function iniciarReveal() {
        var elementos = document.querySelectorAll('[data-animar]');
        if (!elementos.length) { return; }

        if (!('IntersectionObserver' in window) || reduzir) {
            elementos.forEach(function (el) { el.classList.add('animado'); });
            return;
        }

        var observer = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (!entrada.isIntersecting) { return; }
                var el = entrada.target;
                var atraso = parseInt(el.getAttribute('data-animar-atraso') || '0', 10);
                if (atraso > 0) {
                    setTimeout(function () { el.classList.add('animado'); }, atraso);
                } else {
                    el.classList.add('animado');
                }
                observer.unobserve(el);
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -40px 0px'
        });

        elementos.forEach(function (el) { observer.observe(el); });
    }

    /* ---------- 3. Stagger automático ---------- */
    function iniciarStagger() {
        document.querySelectorAll('[data-stagger]').forEach(function (grupo) {
            var passo = parseInt(grupo.getAttribute('data-stagger') || '80', 10);
            Array.prototype.forEach.call(grupo.children, function (filho, i) {
                if (!filho.hasAttribute('data-animar')) {
                    filho.setAttribute('data-animar', 'up');
                }
                if (!filho.hasAttribute('data-animar-atraso')) {
                    filho.setAttribute('data-animar-atraso', String(i * passo));
                }
            });
        });
    }

    /* ---------- 4. Cursor personalizado (opcional) ---------- */
    function iniciarCursor() {
        if (reduzir) { return; }
        if (!document.querySelector('[data-cursor]')) { return; }
        if (!window.matchMedia || !matchMedia('(pointer: fine)').matches) { return; }

        var cursor = document.createElement('div');
        cursor.className = 'cursor-personalizado';
        cursor.setAttribute('aria-hidden', 'true');
        document.body.appendChild(cursor);

        var x = 0, y = 0, alvoX = 0, alvoY = 0;

        document.addEventListener('mousemove', function (e) {
            alvoX = e.clientX;
            alvoY = e.clientY;
        });

        function animar() {
            x += (alvoX - x) * 0.18;
            y += (alvoY - y) * 0.18;
            cursor.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
            requestAnimationFrame(animar);
        }
        animar();

        document.addEventListener('mouseover', function (e) {
            var alvo = e.target.closest('a, button, .botao, input, select, textarea');
            cursor.classList.toggle('cursor-ativo', !!alvo);
        });

        document.addEventListener('mouseleave', function () { cursor.classList.add('cursor-fora'); });
        document.addEventListener('mouseenter', function () { cursor.classList.remove('cursor-fora'); });
    }

    /* ---------- 5. Slideshow do hero (automático, suave) ---------- */
    function iniciarHeroSlideshow() {
        var hero = document.querySelector('.hero-com-slides');
        if (!hero || hero.dataset.slideshowInit === '1') { return; }
        var slides = hero.querySelectorAll('.hero-slide');
        if (slides.length <= 1) { return; }
        hero.dataset.slideshowInit = '1';

        var indice = 0;
        var intervaloMs = 5000;
        var temporizador = null;

        function irPara(novo) {
            slides[indice].classList.remove('ativo');
            indice = (novo + slides.length) % slides.length;
            slides[indice].classList.add('ativo');
        }

        function avancar() {
            irPara(indice + 1);
        }

        function iniciar() {
            if (temporizador !== null) { return; }
            temporizador = window.setInterval(avancar, intervaloMs);
        }

        function parar() {
            if (temporizador !== null) {
                window.clearInterval(temporizador);
                temporizador = null;
            }
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { parar(); } else { iniciar(); }
        });

        iniciar();
    }

    /* ---------- 6. Arranque ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        prepararElementosAutomaticos();  // marca os elementos
        iniciarStagger();                // aplica stagger
        iniciarReveal();                 // observa e anima
        iniciarCursor();                 // cursor (se ativo)
        iniciarHeroSlideshow();          // hero: rotação automática
        // Fallback: se algo falhar (observer/path/cache), nunca deixar conteúdo invisível
        setTimeout(function () {
            document.querySelectorAll('[data-animar]:not(.animado)').forEach(function (el) {
                el.classList.add('animado');
            });
        }, 2500);
    });
})();