<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';

$u = exigir_login();
$pdo = db();
$ref = (string)($_GET['ref'] ?? '');
// NUNCA confirmar pagamento aqui. Apenas mostrar estado atual.
$st = $pdo->prepare('SELECT id, referencia, estado, utilizador_id FROM marcacoes WHERE referencia = ? AND utilizador_id = ?');
$st->execute([$ref, $u['id']]);
$m = $st->fetch();
if (!$m) {
    flash_definir('erro', 'Marcação não encontrada.');
    redirecionar('area-pessoal.php');
}
$tituloPagina = 'Regresso do pagamento';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_marcacao();
?>
<?= passos_marcacao(5) ?>
<h1>Estado do pagamento</h1>
<div class="alerta alerta-info" role="note">
    <strong>Importante:</strong> esta página não confirma pagamentos. A confirmação só é válida quando recebida e validada pelo servidor através do gateway (webhook/notificação autenticada). Atualize as suas marcações/notificações dentro de momentos.
</div>
<section class="cartao">
    <p>Marcação <strong><?= e($m['referencia']) ?></strong>: estado atual <strong><?= e(rotulo('marcacao', $m['estado'])) ?></strong>.</p>
    <p><a class="botao" href="marcar-confirmacao.php?ref=<?= e(urlencode($m['referencia'])) ?>">Ver marcação</a>
       <a class="botao botao-secundario" href="meus-pagamentos.php">Ver pagamentos</a></p>
</section>
<?php require __DIR__ . '/includes/rodape.php'; ?>
