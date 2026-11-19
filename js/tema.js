/* =====================================================================
   tema.js - botão de modo claro / modo escuro
   Versão à prova de duplicação: remove listeners antigos antes de registar.
   ===================================================================== */
(function () {
    'use strict';

    var html   = document.documentElement;
    var botao  = document.getElementById('botao-tema');
    var icone  = document.getElementById('icone-tema');
    if (!botao || !icone) { return; }

    // Remove listeners antigos (se o script for carregado duas vezes)
    if (botao._temaListener) {
        botao.removeEventListener('click', botao._temaListener);
    }
    if (window._temaStorageListener) {
        window.removeEventListener('storage', window._temaStorageListener);
    }

    /* --- Cookie helpers --- */
    function lerCookie(nome) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + nome + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }
    function gravarCookie(nome, valor) {
        document.cookie = nome + '=' + encodeURIComponent(valor)
            + '; path=/; max-age=' + (60 * 60 * 24 * 365) + '; SameSite=Lax';
    }

    /* --- Atualizar botão --- */
    function atualizarBotao() {
        var escuro = html.dataset.tema === 'escuro';
        botao.setAttribute('aria-pressed', escuro ? 'true' : 'false');
        botao.setAttribute('aria-label', escuro ? 'Ativar modo claro' : 'Ativar modo escuro');
        botao.title = escuro ? 'Mudar para modo claro' : 'Mudar para modo escuro';
        icone.textContent = escuro ? '☀️' : '🌙';
        var meta = document.querySelector('meta[name="theme-color"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.name = 'theme-color';
            document.head.appendChild(meta);
        }
        meta.content = escuro ? '#1a2226' : '#ffffff';
    }

    /* --- Aplicar tema --- */
    function aplicar(novo, guardar) {
        if (novo !== 'claro' && novo !== 'escuro') { return; }

        var atual = lerCookie('tema');
        if (atual === novo && guardar) { return; }

        html.dataset.tema      = novo;
        html.dataset.temaConta = novo;
        atualizarBotao();

        if (guardar) {
            gravarCookie('tema', novo);
            try { localStorage.setItem('tema', novo); } catch (e) {}

            // FIX: sincronizar com a conta quando autenticado.
            // O endpoint guardar-tema.php existia mas nunca era chamado.
            // Falha silenciosa: se o POST falhar, o tema continua gravado no browser.
            if (html.dataset.logado === '1') {
                var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta && csrfMeta.content) {
                    try {
                        fetch('guardar-tema.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'tema=' + encodeURIComponent(novo)
                                + '&csrf=' + encodeURIComponent(csrfMeta.content),
                            credentials: 'same-origin',
                            keepalive: true
                        }).catch(function () { /* silencioso */ });
                    } catch (e) { /* silencioso */ }
                }
            }
        }
    }

    /* --- Clique (guarda referência para poder remover) --- */
    var temaListener = function () {
        var atual = html.dataset.tema === 'escuro' ? 'escuro' : 'claro';
        var novo  = atual === 'escuro' ? 'claro' : 'escuro';
        aplicar(novo, true);
    };
    botao.addEventListener('click', temaListener);
    botao._temaListener = temaListener;

    /* --- Sincronizar entre abas --- */
    var storageListener = function (ev) {
        if (ev.key !== 'tema') { return; }
        if (ev.newValue === 'claro' || ev.newValue === 'escuro') {
            aplicar(ev.newValue, false);
        }
    };
    window.addEventListener('storage', storageListener);
    window._temaStorageListener = storageListener;

    /* --- Estado inicial do botão --- */
    atualizarBotao();
})();