<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/hero.php';
require __DIR__ . '/includes/consulta-preparacao.php';
require __DIR__ . '/includes/numeros.php';
require __DIR__ . '/includes/parceiros.php';
require __DIR__ . '/includes/testemunhos.php';
require __DIR__ . '/includes/whatsapp.php';
require_once __DIR__ . '/includes/redes-sociais.php';
require_once __DIR__ . '/includes/conteudo.php';

$pdo = db();

$porDia = [];
$linhas = $pdo->query('SELECT dia_semana, hora_inicio, hora_fim FROM horarios_funcionamento ORDER BY dia_semana, hora_inicio')->fetchAll();
foreach ($linhas as $h) {
    $porDia[(int)$h['dia_semana']][] = substr($h['hora_inicio'], 0, 5) . ' – ' . substr($h['hora_fim'], 0, 5);
}

$limiteTrat = 6;
$totalTrat = (int)$pdo->query('SELECT COUNT(*) FROM tratamentos WHERE ativo = 1')->fetchColumn();
$temMaisTrat = $totalTrat > $limiteTrat;
$fetchTrat = $temMaisTrat ? ($limiteTrat - 1) : $limiteTrat;
$stT = $pdo->prepare(
    'SELECT id, nome, descricao, preco, tipo_preco, duracao_min
     FROM tratamentos WHERE ativo = 1 ORDER BY nome LIMIT :lim'
);
$stT->bindValue(':lim', $fetchTrat, PDO::PARAM_INT);
$stT->execute();
$tratamentos = $stT->fetchAll();

$limiteEq = 4;
$totalEq = (int)$pdo->query('SELECT COUNT(*) FROM dentistas WHERE ativo = 1')->fetchColumn();
$temMaisEq = $totalEq > $limiteEq;
$fetchEq = $temMaisEq ? ($limiteEq - 1) : $limiteEq;
$stD = $pdo->prepare(
    'SELECT d.id, d.nome_completo, d.fotografia,
            (SELECT GROUP_CONCAT(e.nome ORDER BY e.nome SEPARATOR \', \')
               FROM dentistas_especialidades de JOIN especialidades e ON e.id = de.especialidade_id
              WHERE de.dentista_id = d.id) AS especialidades
     FROM dentistas d WHERE d.ativo = 1 ORDER BY d.nome_completo LIMIT :lim'
);
$stD->bindValue(':lim', $fetchEq, PDO::PARAM_INT);
$stD->execute();
$dentistas = $stD->fetchAll();

$galeria = $pdo->query('SELECT caminho, legenda FROM galeria WHERE visivel = 1 ORDER BY ordem, id LIMIT 8')->fetchAll();
$faq = $pdo->query('SELECT pergunta, resposta FROM perguntas_frequentes WHERE visivel = 1 ORDER BY ordem, id')->fetchAll();

$tituloPagina = 'Início';
require __DIR__ . '/includes/cabecalho.php';
?>

<?= hero_render(
    hero_imagens($pdo),
    conteudo('hero_titulo', 'O teu sorriso em boas mãos'),
    conteudo('hero_subtitulo', 'Marca a tua consulta online, escolhe o teu médico dentista e acompanha tudo na tua área pessoal.'),
    conteudo('hero_botao', 'Marcar consulta'),
    conteudo('hero_botao_url', 'marcar.php')
) ?>

<section data-animar="up">
    <?= consulta_preparacao_render() ?>
</section>

<section data-animar="up">
    <?= numeros_render($pdo) ?>
</section>

<section class="seccao" data-animar="up">
    <h2><?= e(conteudo('servicos_titulo', 'Os nossos serviços')) ?></h2>
    <p class="ajuda">
        Os valores abaixo são meramente indicativos. O preço final de cada
        tratamento é determinado após consulta de avaliação clínica, de acordo
        com o diagnóstico e o plano de tratamento definidos pelo médico dentista.
    </p>
    <?php if ($tratamentos || $temMaisTrat): ?>
        <div class="grelha" data-stagger="80">
            <?php foreach ($tratamentos as $t): ?>
                <article class="cartao" data-animar="left">
                    <h3><?= e($t['nome']) ?></h3>
                    <?php if ($t['descricao']): ?><p class="ajuda"><?= e($t['descricao']) ?></p><?php endif; ?>
                    <p>
                        <?php if ($t['tipo_preco'] === 'fixo'): ?>
                            <span class="preco"><?= e(formatar_preco($t['preco'])) ?></span>
                        <?php else: ?>
                            <span class="preco">Sob avaliação</span>
                        <?php endif; ?>
                        <br>
                        <span class="ajuda"><?= e(formatar_duracao((int)$t['duracao_min'])) ?></span>
                    </p>
                </article>
            <?php endforeach; ?>
            <?php if ($temMaisTrat): ?>
                <a class="cartao cartao-ver-mais" data-animar="left" href="tratamentos.php" aria-label="Ver mais tratamentos">
                    <span class="cartao-ver-mais-simbolo" aria-hidden="true">+</span>
                    <span class="cartao-ver-mais-texto">Ver mais</span>
                </a>
            <?php endif; ?>
        </div>
        <p><a href="tratamentos.php">Ver todos os tratamentos →</a></p>
    <?php else: ?>
        <p class="ajuda">Ainda não há tratamentos publicados.</p>
    <?php endif; ?>
</section>

<section class="seccao" data-animar="up">
    <h2><?= e(conteudo('equipa_titulo', 'A nossa equipa')) ?></h2>
    <?php if ($dentistas || $temMaisEq): ?>
        <div class="grelha" data-stagger="80">
            <?php foreach ($dentistas as $d): ?>
                <article class="cartao texto-centro" data-animar="scale">
                    <?= avatar_dentista($d) ?>
                    <h3><?= e($d['nome_completo']) ?></h3>
                    <?php if ($d['especialidades']): ?>
                        <p class="ajuda">Áreas de atuação: <?= e($d['especialidades']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if ($temMaisEq): ?>
                <a class="cartao cartao-ver-mais" data-animar="scale" href="dentistas.php" aria-label="Ver mais membros da equipa">
                    <span class="cartao-ver-mais-simbolo" aria-hidden="true">+</span>
                    <span class="cartao-ver-mais-texto">Ver mais</span>
                </a>
            <?php endif; ?>
        </div>
        <p><a href="dentistas.php">Ver toda a equipa →</a></p>
    <?php else: ?>
        <p class="ajuda">Ainda não há médicos dentistas publicados.</p>
    <?php endif; ?>
</section>

<section data-animar="up">
    <?= parceiros_render(parceiros_visiveis($pdo)) ?>
</section>

<?php $redesHome = redes_sociais_ativas($pdo); if ($redesHome): ?>
<section class="seccao" data-animar="up" aria-labelledby="titulo-redes-sociais">
    <h2 id="titulo-redes-sociais">Siga-nos</h2>
    <p class="ajuda">Canais oficiais da clínica. Apenas são apresentados os canais configurados.</p>
    <?= redes_sociais_render($pdo) ?>
</section>
<?php endif; ?>

<?php if ($galeria): ?>
<section class="seccao" data-animar="up">
    <h2><?= e(conteudo('clinica_titulo', 'A clínica')) ?></h2>
    <div class="grelha galeria" data-stagger="60">
        <?php foreach ($galeria as $g): ?>
            <figure data-animar="scale">
                <img src="<?= e($g['caminho']) ?>" alt="<?= e($g['legenda'] ?? 'Fotografia da clínica') ?>" loading="lazy">
                <?php if ($g['legenda']): ?><figcaption class="ajuda"><?= e($g['legenda']) ?></figcaption><?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>
    <p class="ajuda">
        As imagens de instalações e equipa são meramente ilustrativas.
        Não são apresentadas imagens de resultados clínicos (antes/depois),
        em cumprimento das normas da Ordem dos Médicos Dentistas.
    </p>
</section>
<?php endif; ?>

<section class="seccao grelha-info" data-stagger="100">
    <div class="cartao" data-animar="up">
        <h2>Horário</h2>
        <?php if ($porDia): ?>
            <dl class="dados">
                <?php foreach ($porDia as $dia => $blocos): ?>
                    <dt><?= e(nome_dia($dia)) ?></dt><dd><?= e(implode(' · ', $blocos)) ?></dd>
                <?php endforeach; ?>
            </dl>
        <?php else: ?>
            <p class="ajuda">Horário por definir.</p>
        <?php endif; ?>
    </div>
    <div class="cartao" data-animar="up">
        <h2>Contactos e localização</h2>
        <p><?= e(clinica_dado($pdo, 'clinica_morada', CLINICA_MORADA)) ?></p>
        <p>Telefone: <?= e(clinica_dado($pdo, 'contacto_telefone', CLINICA_TELEFONE)) ?><br>Email: <?= e(clinica_dado($pdo, 'contacto_email', CLINICA_EMAIL)) ?></p>
        <p><a href="contactos.php">Ver página de contactos →</a></p>
    </div>
    <div class="cartao" data-animar="up">
        <h2>Onde estamos</h2>
        <div class="mapa-embed">
            <?php
            $mapaEmbed = config_valor_texto($pdo, 'mapa_embed_url', 'https://www.google.com/maps?q=Portim%C3%A3o%2C%20Portugal&output=embed');
            ?>
            <iframe
                src="<?= e($mapaEmbed) ?>"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                title="Localização da clínica (Portimão)"></iframe>
        </div>
    </div>
</section>

<section data-animar="up">
    <?= testemunhos_render(testemunhos_visiveis($pdo)) ?>
</section>

<?php if ($faq): ?>
<section class="seccao" data-animar="up">
    <h2>Perguntas frequentes</h2>
    <?php foreach ($faq as $f): ?>
        <details class="faq">
            <summary><?= e($f['pergunta']) ?></summary>
            <p><?= e($f['resposta']) ?></p>
        </details>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?= whatsapp_render($pdo) ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>