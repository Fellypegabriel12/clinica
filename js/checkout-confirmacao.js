(() => {
    'use strict';

    const script = document.currentScript;
    if (!script) return;

    const url = script.dataset.estadoUrl || '';
    const ref = script.dataset.ref || '';
    const paymentIntent = script.dataset.paymentIntent || '';
    const texto = document.getElementById('estado-texto');
    const nota = document.getElementById('estado-nota');
    if (!url || !ref) return;

    let tentativas = 0;
    let falhasSeguidas = 0;
    let ativo = true;
    const maxTentativas = 100;
    const intervalo = 3000;

    const definirTexto = (mensagem) => {
        if (texto && mensagem) {
            texto.textContent = mensagem;
        }
    };

    const parar = () => {
        ativo = false;
        if (nota) {
            nota.textContent = 'A confirmação está a demorar mais do que o esperado. Atualiza a página dentro de alguns instantes ou contacta a clínica.';
        }
    };

    const consultar = async () => {
        if (!ativo) return;
        tentativas += 1;

        const params = new URLSearchParams({ ref });
        if (paymentIntent) params.set('payment_intent', paymentIntent);

        let resposta;
        try {
            resposta = await fetch(url + '?' + params.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' }
            });
        } catch (erro) {
            falhasSeguidas += 1;
            if (falhasSeguidas >= 3 && nota) {
                nota.textContent = 'A ligação está instável. Estamos a tentar novamente…';
            }
            if (tentativas >= maxTentativas) { parar(); return; }
            window.setTimeout(consultar, intervalo);
            return;
        }

        falhasSeguidas = 0;
        if (!resposta.ok) {
            if (tentativas >= maxTentativas) { parar(); return; }
            window.setTimeout(consultar, intervalo);
            return;
        }

        let dados;
        try {
            dados = await resposta.json();
        } catch (erro) {
            if (tentativas >= maxTentativas) { parar(); return; }
            window.setTimeout(consultar, intervalo);
            return;
        }

        if (!dados || dados.ok !== true) {
            if (tentativas >= maxTentativas) { parar(); return; }
            window.setTimeout(consultar, intervalo);
            return;
        }

        definirTexto(dados.mensagem || dados.estado_legivel || 'A aguardar confirmação da Stripe');

        if (dados.terminal) {
            ativo = false;
            window.location.reload();
            return;
        }

        if (tentativas >= maxTentativas) {
            parar();
            return;
        }
        window.setTimeout(consultar, intervalo);
    };

    window.setTimeout(consultar, 1200);
})();
