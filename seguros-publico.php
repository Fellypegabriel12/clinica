<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

$pdo = db();

$seguradoras = $pdo->query(
    'SELECT s.id, s.nome, s.tipo,
            (SELECT GROUP_CONCAT(pl.nome ORDER BY pl.nome SEPARATOR "||")
               FROM planos_beneficio pl WHERE pl.seguradora_id = s.id AND pl.ativo = 1) AS planos
       FROM seguradoras s WHERE s.ativo = 1 ORDER BY s.nome'
)->fetchAll();

$tituloPagina = 'Seguros e acordos';
require __DIR__ . '/includes/cabecalho.php';
?>

<h1>Seguros e acordos</h1>

<div class="alerta alerta-info" role="note">
    A informação abaixo é indicativa. A aplicação de qualquer desconto depende sempre
    de <strong>validação prévia pela clínica</strong> e das condições específicas da
    apólice. Podem existir restrições, exclusões ou períodos de carência.
    Confirma sempre com a receção antes da consulta.
</div>

<?php if (!$seguradoras): ?>
    <div class="alerta alerta-info">De momento não temos acordos publicados. Contacta-nos.</div>
<?php else: ?>
    <div class="grelha grelha-larga seccao">
        <?php foreach ($seguradoras as $s): ?>
            <article class="cartao">
                <h2><?= e($s['nome']) ?></h2>
                <p class="ajuda"><?= e($s['tipo']) ?></p>
                <?php if ($s['planos']): ?>
                    <p class="ajuda">Planos: <?= e(str_replace('||', ', ', $s['planos'])) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p>
    Tens um seguro ou plano que não está aqui?
    <a href="contactos.php">Contacta-nos</a> — podemos verificar a possibilidade de acordo.
</p>

<p><a class="botao" href="seguros.php">Registar o meu benefício</a></p>

<?php require __DIR__ . '/includes/rodape.php'; ?>