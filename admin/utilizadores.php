<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/marcacao.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin']);
$pdo = db();

const PAPEIS = ['paciente' => 'Paciente', 'rececao' => 'Receção', 'dentista' => 'Dentista', 'admin' => 'Administrador'];

$q      = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
$pagina = max(1, (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1));
$volta  = 'admin/utilizadores.php?' . http_build_query(['q' => $q, 'pagina' => $pagina]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $alvoId = (int)($_POST['utilizador'] ?? 0);
    $papel  = (string)($_POST['papel'] ?? '');
    $dentistaId = (int)($_POST['dentista'] ?? 0);

    try {
        if (!isset($_POST['ativo_enviado'])) {
            throw new RuntimeException('Formulário incompleto. Recarrega a página e tenta outra vez.');
        }
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT id, papel, ativo FROM utilizadores WHERE id = ? FOR UPDATE');
        $st->execute([$alvoId]);
        $alvo = $st->fetch();

        if (!$alvo) {
            throw new RuntimeException('Utilizador não encontrado.');
        }
        if (!isset(PAPEIS[$papel])) {
            throw new RuntimeException('Papel inválido.');
        }
        if ($alvoId === $u['id'] && ($papel !== $alvo['papel'] || !$ativo)) {
            throw new RuntimeException('Não podes mudar o teu próprio papel nem desativar a tua conta.');
        }
        if ($alvo['papel'] === 'admin' && (int)$alvo['ativo'] === 1 && ($papel !== 'admin' || !$ativo)) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE papel = 'admin' AND ativo = 1 AND id <> ?");
            $st->execute([$alvoId]);
            if ((int)$st->fetchColumn() === 0) {
                throw new RuntimeException('Tem de existir pelo menos um administrador ativo.');
            }
        }

        // Dentista: precisa de um perfil livre ou já associado a este utilizador.
        // BUGFIX (race condition): FOR UPDATE no dentistas também, para evitar que dois
        // admins (a editar users diferentes) escolham o mesmo dentista livre em simultâneo.
        if ($papel === 'dentista') {
            $st = $pdo->prepare('SELECT id FROM dentistas WHERE id = ? AND (utilizador_id IS NULL OR utilizador_id = ?) FOR UPDATE');
            $st->execute([$dentistaId, $alvoId]);
            if (!$st->fetchColumn()) {
                throw new RuntimeException('Escolhe o perfil de dentista a associar a esta conta.');
            }
        }

        $pdo->prepare('UPDATE utilizadores SET papel = ?, ativo = ? WHERE id = ?')->execute([$papel, $ativo, $alvoId]);
        $pdo->prepare('UPDATE dentistas SET utilizador_id = NULL WHERE utilizador_id = ?')->execute([$alvoId]);
        if ($papel === 'dentista') {
            $pdo->prepare('UPDATE dentistas SET utilizador_id = ? WHERE id = ?')->execute([$alvoId, $dentistaId]);
        }

        registar_admin('editar_utilizador', 'utilizadores', $alvoId, 'papel=' . $papel . ' ativo=' . $ativo);
        $pdo->commit();
        flash_definir('sucesso', 'Utilizador atualizado.');
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        flash_definir('erro', $e->getMessage());
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Erro ao editar utilizador: ' . $e->getMessage());
        flash_definir('erro', 'Ocorreu um erro. Tenta novamente.');
    }
    redirecionar($volta);
}

// ---------- Lista ----------
$porPagina = 25;
$where  = '';
$params = [];
if ($q !== '') {
    $where  = ' WHERE (u.nome_completo LIKE ? OR u.email LIKE ?)';
    $like   = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $params = [$like, $like];
}
$st = $pdo->prepare('SELECT COUNT(*) FROM utilizadores u' . $where);
$st->execute($params);
$total = (int)$st->fetchColumn();
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina  = min($pagina, $paginas);

$st = $pdo->prepare(
    'SELECT u.id, u.nome_completo, u.email, u.papel, u.ativo, u.criado_em, d.id AS dentista_id
       FROM utilizadores u LEFT JOIN dentistas d ON d.utilizador_id = u.id' . $where .
    ' ORDER BY u.nome_completo LIMIT ' . $porPagina . ' OFFSET ' . (($pagina - 1) * $porPagina)
);
$st->execute($params);
$utilizadores = $st->fetchAll();

$dentistas = $pdo->query('SELECT id, nome_completo, utilizador_id FROM dentistas ORDER BY nome_completo')->fetchAll();

$tituloPagina = 'Utilizadores e permissões';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Utilizadores e permissões</h1>

<p class="ajuda">
    <strong>Paciente:</strong> usa só a área pessoal. <strong>Receção:</strong> gere marcações, pedidos e pagamentos.
    <strong>Dentista:</strong> vê apenas a sua agenda. <strong>Administrador:</strong> acesso total.
</p>

<form method="get" action="admin/utilizadores.php" class="filtros-gestao">
    <div class="campo">
        <label for="q">Procurar por nome ou email</label>
        <input type="text" id="q" name="q" maxlength="100" value="<?= e($q) ?>">
    </div>
    <div class="campo"><button type="submit" class="botao">Procurar</button></div>
</form>

<?php if (!$utilizadores): ?>
    <div class="alerta alerta-info">Nenhum utilizador encontrado.</div>
<?php else: ?>
<div class="tabela-scroll">
<table class="tabela">
    <thead><tr><th>Utilizador</th><th>Papel</th><th>Dentista associado</th><th>Ativo</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($utilizadores as $x): $fid = 'u' . (int)$x['id']; ?>
        <tr>
            <td>
                <strong><?= e($x['nome_completo']) ?></strong><br>
                <span class="ajuda"><?= e($x['email']) ?></span>
            </td>
            <td>
                <select form="<?= $fid ?>" name="papel" aria-label="Papel de <?= e($x['nome_completo']) ?>">
                    <?php foreach (PAPEIS as $v => $t): ?>
                        <option value="<?= e($v) ?>" <?= $x['papel'] === $v ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select form="<?= $fid ?>" name="dentista" aria-label="Perfil de dentista de <?= e($x['nome_completo']) ?>">
                    <option value="0">— nenhum —</option>
                    <?php foreach ($dentistas as $d):
                        if ($d['utilizador_id'] !== null && (int)$d['utilizador_id'] !== (int)$x['id']) { continue; } ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)$x['dentista_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="checkbox" form="<?= $fid ?>" name="ativo" value="1" <?= (int)$x['ativo'] ? 'checked' : '' ?>
                       aria-label="Conta ativa de <?= e($x['nome_completo']) ?>">
            </td>
            <td>
                <form id="<?= $fid ?>" method="post" action="admin/utilizadores.php">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="utilizador" value="<?= (int)$x['id'] ?>">
                    <input type="hidden" name="ativo_enviado" value="1">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <input type="hidden" name="pagina" value="<?= $pagina ?>">
                    <button type="submit" class="botao botao-secundario">Guardar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($paginas > 1): ?>
    <p class="separadores">
        <?php for ($i = 1; $i <= $paginas; $i++): ?>
            <a class="botao <?= $i === $pagina ? '' : 'botao-secundario' ?>"
               href="admin/utilizadores.php?<?= e(http_build_query(['q' => $q, 'pagina' => $i])) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </p>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>