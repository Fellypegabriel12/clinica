<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

$pdo = db();
$artigos = $pdo->query(
    'SELECT id, titulo, slug, resumo, categoria, criado_em
       FROM artigos WHERE visivel = 1 ORDER BY criado_em DESC'
)->fetchAll();

$tituloPagina = 'Informação ao paciente';
require __DIR__ . '/includes/cabecalho.php';
?>

<h1>Informação ao paciente</h1>
<p class="ajuda">
    Artigos informativos sobre saúde oral, prevenção e tratamentos. Esta informação
    não substitui uma consulta de avaliação clínica.
</p>

<?php if (!$artigos): ?>
    <div class="alerta alerta-info">Ainda não há artigos publicados.</div>
<?php else: ?>
    <div class="grelha grelha-larga seccao">
        <?php foreach ($artigos as $a): ?>
            <article class="cartao">
                <?php if ($a['categoria']): ?>
                    <span class="etiqueta"><?= e($a['categoria']) ?></span>
                <?php endif; ?>
                <h2><a href="artigo.php?slug=<?= e(urlencode($a['slug'])) ?>"><?= e($a['titulo']) ?></a></h2>
                <?php if ($a['resumo']): ?>
                    <p class="ajuda"><?= e($a['resumo']) ?></p>
                <?php endif; ?>
                <p class="ajuda"><?= e((new DateTime($a['criado_em']))->format('d/m/Y')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>