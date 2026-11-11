<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';
require __DIR__ . '/includes/conta.php';

$u   = exigir_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');
    if ($acao === 'marcar_lidas') {
        $pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE utilizador_id = ? AND lida = 0')->execute([$u['id']]);
        flash_definir('sucesso', 'Notificações marcadas como lidas.');
    } elseif ($acao === 'marcar_lida') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE id = ? AND utilizador_id = ?')->execute([$id, $u['id']]);
        }
    }
    redirecionar('notificacoes.php');
}

// Ao abrir a área de notificações, marcar como lidas as notificações não lidas (estado persistido em BD)
$pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE utilizador_id = ? AND lida = 0')->execute([$u['id']]);

$st = $pdo->prepare(
    'SELECT n.assunto, n.mensagem, n.lida, n.criado_em, m.referencia
       FROM notificacoes n LEFT JOIN marcacoes m ON m.id = n.marcacao_id
      WHERE n.utilizador_id = ?
      ORDER BY n.id DESC LIMIT 50'
);
$st->execute([$u['id']]);
$notificacoes = $st->fetchAll();
$naoLidas = 0; // atualizadas ao abrir

$tituloPagina = 'Notificações';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_conta();
?>

<h1>Notificações</h1>
<?= submenu_conta('notificacoes', 0) ?>

<?php if (!$notificacoes): ?>
    <div class="alerta alerta-info">Não tens notificações.</div>
<?php else: ?>
    <p class="ajuda">As notificações apresentadas foram marcadas como lidas.</p>

    <div class="grelha grelha-larga seccao">
        <?php foreach ($notificacoes as $n): ?>
            <article class="cartao consulta-cartao">
                <strong class="<?= (int)$n['lida'] ? '' : 'nao-lida' ?>">
                    <?= (int)$n['lida'] ? '' : '● ' ?><?= e($n['assunto']) ?>
                </strong>
                <span><?= e($n['mensagem']) ?></span>
                <span class="ajuda">
                    <?= e((new DateTime($n['criado_em']))->format('d/m/Y H:i')) ?>
                    <?php if ($n['referencia']): ?>
                        · <a href="marcar-confirmacao.php?ref=<?= e(urlencode($n['referencia'])) ?>"><?= e($n['referencia']) ?></a>
                    <?php endif; ?>
                </span>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>
