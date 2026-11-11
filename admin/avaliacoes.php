<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';

$u   = exigir_login(['admin', 'rececao']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $id  = (int)($_POST['id'] ?? 0);
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'publicar') {
            // Aviso: o comentário é publicado de forma anónima. Nunca mostrar
            // dados pessoais ou de saúde identificáveis.
            $pdo->prepare("UPDATE avaliacoes SET estado = 'publicada', moderado_por = ? WHERE id = ?")
                ->execute([$u['id'], $id]);
            registar_admin('publicar_avaliacao', 'avaliacoes', $id);
            flash_definir('sucesso', 'Avaliação publicada (de forma anónima).');
        } elseif ($acao === 'rejeitar') {
            $pdo->prepare("UPDATE avaliacoes SET estado = 'rejeitada', moderado_por = ? WHERE id = ?")
                ->execute([$u['id'], $id]);
            registar_admin('rejeitar_avaliacao', 'avaliacoes', $id);
            flash_definir('sucesso', 'Avaliação rejeitada.');
        } elseif ($acao === 'rejeitar_dados_sensiveis') {
            $pdo->prepare("UPDATE avaliacoes SET estado = 'rejeitada', moderado_por = ? WHERE id = ?")
                ->execute([$u['id'], $id]);
            registar_admin('rejeitar_avaliacao_dados_sensiveis', 'avaliacoes', $id);
            flash_definir('sucesso', 'Avaliação rejeitada por conter dados pessoais ou de saúde identificáveis.');
        } elseif ($acao === 'apagar') {
            $pdo->prepare("DELETE FROM avaliacoes WHERE id = ?")->execute([$id]);
            registar_admin('apagar_avaliacao', 'avaliacoes', $id);
            flash_definir('sucesso', 'Avaliação apagada.');
        }
    } catch (PDOException $e) {
        error_log('Erro avaliacoes: ' . $e->getMessage());
        flash_definir('erro', 'Ocorreu um erro. Tenta novamente.');
    }
    redirecionar('admin/avaliacoes.php');
}

$filtro = (string)($_GET['estado'] ?? 'pendente');
if (!in_array($filtro, ['pendente', 'publicada', 'rejeitada', 'todas'], true)) {
    $filtro = 'pendente';
}

$where = $filtro === 'todas' ? '' : ' WHERE a.estado = ?';
$params = $filtro === 'todas' ? [] : [$filtro];

$sql = "SELECT a.id, a.classificacao, a.comentario, a.estado, a.criado_em,
               p.nome_completo AS paciente, d.nome_completo AS dentista,
               c.inicio AS consulta_inicio,
               m.referencia,
               u.nome_completo AS moderador
          FROM avaliacoes a
          JOIN pacientes p ON p.id = a.paciente_id
          JOIN dentistas d ON d.id = a.dentista_id
          JOIN consultas c ON c.id = a.consulta_id
          JOIN marcacoes m ON m.id = c.marcacao_id
          LEFT JOIN utilizadores u ON u.id = a.moderado_por"
     . $where
     . ' ORDER BY a.criado_em DESC LIMIT 300';

$st = $pdo->prepare($sql);
$st->execute($params);
$avaliacoes = $st->fetchAll();

$st = $pdo->query(
    "SELECT d.nome_completo, COUNT(a.id) AS total, ROUND(AVG(a.classificacao), 1) AS media
       FROM avaliacoes a JOIN dentistas d ON d.id = a.dentista_id
      WHERE a.estado = 'publicada'
      GROUP BY d.id, d.nome_completo
      ORDER BY media DESC"
);
$porDentista = $st->fetchAll();

$rotulosEstado = [
    'pendente'  => 'Pendente',
    'publicada' => 'Publicada',
    'rejeitada' => 'Rejeitada',
];

$tituloPagina = 'Avaliações';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Avaliações</h1>

<div class="alerta alerta-info" role="note">
    <strong>Moderação obrigatória:</strong> as avaliações são publicadas de forma
    <strong>anónima</strong>. Rejeita sempre comentários que incluam nome de
    pacientes, contactos, número de utente, detalhes clínicos identificáveis ou
    qualquer informação que permita identificar o autor. Rejeita também imagens
    ou referências a resultados clínicos (antes/depois), em cumprimento das
    normas da OMD.
</div>

<section class="seccao">
    <h2>Média por médico dentista (publicadas)</h2>
    <?php if (!$porDentista): ?>
        <div class="alerta alerta-info">Ainda não há avaliações publicadas.</div>
    <?php else: ?>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Médico dentista</th><th>Nº de avaliações</th><th>Média (1-5)</th></tr></thead>
                <tbody>
                <?php foreach ($porDentista as $d): ?>
                    <tr>
                        <td><?= e($d['nome_completo']) ?></td>
                        <td><?= (int)$d['total'] ?></td>
                        <td><?= e((string)$d['media']) ?> ★</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<form method="get" action="admin/avaliacoes.php" class="filtros-gestao">
    <div class="campo">
        <label for="estado">Filtro</label>
        <select id="estado" name="estado">
            <?php foreach ($rotulosEstado as $v => $t): ?>
                <option value="<?= e($v) ?>" <?= $filtro === $v ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
            <option value="todas" <?= $filtro === 'todas' ? 'selected' : '' ?>>Todas</option>
        </select>
    </div>
    <div class="campo"><button type="submit" class="botao">Filtrar</button></div>
</form>

<?php if (!$avaliacoes): ?>
    <div class="alerta alerta-info">Não há avaliações com este filtro.</div>
<?php else: ?>
    <div class="grelha grelha-larga">
    <?php foreach ($avaliacoes as $a): ?>
        <article class="cartao linha-consulta">
            <strong><?= str_repeat('★', (int)$a['classificacao']) ?></strong>
            <span>Paciente anónimo sobre <strong><?= e($a['dentista']) ?></strong></span>
            <span class="ajuda">
                Consulta de <?= e((new DateTime($a['consulta_inicio']))->format('d/m/Y')) ?>
                · <?= e($a['referencia']) ?>
            </span>
            <?php if ($a['comentario']): ?>
                <p><?= nl2br(e($a['comentario'])) ?></p>
            <?php else: ?>
                <p class="ajuda">Sem comentário.</p>
            <?php endif; ?>
            <span>
                Estado:
                <span class="estado-etiqueta estado-<?= e($a['estado']) ?>">
                    <?= e($rotulosEstado[$a['estado']] ?? $a['estado']) ?>
                </span>
                <?php if ($a['moderador']): ?>
                    · por <?= e($a['moderador']) ?>
                <?php endif; ?>
            </span>

            <form method="post" action="admin/avaliacoes.php" class="acoes">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <?php if ($a['estado'] !== 'publicada'): ?>
                    <button type="submit" name="acao" value="publicar" class="botao">Publicar (anónima)</button>
                <?php endif; ?>
                <?php if ($a['estado'] !== 'rejeitada'): ?>
                    <button type="submit" name="acao" value="rejeitar" class="botao botao-secundario">Rejeitar</button>
                    <button type="submit" name="acao" value="rejeitar_dados_sensiveis" class="botao botao-secundario"
                            onclick="return confirm('Rejeitar por conter dados pessoais ou de saúde?');">
                        Rejeitar (dados sensíveis)
                    </button>
                <?php endif; ?>
                <button type="submit" name="acao" value="apagar" class="botao botao-secundario"
                        onclick="return confirm('Apagar esta avaliação?');">Apagar</button>
            </form>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>