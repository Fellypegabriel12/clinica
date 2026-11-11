<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin', 'rececao']);
$pdo = db();

$estados = [
    'nova'        => 'Nova',
    'em_analise'  => 'Em análise',
    'entrevista'  => 'Entrevista',
    'rejeitada'   => 'Rejeitada',
    'contratada'  => 'Contratada',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);

    if ($acao === 'estado') {
        $novo = (string)($_POST['estado'] ?? '');
        if (isset($estados[$novo])) {
            $pdo->prepare('UPDATE candidaturas SET estado = ? WHERE id = ?')->execute([$novo, $id]);
            registar_admin('candidatura_estado', 'candidaturas', $id, $novo);
            flash_definir('sucesso', 'Estado atualizado.');
        }
    } elseif ($acao === 'apagar') {
        $st = $pdo->prepare('SELECT cv FROM candidaturas WHERE id = ?');
        $st->execute([$id]);
        $cv = $st->fetchColumn() ?: null;
        $pdo->prepare('DELETE FROM candidaturas WHERE id = ?')->execute([$id]);
        if ($cv && str_starts_with($cv, 'uploads/cv/')) {
            $f = __DIR__ . '/../' . $cv;
            if (is_file($f)) { @unlink($f); }
        }
        registar_admin('apagar_candidatura', 'candidaturas', $id);
        flash_definir('sucesso', 'Candidatura apagada.');
    }
    redirecionar('admin/candidaturas.php');
}

$filtro = (string)($_GET['estado'] ?? 'todas');
if (!isset($estados[$filtro]) && $filtro !== 'todas') { $filtro = 'todas'; }

$where  = '';
$params = [];
if ($filtro !== 'todas') {
    $where  = ' WHERE estado = ?';
    $params = [$filtro];
}

$st = $pdo->prepare('SELECT * FROM candidaturas' . $where . ' ORDER BY criado_em DESC LIMIT 300');
$st->execute($params);
$candidaturas = $st->fetchAll();

$tituloPagina = 'Candidaturas';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Candidaturas</h1>

<form method="get" action="admin/candidaturas.php" class="filtros-gestao">
    <div class="campo">
        <label for="estado">Filtrar por estado</label>
        <select id="estado" name="estado">
            <option value="todas" <?= $filtro === 'todas' ? 'selected' : '' ?>>Todas</option>
            <?php foreach ($estados as $v => $t): ?>
                <option value="<?= e($v) ?>" <?= $filtro === $v ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo"><button type="submit" class="botao">Filtrar</button></div>
</form>

<?php if (!$candidaturas): ?>
    <div class="alerta alerta-info">Não há candidaturas com este filtro.</div>
<?php else: ?>
    <div class="grelha grelha-larga">
        <?php foreach ($candidaturas as $c): ?>
            <article class="cartao linha-consulta">
                <strong><?= e($c['nome']) ?></strong>
                <span><?= e($c['email']) ?><?= $c['telemovel'] ? ' · ' . e($c['telemovel']) : '' ?></span>
                <span class="ajuda">Área: <?= e($c['area']) ?> · <?= e((new DateTime($c['criado_em']))->format('d/m/Y H:i')) ?></span>
                <?php if ($c['mensagem']): ?>
                    <p><?= nl2br(e($c['mensagem'])) ?></p>
                <?php endif; ?>
                <?php if ($c['cv']): ?>
                    <p><a class="botao botao-secundario" href="admin/download-cv.php?id=<?= (int)$c['id'] ?>" target="_blank" rel="noopener">Ver CV</a></p>
                <?php endif; ?>

                <form method="post" action="admin/candidaturas.php" class="acoes">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <select name="estado" aria-label="Estado">
                        <?php foreach ($estados as $v => $t): ?>
                            <option value="<?= e($v) ?>" <?= $c['estado'] === $v ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="acao" value="estado" class="botao botao-secundario">Atualizar</button>
                    <button type="submit" name="acao" value="apagar" class="botao botao-secundario"
                            onclick="return confirm('Apagar esta candidatura?');">Apagar</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>