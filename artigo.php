<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';

$pdo  = db();
$slug = (string)($_GET['slug'] ?? '');

$st = $pdo->prepare('SELECT id, titulo, resumo, conteudo, categoria, criado_em FROM artigos WHERE slug = ? AND visivel = 1');
$st->execute([$slug]);
$artigo = $st->fetch();

if (!$artigo) {
    flash_definir('erro', 'Artigo não encontrado.');
    redirecionar('blog.php');
}

$tituloPagina = $artigo['titulo'];
require __DIR__ . '/includes/cabecalho.php';
?>

<p><a href="blog.php">← Voltar à informação ao paciente</a></p>

<article class="cartao seccao">
    <?php if ($artigo['categoria']): ?>
        <span class="etiqueta"><?= e($artigo['categoria']) ?></span>
    <?php endif; ?>
    <h1><?= e($artigo['titulo']) ?></h1>
    <p class="ajuda">Publicado a <?= e((new DateTime($artigo['criado_em']))->format('d/m/Y')) ?></p>

    <div class="artigo-conteudo">
        <?= nl2br(e($artigo['conteudo'])) ?>
    </div>

    <div class="alerta alerta-info" role="note">
        Este artigo é meramente informativo e não constitui diagnóstico, aconselhamento
        clínico ou proposta comercial. Para uma avaliação, marca uma consulta.
    </div>
</article>

<?php require __DIR__ . '/includes/rodape.php'; ?>