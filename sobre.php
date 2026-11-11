<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conteudo.php';

$pdo = db();

$filosofia     = config_valor_texto($pdo, 'clinica_filosofia', '');
$tecnologia    = config_valor_texto($pdo, 'clinica_tecnologia', '');
$instalacoes   = config_valor_texto($pdo, 'clinica_instalacoes', '');
$compromissos  = config_valor_texto($pdo, 'clinica_compromissos', '');

// Se as configurações estiverem vazias, usamos textos por omissão
if ($filosofia === '') {
    $filosofia = 'A nossa abordagem assenta em três pilares: rigor clínico, transparência e proximidade. '
               . 'Explicamos cada passo do tratamento, apresentamos alternativas e acompanhamos o paciente ao longo do tempo. '
               . 'Acreditamos que a informação clara é tão importante como o tratamento em si.';
}
if ($tecnologia === '') {
    $tecnologia = 'Trabalhamos com equipamentos de diagnóstico digital (radiografia digital, imagiologia 3D) e '
                . 'instrumentos de precisão que nos permitem planear tratamentos com segurança e conforto.';
}
if ($instalacoes === '') {
    $instalacoes = 'As nossas instalações foram pensadas para o conforto e a segurança do paciente: '
                 . 'gabinetes equipados, sala de esterilização segundo as normas, e áreas de espera acolhedoras.';
}
if ($compromissos === '') {
    $compromissos = "Transparência de preços e de opções de tratamento\n"
                  . "Explicação clara do diagnóstico e do plano de tratamento\n"
                  . "Consentimento informado antes de qualquer intervenção\n"
                  . "Confidencialidade e proteção de dados (RGPD)\n"
                  . "Acompanhamento pós-tratamento\n"
                  . "Respeito pelos tempos de espera e pela agenda do paciente";
}

$listaCompromissos = array_filter(array_map('trim', explode("\n", $compromissos)));

$tituloPagina = 'A Nossa Clínica';
require __DIR__ . '/includes/cabecalho.php';
?>

<h1><?= e(conteudo('clinica_titulo', 'A Nossa Clínica')) ?></h1>

<section class="cartao seccao">
    <h2>Quem somos</h2>
    <p>
        A <strong><?= e(config_valor_texto($pdo, 'clinica_nome', CLINICA_NOME)) ?></strong> é uma clínica dentária em Portimão,
        registada na Entidade Reguladora da Saúde (ERS) com o n.º <?= e(config_valor_texto($pdo, 'clinica_ers', CLINICA_ERS)) ?>
        e com direção clínica assegurada por <?= e(config_valor_texto($pdo, 'clinica_diretor', CLINICA_DIRETOR)) ?> (<?= e(config_valor_texto($pdo, 'clinica_diretor_omd', CLINICA_DIRETOR_OMD)) ?>).
    </p>
    <p>
        A nossa missão é prestar cuidados de medicina dentária de qualidade, com
        transparência, proximidade e respeito pela individualidade de cada pessoa.
    </p>
</section>

<section class="cartao seccao">
    <h2>A nossa filosofia</h2>
    <p><?= nl2br(e($filosofia)) ?></p>
</section>

<section class="cartao seccao">
    <h2>Tecnologia</h2>
    <p><?= nl2br(e($tecnologia)) ?></p>
</section>

<section class="cartao seccao">
    <h2>Instalações</h2>
    <p><?= nl2br(e($instalacoes)) ?></p>
</section>

<?php if ($listaCompromissos): ?>
<section class="cartao seccao">
    <h2>Os nossos compromissos</h2>
    <ul class="compromissos">
        <?php foreach ($listaCompromissos as $c): ?>
            <li><?= e($c) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="cartao seccao">
    <h2>A nossa equipa</h2>
    <p>
        Conhece os médicos dentistas da clínica na
        <a href="dentistas.php">página da equipa</a>, onde cada profissional está
        identificado com a respetiva cédula profissional da OMD.
    </p>
</section>

<section class="cartao seccao">
    <h2>Queres fazer parte da equipa?</h2>
    <p>
        Estamos sempre disponíveis para conhecer pessoas que partilhem os nossos valores.
        <a href="trabalha-connosco.php">Envia-nos a tua candidatura →</a>
    </p>
</section>

<p><a class="botao" href="index.php">← Voltar ao início</a></p>

<?php require __DIR__ . '/includes/rodape.php'; ?>