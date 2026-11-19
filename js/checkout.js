(() => {
    'use strict';

    const form = document.getElementById('payment-form');
    if (!form) return;

    const publishableKey = form.dataset.publishableKey || '';
    const clientSecret = form.dataset.clientSecret || '';
    const returnUrl = form.dataset.returnUrl || '';
    const button = document.getElementById('pay-button');
    const errorBox = document.getElementById('checkout-error');
    const paymentElementBox = document.getElementById('payment-element');
    const textoBotaoOriginal = button ? button.textContent.trim() : 'Pagar';

    const mostrarErro = (mensagem) => {
        if (!errorBox) return;
        errorBox.textContent = mensagem || 'Não foi possível processar o pagamento. Tenta novamente.';
        errorBox.classList.add('is-visible');
        errorBox.setAttribute('role', 'alert');
    };

    const limparErro = () => {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.classList.remove('is-visible');
    };

    const reporBotao = () => {
        if (!button) return;
        button.disabled = false;
        button.textContent = textoBotaoOriginal;
    };

    if (!publishableKey || !clientSecret || !returnUrl) {
        mostrarErro('O checkout não está configurado corretamente. Contacta a clínica.');
        if (button) button.disabled = true;
        return;
    }
    if (typeof Stripe !== 'function') {
        mostrarErro('Não foi possível carregar o sistema de pagamento. Verifica a ligação e tenta novamente.');
        if (button) button.disabled = true;
        return;
    }

    const stripe = Stripe(publishableKey);
    const elements = stripe.elements({
        clientSecret,
        locale: 'pt',
        appearance: {
            theme: 'stripe',
            variables: {
                colorPrimary: '#0874d1',
                colorText: '#30343b',
                colorDanger: '#b42318',
                fontFamily: 'Arial, Helvetica, sans-serif',
                fontSizeBase: '12px',
                borderRadius: '5px',
                spacingUnit: '4px'
            }
        }
    });

    const paymentElement = elements.create('payment', {
        layout: { type: 'accordion', defaultCollapsed: false },
        fields: {
            billingDetails: {
                name: 'never',
                email: 'never',
                phone: 'never',
                address: 'if_required'
            }
        }
    });

    paymentElement.mount('#payment-element');
    if (paymentElementBox) paymentElementBox.classList.add('is-loading');

    paymentElement.on('ready', () => {
        if (paymentElementBox) paymentElementBox.classList.remove('is-loading');
        if (button) button.disabled = false;
    });

    paymentElement.on('change', (event) => {
        if (event.error) {
            mostrarErro(event.error.message);
        } else {
            limparErro();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        limparErro();

        const email = form.querySelector('input[name="email"]');
        const nomeContato = form.querySelector('input[name="nome"]');
        const telefone = form.querySelector('input[name="telefone"]');
        const pais = form.querySelector('select[name="pais"]');
        const holder = document.getElementById('holder');

        if (email && !email.checkValidity()) {
            mostrarErro('Introduz um endereço de e-mail válido.');
            email.focus();
            return;
        }

        const nomeTitular = holder && holder.value.trim() !== ''
            ? holder.value.trim()
            : (nomeContato ? nomeContato.value.trim() : '');
        if (nomeTitular === '') {
            mostrarErro('Indica o nome do titular do cartão.');
            if (holder) holder.focus();
            return;
        }

        if (button) {
            button.disabled = true;
            button.textContent = 'A processar…';
        }

        const billingDetails = {
            name: nomeTitular,
            email: email ? email.value.trim() : ''
        };
        if (telefone && telefone.value.trim() !== '') {
            billingDetails.phone = telefone.value.trim();
        }
        if (pais && pais.value && pais.value !== 'other') {
            billingDetails.address = { country: pais.value };
        }

        let resultado;
        try {
            resultado = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: returnUrl,
                    payment_method_data: { billing_details: billingDetails }
                },
                redirect: 'if_required'
            });
        } catch (erro) {
            console.error('Stripe confirmPayment:', erro);
            mostrarErro('Não foi possível contactar o Stripe. Verifica a ligação e tenta novamente.');
            reporBotao();
            return;
        }

        const { error, paymentIntent } = resultado;
        if (error) {
            mostrarErro(error.message);
            reporBotao();
            return;
        }

        if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing')) {
            const destino = new URL(returnUrl, window.location.href);
            destino.searchParams.set('payment_intent', paymentIntent.id);
            destino.searchParams.set('redirect_status', paymentIntent.status);
            window.location.assign(destino.toString());
            return;
        }

        if (paymentIntent && paymentIntent.status === 'requires_action') {
            mostrarErro('O banco pediu uma confirmação adicional. Segue as instruções apresentadas.');
        } else {
            mostrarErro('O pagamento não foi concluído. Tenta novamente.');
        }
        reporBotao();
    });
})();
