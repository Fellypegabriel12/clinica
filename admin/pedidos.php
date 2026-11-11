<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/marcacao.php';
require __DIR__ . '/../includes/conta.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin', 'rececao']);
$pdo = db();

limpar_reservas_expiradas($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $pedidoId = (int)($_POST['pedido'] ?? 0);
    $resposta = mb_substr(trim((string)($_POST['resposta'] ?? '')), 0, 200);

    try {
        if (($_POST['acao'] ?? '') === 'aprovar') {
            aprovar_pedido($pdo, $pedidoId, $u['id'], $resposta);
            flash_definir('sucesso', 'Pedido aprovado: a consulta foi mudada e o paciente avisado.');
        } elseif (($_POST['acao'] ?? '') === 'recusar') {
            recusar_pedido($pdo, $pedidoId, $u['id'], $resposta);
            flash_definir('sucesso', 'Pedido recusado e paciente avisado.');
        }
    } catch (RuntimeException $e) {
        flash_definir('erro', $e->getMessage());
    } catch (PDOException $e) {
        error_log('Erro nos pedidos: ' . $e->getMessage());
        flash_definir('erro', 'Ocorreu um erro. Tenta novamente.');
    }
    redirecionar('admin/pedidos.php');
}

$st = $pdo->query(
    "SELECT pa.id, pa.novo_inicio, pa.motivo, pa.criado_em,
            c.inicio AS inicio_atual, c.duracao_min,
            p.nome_completo AS paciente, d.nome_completo AS dentista,
            m.referencia, ut.nome_completo AS contacto_nome, ut.telemovel AS contacto_tel
       FROM pedidos_alteracao pa
       JOIN consultas c ON c.id = pa.consulta_id
       JOIN marcacoes m ON m.id = c.marcacao_id
       JOIN pacientes p ON p.id = c.paciente_id
       JOIN dentistas d ON d.id = c.dentista_id
       JOIN utilizadores ut ON ut.id = pa.utilizador_id
      WHERE pa.estado = 'pendente'
      ORDER BY pa.criado_em"
);
$pedidos = $st->fetchAll();

$tituloPagina = 'Pedidos de alteração';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Pedidos de alteração</h1>

<?php if (!$pedidos): ?>
    <div class="alerta alerta-info">Não há pedidos por tratar.</div>
<?php else: ?>
    <div class="grelha grelha-larga">
    <?php foreach ($pedidos as $p):
        $atual = utc($p['inicio_atual']); $novo = utc($p['novo_inicio']); ?>
        <article class="cartao linha-consulta">
            <strong><?= e($p['paciente']) ?> · <?= e($p['dentista']) ?></strong>
            <span>De <strong><?= e($atual->format('d/m/Y H:i')) ?></strong> para <strong><?= e($novo->format('d/m/Y H:i')) ?></strong>
                  (<?= e(formatar_duracao((int)$p['duracao_min'])) ?>)</span>
            <?php if ($p['motivo']): ?><span class="ajuda">Motivo: <?= e($p['motivo']) ?></span><?php endif; ?>
            <span class="ajuda">Contacto: <?= e($p['contacto_nome']) ?> · <?= e($p['contacto_tel']) ?> · Marcação <?= e($p['referencia']) ?></span>

            <form method="post" action="admin/pedidos.php" class="acoes" style="display:grid; gap:.5rem;">
                <?= csrf_campo() ?>
                <input type="hidden" name="pedido" value="<?= (int)$p['id'] ?>">
                <div class="campo" style="margin:0;">
                    <label for="r<?= (int)$p['id'] ?>" class="ajuda">Mensagem para o paciente (opcional)</label>
                    <input type="text" id="r<?= (int)$p['id'] ?>" name="resposta" maxlength="200">
                </div>
                <div>
                    <button type="submit" name="acao" value="aprovar" class="botao">Aprovar</button>
                    <button type="submit" name="acao" value="recusar" class="botao botao-secundario">Recusar</button>
                </div>
            </form>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
