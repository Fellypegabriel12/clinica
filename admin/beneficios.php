<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/marcacao.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin', 'rececao']);
$pdo = db();

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');
    $id   = (int)($_POST['beneficio'] ?? 0);
    $nota = mb_substr(trim((string)($_POST['notas'] ?? '')), 0, 255);

    try {
        if ($acao === 'validar') {
            $pdo->prepare(
                "UPDATE beneficios_paciente
                    SET estado_validacao = 'validado', validado_em = NOW(), validado_por = ?, notas = ?
                  WHERE id = ?"
            )->execute([$u['id'], $nota ?: null, $id]);
            registar_admin('validar_beneficio', 'beneficios_paciente', $id);
            flash_definir('sucesso', 'Benefício validado.');
        } elseif ($acao === 'rejeitar') {
            $pdo->prepare(
                "UPDATE beneficios_paciente
                    SET estado_validacao = 'rejeitado', validado_em = NOW(), validado_por = ?, notas = ?
                  WHERE id = ?"
            )->execute([$u['id'], $nota ?: null, $id]);
            registar_admin('rejeitar_beneficio', 'beneficios_paciente', $id);
            flash_definir('sucesso', 'Benefício rejeitado.');
        } elseif ($acao === 'em_validacao') {
            $pdo->prepare(
                "UPDATE beneficios_paciente SET estado_validacao = 'em_validacao', notas = ? WHERE id = ?"
            )->execute([$nota ?: null, $id]);
            registar_admin('em_validacao_beneficio', 'beneficios_paciente', $id);
            flash_definir('sucesso', 'Benefício marcado como "em validação".');
        }
    } catch (PDOException $e) {
        error_log('Erro benefícios: ' . $e->getMessage());
        flash_definir('erro', 'Ocorreu um erro. Tenta novamente.');
    }
    redirecionar('admin/beneficios.php');
}

$filtro = (string)($_GET['estado'] ?? 'pendentes');
$estadosValidos = ['pendentes', 'validado', 'rejeitado', 'em_validacao', 'todos'];
if (!in_array($filtro, $estadosValidos, true)) { $filtro = 'pendentes'; }

$where = '';
if ($filtro === 'pendentes') {
    $where = " WHERE bp.estado_validacao IN ('registado', 'em_validacao')";
} elseif (in_array($filtro, ['validado', 'rejeitado', 'em_validacao'], true)) {
    $where = ' WHERE bp.estado_validacao = ?';
}

$sql = "SELECT bp.id, bp.numero_apolice, bp.numero_beneficiario,
               bp.valido_de, bp.valido_ate, bp.estado_validacao, bp.validado_em, bp.notas, bp.criado_em,
               p.nome_completo AS paciente, p.email AS paciente_email,
               pl.nome AS plano, s.nome AS seguradora, s.tipo AS tipo_seguradora,
               u.nome_completo AS validador
          FROM beneficios_paciente bp
          JOIN pacientes p ON p.id = bp.paciente_id
          JOIN planos_beneficio pl ON pl.id = bp.plano_id
          JOIN seguradoras s ON s.id = pl.seguradora_id
          LEFT JOIN utilizadores u ON u.id = bp.validado_por"
     . $where
     . ' ORDER BY bp.criado_em DESC LIMIT 300';

$st = $pdo->prepare($sql);
$st->execute($where === ' WHERE bp.estado_validacao = ?' ? [$filtro] : []);
$beneficios = $st->fetchAll();

$tituloPagina = 'Benefícios e seguros';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Benefícios e seguros</h1>

<p class="ajuda">
    Valida os benefícios registados pelos pacientes. Sem validação, o desconto não é aplicado nas marcações.
    Este é o único sítio onde a clínica valida planos de saúde e seguros.
</p>

<form method="get" action="admin/beneficios.php" class="filtros-gestao">
    <div class="campo">
        <label for="estado">Mostrar</label>
        <select id="estado" name="estado">
            <option value="pendentes" <?= $filtro === 'pendentes' ? 'selected' : '' ?>>Por validar</option>
            <option value="em_validacao" <?= $filtro === 'em_validacao' ? 'selected' : '' ?>>Em validação</option>
            <option value="validado" <?= $filtro === 'validado' ? 'selected' : '' ?>>Validados</option>
            <option value="rejeitado" <?= $filtro === 'rejeitado' ? 'selected' : '' ?>>Rejeitados</option>
            <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
        </select>
    </div>
    <div class="campo"><button type="submit" class="botao">Filtrar</button></div>
</form>

<?php if (!$beneficios): ?>
    <div class="alerta alerta-info">Não há benefícios com este filtro.</div>
<?php else: ?>
    <div class="grelha grelha-larga">
        <?php foreach ($beneficios as $b): ?>
            <article class="cartao linha-consulta">
                <strong><?= e($b['paciente']) ?></strong>
                <span class="ajuda"><?= e($b['paciente_email'] ?? '') ?></span>
                <span><strong><?= e($b['seguradora']) ?></strong> — <?= e($b['plano']) ?>
                    <span class="ajuda">(<?= e($b['tipo_seguradora']) ?>)</span>
                </span>
                <span class="ajuda">
                    Apólice: <?= e($b['numero_apolice'] ?? '—') ?>
                    · Beneficiário: <?= e($b['numero_beneficiario'] ?? '—') ?>
                </span>
                <span class="ajuda">
                    Validade:
                    <?= $b['valido_de'] ? e((new DateTime($b['valido_de']))->format('d/m/Y')) : '—' ?>
                    a
                    <?= $b['valido_ate'] ? e((new DateTime($b['valido_ate']))->format('d/m/Y')) : '—' ?>
                </span>
                <span>
                    Estado:
                    <span class="estado-etiqueta estado-<?= e($b['estado_validacao']) ?>">
                        <?= e($b['estado_validacao']) ?>
                    </span>
                </span>
                <?php if ($b['validado_em']): ?>
                    <span class="ajuda">
                        <?= e($b['estado_validacao'] === 'validado' ? 'Validado' : 'Tratado') ?>
                        em <?= e((new DateTime($b['validado_em']))->format('d/m/Y H:i')) ?>
                        <?= $b['validador'] ? 'por ' . e($b['validador']) : '' ?>
                    </span>
                <?php endif; ?>
                <?php if ($b['notas']): ?>
                    <span class="ajuda">Notas: <?= e($b['notas']) ?></span>
                <?php endif; ?>

                <?php if (in_array($b['estado_validacao'], ['registado', 'em_validacao'], true)): ?>
                    <form method="post" action="admin/beneficios.php" class="acoes" style="display:grid;gap:.5rem;">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="beneficio" value="<?= (int)$b['id'] ?>">
                        <div class="campo" style="margin:0;">
                            <label for="notas-<?= (int)$b['id'] ?>" class="ajuda">Notas internas (opcional)</label>
                            <input type="text" id="notas-<?= (int)$b['id'] ?>" name="notas" maxlength="255">
                        </div>
                        <div>
                            <button type="submit" name="acao" value="validar" class="botao">Validar</button>
                            <button type="submit" name="acao" value="em_validacao" class="botao botao-secundario">Em validação</button>
                            <button type="submit" name="acao" value="rejeitar" class="botao botao-secundario">Rejeitar</button>
                        </div>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>