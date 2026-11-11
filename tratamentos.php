<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conteudo.php';

$modoMarcar = ($_GET['modo'] ?? '') === 'marcar';

$tratamentos = db()->query(
    'SELECT t.id, t.nome, t.descricao, t.preco, t.tipo_preco, t.duracao_min,
            e.nome AS especialidade
       FROM tratamentos t
       LEFT JOIN especialidades e ON e.id = t.especialidade_id
      WHERE t.ativo = 1
      ORDER BY t.nome'
)->fetchAll();

$tituloPagina = 'Tratamentos';
require __DIR__ . '/includes/cabecalho.php';
?>

<h1><?= e(conteudo('servicos_titulo', 'Tratamentos')) ?></h1>

<div class="alerta alerta-info" role="note">
    <strong>Informação importante sobre preços:</strong>
    os valores apresentados neste site são meramente indicativos e não
    constituem uma proposta comercial. O preço final de cada tratamento é
    determinado após consulta de avaliação clínica, de acordo com o diagnóstico
    e o plano de tratamento definidos pelo médico dentista.
</div>

<?php if (!$tratamentos): ?>
    <div class="alerta alerta-info">Ainda não há tratamentos publicados.</div>
<?php else: ?>
    <div class="tabela-scroll">
        <table class="tabela-editorial">
            <thead>
                <tr>
                    <th scope="col">Tratamento</th>
                    <th scope="col">Duração</th>
                    <th scope="col" class="preco-col">Preço de referência</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tratamentos as $t): ?>
                <tr>
                    <td>
                        <span class="nome"><?= e($t['nome']) ?></span>
                        <?php if (!empty($t['especialidade'])): ?>
                            <span class="etiqueta"><?= e($t['especialidade']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($t['descricao'])): ?>
                            <p class="desc"><?= e($t['descricao']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td><?= e(formatar_duracao((int)$t['duracao_min'])) ?></td>
                    <td class="preco-col">
                        <?php if ($t['tipo_preco'] === 'fixo'): ?>
                            <?= e(formatar_preco($t['preco'])) ?>
                            <span class="ajuda">confirmado após avaliação</span>
                        <?php else: ?>
                            <em>Sob avaliação</em>
                            <span class="ajuda">valor definido em consulta</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="botao botao-secundario" href="dentistas.php?tratamento=<?= (int)$t['id'] ?><?= $modoMarcar ? '&amp;modo=marcar' : '' ?>">
                            <?= $modoMarcar ? 'Escolher' : 'Ver equipa' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>