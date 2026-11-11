<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

$tituloPagina = 'Marcar consulta';
require __DIR__ . '/includes/cabecalho.php';
?>

<section class="seccao">
    <h1>Marcar consulta</h1>
    <p>Como preferes começar?</p>

    <div class="grelha">
        <article class="cartao">
            <h2>Escolher um dentista</h2>
            <p class="ajuda">Já sabes com quem queres ser atendido? Vê a equipa e escolhe.</p>
            <a class="botao" href="dentistas.php?modo=marcar">Ver dentistas</a>
        </article>
        <article class="cartao">
            <h2>Escolher um tratamento</h2>
            <p class="ajuda">Sabes o que precisas? Escolhe o tratamento e mostramos quem o realiza.</p>
            <a class="botao" href="tratamentos.php?modo=marcar">Ver tratamentos</a>
        </article>
    </div>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
