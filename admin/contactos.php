<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin', 'rececao']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);

    if ($acao === 'lida') {
        $pdo->prepare('UPDATE mensagens_contacto SET lida = 1 WHERE id = ?')->execute([$id]);
        flash_definir('sucesso', 'Mensagem marcada como lida.');
    } elseif ($acao === 'apagar') {
        $pdo->prepare('DELETE FROM mensagens_contacto WHERE id = ?')->execute([$id]);
        flash_definir('sucesso', 'Mensagem apagada.');
    }
    redirecionar('admin/contactos.php');
}

$filtro = ($_GET['filtro'] ?? 'todas') === 'nao_lidas' ? 'nao_lidas' : 'todas';
$where  = $filtro === 'nao_lidas' ? ' WHERE lida = 0' : '';

$mensagens = $pdo->query(
    'SELECT id, nome, email, telemovel, assunto, mensagem, lida, criado_em
       FROM mensagens_contacto' . $where . ' ORDER BY criado_em DESC LIMIT 300'
)->fetchAll();

$tituloPagina = 'Mensagens de contacto';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Mensagens de contacto</h1>

<form method="get" action="admin/contactos.php" class="filtros-gestao">
    <div class="campo">
        <label for="filtro">Mostrar</label>
        <select id="filtro" name="filtro">
            <option value="todas" <?= $filtro === 'todas' ? 'selected' : '' ?>>Todas</option>
            <option value="nao_lidas" <?= $filtro === 'nao_lidas' ? 'selected' : '' ?>>Não lidas</option>
        </select>
    </div>
    <div class="campo"><button type="submit" class="botao">Filtrar</button></div>
</form>

<?php if (!$mensagens): ?>
    <div class="alerta alerta-info">Não há mensagens.</div>
<?php else: ?>
    <div class="grelha grelha-larga">
        <?php foreach ($mensagens as $m): ?>
            <article class="cartao linha-consulta <?= (int)$m['lida'] ? '' : 'nao-lida' ?>">
                <strong><?= e($m['assunto']) ?></strong>
                <span><?= e($m['nome']) ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
                    <?= $m['telemovel'] ? ' · ' . e($m['telemovel']) : '' ?></span>
                <p><?= nl2br(e($m['mensagem'])) ?></p>
                <span class="ajuda"><?= e((new DateTime($m['criado_em']))->format('d/m/Y H:i')) ?></span>
                <form method="post" action="admin/contactos.php" class="acoes">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <?php if (!(int)$m['lida']): ?>
                        <button type="submit" name="acao" value="lida" class="botao botao-secundario">Marcar como lida</button>
                    <?php endif; ?>
                    <button type="submit" name="acao" value="apagar" class="botao botao-secundario"
                            onclick="return confirm('Apagar esta mensagem?');">Apagar</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>