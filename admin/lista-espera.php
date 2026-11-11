<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin', 'rececao']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $estados = ['ativa','notificada','concluida','cancelada'];
    if ($id > 0 && in_array($acao, $estados, true)) {
        $extra = $acao === 'notificada' ? ', notificada_em = NOW()' : '';
        $pdo->prepare("UPDATE lista_espera SET estado = ?" . $extra . " WHERE id = ?")->execute([$acao, $id]);
        registar_admin('lista_espera_estado', 'lista_espera', $id, $acao);
        flash_definir('sucesso', 'Estado atualizado.');
    }
    redirecionar('admin/lista-espera.php');
}

$filtro = (string)($_GET['estado'] ?? 'ativa');
if (!in_array($filtro, ['ativa','notificada','concluida','cancelada','todas'], true)) { $filtro = 'ativa'; }
$where = $filtro === 'todas' ? '' : 'WHERE le.estado = ?';
$params = $filtro === 'todas' ? [] : [$filtro];

$st = $pdo->prepare(
    "SELECT le.*, u.nome_completo AS responsavel, u.email, p.nome_completo AS paciente,
            d.nome_completo AS dentista, t.nome AS tratamento
       FROM lista_espera le
       JOIN utilizadores u ON u.id = le.utilizador_id
       JOIN pacientes p ON p.id = le.paciente_id
       LEFT JOIN dentistas d ON d.id = le.dentista_id
       LEFT JOIN tratamentos t ON t.id = le.tratamento_id
       $where
      ORDER BY le.criado_em DESC LIMIT 300"
);
$st->execute($params);
$itens = $st->fetchAll();

$tituloPagina = 'Lista de espera';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>
<p><a href="admin/index.php">← Painel</a></p>
<h1>Lista de espera</h1>
<p class="ajuda">Pedidos de pacientes para serem contactados quando houver disponibilidade. A notificação automática por email/SMS não está integrada; use "Notificada" após contacto manual.</p>

<form method="get" action="admin/lista-espera.php" class="filtros-gestao">
    <div class="campo"><label for="estado">Estado</label>
        <select id="estado" name="estado"><?php foreach (['ativa'=>'Ativas','notificada'=>'Notificadas','concluida'=>'Concluídas','cancelada'=>'Canceladas','todas'=>'Todas'] as $v=>$l): ?>
            <option value="<?= e($v) ?>" <?= $filtro===$v?'selected':'' ?>><?= e($l) ?></option>
        <?php endforeach; ?></select>
    </div>
    <div class="campo"><button class="botao" type="submit">Filtrar</button></div>
</form>

<?php if (!$itens): ?><div class="alerta alerta-info">Sem registos.</div><?php else: ?>
<div class="grelha grelha-larga">
<?php foreach ($itens as $i): ?>
    <article class="cartao">
        <strong><?= e($i['paciente']) ?></strong>
        <div class="ajuda">Responsável: <?= e($i['responsavel']) ?> (<?= e($i['email']) ?>)</div>
        <div class="ajuda">Período: <?= e($i['data_desde']) ?> a <?= e($i['data_ate']) ?> · <?= e($i['periodo']) ?></div>
        <?php if ($i['dentista']): ?><div class="ajuda">Dentista: <?= e($i['dentista']) ?></div><?php endif; ?>
        <?php if ($i['tratamento']): ?><div class="ajuda">Tratamento: <?= e($i['tratamento']) ?></div><?php endif; ?>
        <div>Estado: <span class="estado-etiqueta"><?= e($i['estado']) ?></span></div>
        <form method="post" action="admin/lista-espera.php" class="acoes">
            <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
            <button class="botao botao-secundario" name="acao" value="notificada" type="submit">Notificada</button>
            <button class="botao botao-secundario" name="acao" value="concluida" type="submit">Concluída</button>
            <button class="botao botao-secundario" name="acao" value="cancelada" type="submit">Cancelar</button>
        </form>
    </article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
