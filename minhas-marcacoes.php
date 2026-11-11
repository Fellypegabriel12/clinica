<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';
require __DIR__ . '/includes/conta.php';

$u   = exigir_login();
$pdo = db();

limpar_reservas_expiradas($pdo);

// ---------- Cancelar uma consulta ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    if (($_POST['acao'] ?? '') === 'cancelar') {
        try {
            $reembolso = cancelar_consulta($pdo, (int)($_POST['consulta'] ?? 0), $u['id']);
            flash_definir('sucesso', 'Consulta cancelada.'
                . ($reembolso > 0 ? ' Reembolso registado: ' . formatar_preco($reembolso) . ' (demonstração).' : ''));
        } catch (RuntimeException $e) {
            flash_definir('erro', $e->getMessage());
        } catch (PDOException $e) {
            error_log('Erro ao cancelar consulta: ' . $e->getMessage());
            flash_definir('erro', 'Ocorreu um erro. Tenta novamente.');
        }
    }
    redirecionar('minhas-marcacoes.php');
}

$agora = agora_bd($pdo)->format('Y-m-d H:i:s');
$horasCancelar = config_valor($pdo, 'cancelamento_antecedencia_h', 24);
$limite = agora_bd($pdo)->modify('+' . $horasCancelar . ' hours')->format('Y-m-d H:i:s');

// ---------- Todas as consultas das marcações deste utilizador ----------
$st = $pdo->prepare(
    'SELECT c.id, c.inicio, c.fim, c.duracao_min, c.estado, c.preco_total,
            p.nome_completo AS paciente, d.nome_completo AS dentista,
            m.id AS marcacao_id, m.referencia, m.estado AS marcacao_estado
       FROM consultas c
       JOIN marcacoes m ON m.id = c.marcacao_id
       JOIN pacientes p ON p.id = c.paciente_id
       JOIN dentistas d ON d.id = c.dentista_id
      WHERE m.utilizador_id = ?
      ORDER BY c.inicio'
);
$st->execute([$u['id']]);
$todas = $st->fetchAll();

$proximas = $passadas = $canceladas = [];
foreach ($todas as $c) {
    if ($c['estado'] === 'cancelada') {
        $canceladas[] = $c;
    } elseif (in_array($c['estado'], ['pendente', 'confirmada'], true) && $c['inicio'] >= $agora) {
        $proximas[] = $c;
    } else {
        $passadas[] = $c;
    }
}
$passadas   = array_reverse($passadas);
$canceladas = array_reverse($canceladas);

// Tratamentos e pedidos de alteração pendentes
$tratDe = [];
$pedidoDe = [];
$avaliacaoDe = [];
if ($todas) {
    $ids = array_column($todas, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $st = $pdo->prepare(
        "SELECT ct.consulta_id, t.nome, ct.preco_final
           FROM consultas_tratamentos ct JOIN tratamentos t ON t.id = ct.tratamento_id
          WHERE ct.consulta_id IN ($in) ORDER BY t.nome"
    );
    $st->execute($ids);
    foreach ($st as $r) { $tratDe[$r['consulta_id']][] = $r; }

    $st = $pdo->prepare("SELECT consulta_id, novo_inicio FROM pedidos_alteracao WHERE estado = 'pendente' AND consulta_id IN ($in)");
    $st->execute($ids);
    foreach ($st as $r) { $pedidoDe[$r['consulta_id']] = $r['novo_inicio']; }

    $st = $pdo->prepare("SELECT consulta_id, estado FROM avaliacoes WHERE consulta_id IN ($in)");
    $st->execute($ids);
    foreach ($st as $r) { $avaliacaoDe[$r['consulta_id']] = $r['estado']; }
}

// Cartão de uma consulta
function mostrar_consulta(array $c, array $tratDe, array $pedidoDe, array $avaliacaoDe, string $limite, int $horas, bool $acoes): void
{
    $ini = utc($c['inicio']);
    $fim = utc($c['fim']);
    ?>
    <article class="cartao consulta-cartao">
        <strong><?= e(data_extenso($ini)) ?>, <?= e($ini->format('H:i')) ?> – <?= e($fim->format('H:i')) ?></strong>
        <span><?= e($c['paciente']) ?> com <?= e($c['dentista']) ?></span>
        <span class="ajuda">
            <?= e(rotulo('marcacao', $c['estado'])) ?> ·
            <a href="marcar-confirmacao.php?ref=<?= e(urlencode($c['referencia'])) ?>"><?= e($c['referencia']) ?></a>
            · <?= e(formatar_preco($c['preco_total'])) ?>
        </span>
        <?php if (!empty($tratDe[$c['id']])): ?>
            <span class="ajuda">Tratamentos: <?= e(implode(', ', array_column($tratDe[$c['id']], 'nome'))) ?></span>
        <?php endif; ?>

        <?php if (isset($pedidoDe[$c['id']])): ?>
            <span class="ajuda">Pedido de alteração pendente para <?= e((new DateTime($pedidoDe[$c['id']]))->format('d/m/Y H:i')) ?>.</span>
        <?php endif; ?>

        <?php if ($c['estado'] === 'concluida'): ?>
            <?php if (isset($avaliacaoDe[$c['id']])): ?>
                <span class="ajuda">
                    Avaliação: <?= $avaliacaoDe[$c['id']] === 'publicada' ? 'publicada' : ($avaliacaoDe[$c['id']] === 'rejeitada' ? 'não publicada' : 'pendente de moderação') ?>
                </span>
            <?php else: ?>
                <div class="acoes">
                    <a class="botao" href="avaliar.php?consulta=<?= (int)$c['id'] ?>">⭐ Avaliar consulta</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($acoes): ?>
            <?php if ($c['inicio'] >= $limite): ?>
                <div class="acoes">
                    <a class="botao botao-secundario" href="alterar-consulta.php?id=<?= (int)$c['id'] ?>">Pedir alteração</a>
                    <form method="post" action="minhas-marcacoes.php" class="form-inline"
                          onsubmit="return confirm('Queres mesmo cancelar esta consulta?');">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="cancelar">
                        <input type="hidden" name="consulta" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="botao botao-secundario">Cancelar consulta</button>
                    </form>
                </div>
            <?php else: ?>
                <span class="ajuda">Já não é possível cancelar nem alterar online (menos de <?= $horas ?> horas). Contacta a clínica.</span>
            <?php endif; ?>
        <?php endif; ?>
    </article>
    <?php
}

$tituloPagina = 'As minhas marcações';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_conta();
?>

<h1>As minhas marcações</h1>
<?= submenu_conta('marcacoes', nao_lidas($pdo, $u['id'])) ?>

<p class="ajuda">Podes cancelar ou pedir alteração com pelo menos <?= (int)$horasCancelar ?> horas de antecedência.</p>

<section class="seccao">
    <h2>Próximas</h2>
    <?php if (!$proximas): ?>
        <div class="alerta alerta-info">Não tens consultas marcadas. <a href="dentistas.php?modo=marcar">Marcar consulta</a></div>
    <?php else: ?>
        <div class="grelha grelha-larga">
            <?php foreach ($proximas as $c) { mostrar_consulta($c, $tratDe, $pedidoDe, $avaliacaoDe, $limite, $horasCancelar, true); } ?>
        </div>
    <?php endif; ?>
</section>

<section class="seccao">
    <h2>Histórico</h2>
    <?php if (!$passadas): ?>
        <p class="ajuda">Ainda não tens consultas passadas.</p>
    <?php else: ?>
        <div class="grelha grelha-larga">
            <?php foreach ($passadas as $c) { mostrar_consulta($c, $tratDe, $pedidoDe, $avaliacaoDe, $limite, $horasCancelar, false); } ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($canceladas): ?>
<section class="seccao">
    <h2>Canceladas</h2>
    <div class="grelha grelha-larga">
        <?php foreach ($canceladas as $c) { mostrar_consulta($c, $tratDe, $pedidoDe, $avaliacaoDe, $limite, $horasCancelar, false); } ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>