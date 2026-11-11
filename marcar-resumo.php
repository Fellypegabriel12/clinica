<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';

$u   = exigir_login();
$pdo = db();

$c        = carregar_marcacao($pdo, $u['id']);
$dentista = $c['dentista'];
$contacto = $_SESSION['marcacao']['contacto'] ?? ['nome' => '', 'telemovel' => ''];

// Validação e cálculo centralizados (evita repetir regras/mensagens em várias páginas)
$resumo      = resumo_da_marcacao($pdo, $c);
$linhasResumo = $resumo['linhas'];
$totalGeral  = $resumo['total'];
$haEstimados = $resumo['estimado'];

// Até quando duram as reservas temporárias
$st = $pdo->prepare('SELECT MIN(expira_em) FROM horarios_ocupados WHERE consulta_id IS NULL AND token_reserva LIKE ?');
$st->execute([$c['prefixo'] . ':%']);
$expira = $st->fetchColumn();

$tituloPagina = 'Marcar: resumo';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_marcacao();
?>

<?= passos_marcacao(4) ?>

<h1>Resumo da marcação</h1>
<p>Confirma que está tudo certo antes de avançar.</p>

<?php foreach ($c['avisos'] as $aviso): ?>
    <div class="alerta alerta-info" role="status"><?= e($aviso) ?></div>
<?php endforeach; ?>

<?php foreach ($linhasResumo as $l): $p = $l['paciente']; $t = $l['totais']; ?>
    <section class="cartao seccao">
        <h2><?= e($p['nome_completo']) ?></h2>
        <dl class="dados">
            <dt>Dentista</dt><dd><?= e($dentista['nome_completo']) ?></dd>
            <dt>Data</dt><dd><?= e(data_extenso($l['inicio'])) ?></dd>
            <dt>Hora</dt><dd><?= e($l['inicio']->format('H:i')) ?> – <?= e($l['fim']->format('H:i')) ?></dd>
            <dt>Duração estimada</dt><dd><?= e(formatar_duracao($t['duracao'])) ?></dd>
        </dl>

        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Tratamento</th><th>Duração</th><th>Preço</th></tr></thead>
                <tbody>
                <?php foreach ($t['linhas'] as $tr): ?>
                    <tr>
                        <td><?= e($tr['nome']) ?></td>
                        <td><?= e(formatar_duracao((int)$tr['duracao_min'])) ?></td>
                        <td>
                            <?php if (($tr['desconto'] ?? 0) > 0): ?>
                                <span class="ajuda"><s><?= e(formatar_preco($tr['preco_original'] ?? $tr['preco'])) ?></s></span><br>
                                <strong><?= e(formatar_preco($tr['preco_final'] ?? $tr['preco'])) ?></strong>
                                <br><span class="ajuda">Desconto aplicado (benefício validado)</span>
                            <?php else: ?>
                                <?= $tr['tipo_preco'] === 'fixo' ? '' : 'Estimado: ' ?><?= e(formatar_preco($tr['preco_final'] ?? $tr['preco'])) ?>
                            <?php endif; ?>
                            <?php if ($tr['tipo_preco'] !== 'fixo'): ?><br><span class="ajuda">sujeito a avaliação clínica</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="2" scope="row">Subtotal</th><th><?= e(formatar_preco($t['total'])) ?></th></tr>
                </tfoot>
            </table>
        </div>
    </section>
<?php endforeach; ?>

<section class="cartao seccao">
    <h2>Valor total previsto: <?= e(formatar_preco($totalGeral)) ?></h2>
    <?php if ($haEstimados): ?>
        <div class="alerta alerta-info" role="note">
            Alguns tratamentos têm preço estimado. O valor final é confirmado pelo dentista depois da avaliação clínica.
        </div>
    <?php endif; ?>
    <p class="ajuda">Descontos de seguros/benefícios validados para o paciente e data são calculados automaticamente quando aplicável.</p>

    <dl class="dados">
        <dt>Contacto</dt><dd><?= e($contacto['nome']) ?> · <?= e($contacto['telemovel']) ?></dd>
    </dl>

    <?php if ($expira): ?>
        <p class="ajuda">Horários reservados temporariamente até às <?= e((new DateTime((string)$expira))->format('H:i')) ?>.</p>
    <?php endif; ?>
</section>

<p>
    <a class="botao botao-secundario" href="marcar-pacientes.php">Alterar pacientes</a>
    <a class="botao botao-secundario" href="marcar-calendario.php">Alterar horário</a>
    <a class="botao botao-secundario" href="marcar-tratamentos.php">Alterar tratamentos</a>
    <a class="botao" href="marcar-pagamento.php">Continuar para o pagamento →</a>
</p>

<?php require __DIR__ . '/includes/rodape.php'; ?>
