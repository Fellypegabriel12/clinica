<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';
require __DIR__ . '/../includes/pagamentos.php';

$u = exigir_login(['admin']);
$pdo = db();

$erros = [];
$aba = (string)($_GET['aba'] ?? 'seguradoras');
if (!in_array($aba, ['seguradoras','planos','regras'], true)) { $aba = 'seguradoras'; }
$planoId = (int)($_GET['plano'] ?? $_POST['plano_id'] ?? 0);

$tiposSeg = ['seguro_saude'=>'Seguro de saúde','seguro_dentario'=>'Seguro dentário','plano_saude'=>'Plano de saúde','apoio_publico'=>'Apoio público','desconto_comercial'=>'Desconto comercial / plano dentário'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = (string)($_POST['acao'] ?? '');
    try {
        if ($acao === 'guardar_seguradora') {
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim((string)($_POST['nome'] ?? ''));
            $tipo = (string)($_POST['tipo'] ?? 'seguro_saude');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            if ($nome === '' || mb_strlen($nome) > 120) { throw new RuntimeException('Nome da seguradora/plano inválido.'); }
            if (!isset($tiposSeg[$tipo])) { throw new RuntimeException('Tipo inválido.'); }
            if ($id > 0) {
                $pdo->prepare('UPDATE seguradoras SET nome=?, tipo=?, ativo=? WHERE id=?')->execute([$nome,$tipo,$ativo,$id]);
                registar_admin('editar_seguradora', 'seguradoras', $id, $nome);
                flash_definir('sucesso', 'Entidade atualizada.');
            } else {
                $pdo->prepare('INSERT INTO seguradoras (nome,tipo,ativo) VALUES (?,?,?)')->execute([$nome,$tipo,$ativo]);
                registar_admin('criar_seguradora', 'seguradoras', (int)$pdo->lastInsertId(), $nome);
                flash_definir('sucesso', 'Entidade criada.');
            }
            redirecionar('admin/seguradoras-planos.php?aba=seguradoras');
        }
        if ($acao === 'guardar_plano') {
            $id = (int)($_POST['id'] ?? 0);
            $seguradoraId = (int)($_POST['seguradora_id'] ?? 0);
            $nome = trim((string)($_POST['nome'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $condicoes = trim((string)($_POST['condicoes'] ?? ''));
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            if (!$seguradoraId || $nome === '' || mb_strlen($nome) > 120) { throw new RuntimeException('Plano inválido (seguradora e nome obrigatórios).'); }
            $st = $pdo->prepare('SELECT id FROM seguradoras WHERE id=?'); $st->execute([$seguradoraId]); if (!$st->fetchColumn()) throw new RuntimeException('Seguradora inválida.');
            if ($id > 0) {
                $pdo->prepare('UPDATE planos_beneficio SET seguradora_id=?, nome=?, descricao=?, condicoes=?, ativo=? WHERE id=?')->execute([$seguradoraId,$nome,$descricao?:null,$condicoes?:null,$ativo,$id]);
                registar_admin('editar_plano_beneficio', 'planos_beneficio', $id, $nome);
            } else {
                $pdo->prepare('INSERT INTO planos_beneficio (seguradora_id,nome,descricao,condicoes,ativo) VALUES (?,?,?,?,?)')->execute([$seguradoraId,$nome,$descricao?:null,$condicoes?:null,$ativo]);
                $id = (int)$pdo->lastInsertId();
                registar_admin('criar_plano_beneficio', 'planos_beneficio', $id, $nome);
            }
            flash_definir('sucesso', 'Plano guardado. Configure os tratamentos/coberturas na aba Regras.');
            redirecionar('admin/seguradoras-planos.php?aba=regras&plano='.$id);
        }
        if ($acao === 'guardar_regra') {
            $planoId = (int)($_POST['plano_id'] ?? 0);
            $tratamentoId = (int)($_POST['tratamento_id'] ?? 0);
            if ($planoId<=0 || $tratamentoId<=0) throw new RuntimeException('Plano/tratamento inválido.');
            $tipoDesconto = (string)($_POST['tipo_desconto'] ?? 'percentagem');
            if (!in_array($tipoDesconto, ['percentagem','valor_fixo'], true)) $tipoDesconto = 'percentagem';
            $valor = $_POST['valor'] !== '' ? round((float)str_replace(',', '.', (string)$_POST['valor']), 2) : 0.0;
            $precoConv = $_POST['preco_convencionado'] !== '' ? round((float)str_replace(',', '.', (string)$_POST['preco_convencionado']), 2) : null;
            $maxComp = $_POST['valor_max_comparticipado'] !== '' ? round((float)str_replace(',', '.', (string)$_POST['valor_max_comparticipado']), 2) : null;
            $pctComp = $_POST['percentagem_comparticipacao'] !== '' ? round((float)str_replace(',', '.', (string)$_POST['percentagem_comparticipacao']), 2) : null;
            $cond = mb_substr(trim((string)($_POST['condicoes_especificas'] ?? '')), 0, 255);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $pdo->prepare('INSERT INTO planos_tratamentos (plano_id,tratamento_id,tipo_desconto,valor,preco_convencionado,valor_max_comparticipado,percentagem_comparticipacao,condicoes_especificas,ativo)
                VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tipo_desconto=VALUES(tipo_desconto), valor=VALUES(valor), preco_convencionado=VALUES(preco_convencionado), valor_max_comparticipado=VALUES(valor_max_comparticipado), percentagem_comparticipacao=VALUES(percentagem_comparticipacao), condicoes_especificas=VALUES(condicoes_especificas), ativo=VALUES(ativo)')
                ->execute([$planoId,$tratamentoId,$tipoDesconto,$valor,$precoConv,$maxComp,$pctComp,$cond?:null,$ativo]);
            registar_admin('guardar_regra_plano', 'planos_tratamentos', $planoId, 'tratamento '.$tratamentoId);
            flash_definir('sucesso', 'Regra do tratamento guardada.');
            redirecionar('admin/seguradoras-planos.php?aba=regras&plano='.$planoId);
        }
        if ($acao === 'remover_regra') {
            $planoId = (int)($_POST['plano_id'] ?? 0);
            $tratamentoId = (int)($_POST['tratamento_id'] ?? 0);
            $pdo->prepare('DELETE FROM planos_tratamentos WHERE plano_id=? AND tratamento_id=?')->execute([$planoId,$tratamentoId]);
            registar_admin('remover_regra_plano', 'planos_tratamentos', $planoId, 'tratamento '.$tratamentoId);
            flash_definir('sucesso', 'Regra removida.');
            redirecionar('admin/seguradoras-planos.php?aba=regras&plano='.$planoId);
        }
    } catch (Throwable $e) {
        if ($e instanceof PDOException && (string)$e->getCode() === '23000') { $erros[] = 'Registo duplicado ou referência inválida.'; }
        else { $erros[] = $e instanceof RuntimeException ? $e->getMessage() : 'Ocorreu um erro. Tenta novamente.'; error_log('seguradoras-planos: '.$e->getMessage()); }
    }
}

$seguradoras = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM planos_beneficio p WHERE p.seguradora_id=s.id) n_planos FROM seguradoras s ORDER BY s.ativo DESC, s.nome')->fetchAll();
$planos = $pdo->query('SELECT p.*, s.nome AS seguradora, s.tipo AS seguradora_tipo FROM planos_beneficio p JOIN seguradoras s ON s.id=p.seguradora_id ORDER BY p.ativo DESC, s.nome, p.nome')->fetchAll();
$tratamentos = $pdo->query('SELECT id, nome FROM tratamentos WHERE ativo=1 ORDER BY nome')->fetchAll();
$planoSel = null; $regras = [];
if ($planoId > 0) {
    $st = $pdo->prepare('SELECT p.*, s.nome AS seguradora, s.tipo AS seguradora_tipo FROM planos_beneficio p JOIN seguradoras s ON s.id=p.seguradora_id WHERE p.id=?');
    $st->execute([$planoId]); $planoSel = $st->fetch();
    if ($planoSel) {
        $st = $pdo->prepare('SELECT pt.*, t.nome AS tratamento FROM planos_tratamentos pt JOIN tratamentos t ON t.id=pt.tratamento_id WHERE pt.plano_id=? ORDER BY t.nome');
        $st->execute([$planoId]); $regras = $st->fetchAll();
    }
}

$tituloPagina = 'Seguradoras e planos';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>
<p><a href="admin/index.php">← Painel</a></p>
<h1>Seguradoras e planos dentários/saúde</h1>
<div class="alerta alerta-info" role="note"><strong>Distinção:</strong> seguros de saúde podem comparticipar tratamentos conforme contrato (cobertura, limites, condições). Planos dentários normalmente aplicam preços convencionados ou descontos. Configure apenas valores/regras reais do contrato; o sistema não inventa percentagens.</div>
<?php foreach ($erros as $e): ?><div class="alerta alerta-erro"><?= e($e) ?></div><?php endforeach; ?>

<nav class="separadores" aria-label="Secções">
    <a class="botao<?= $aba==='seguradoras'?'':' botao-secundario' ?>" href="admin/seguradoras-planos.php?aba=seguradoras">Seguradoras/entidades</a>
    <a class="botao<?= $aba==='planos'?'':' botao-secundario' ?>" href="admin/seguradoras-planos.php?aba=planos">Planos</a>
    <a class="botao<?= $aba==='regras'?'':' botao-secundario' ?>" href="admin/seguradoras-planos.php?aba=regras<?= $planoId?'&plano='.$planoId:'' ?>">Regras por tratamento</a>
</nav>

<?php if ($aba === 'seguradoras'): ?>
<section class="cartao seccao"><h2>Nova seguradora/entidade</h2>
<form method="post" action="admin/seguradoras-planos.php?aba=seguradoras" class="grelha"><?= csrf_campo() ?><input type="hidden" name="acao" value="guardar_seguradora"><input type="hidden" name="id" value="0">
    <div class="campo"><label>Nome</label><input type="text" name="nome" required maxlength="120"></div>
    <div class="campo"><label>Tipo</label><select name="tipo"><?php foreach ($tiposSeg as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="campo campo-checkbox"><label><input type="checkbox" name="ativo" value="1" checked> Ativa</label></div>
    <div class="campo"><button class="botao" type="submit">Criar</button></div>
</form></section>
<section class="seccao"><h2>Entidades registadas</h2>
<div class="tabela-scroll"><table class="tabela"><thead><tr><th>Nome</th><th>Tipo</th><th>Planos</th><th>Estado</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($seguradoras as $s): ?><tr><td><?= e($s['nome']) ?></td><td><?= e($tiposSeg[$s['tipo']] ?? $s['tipo']) ?></td><td><?= (int)$s['n_planos'] ?></td><td><?= $s['ativo']?'Ativa':'Inativa' ?></td>
<td><details><summary>Editar</summary><form method="post" action="admin/seguradoras-planos.php?aba=seguradoras" style="display:grid;gap:.3rem;margin-top:.3rem"><?= csrf_campo() ?><input type="hidden" name="acao" value="guardar_seguradora"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="text" name="nome" value="<?= e($s['nome']) ?>" required><select name="tipo"><?php foreach ($tiposSeg as $k=>$v): ?><option value="<?= e($k) ?>" <?= $s['tipo']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select><label><input type="checkbox" name="ativo" value="1" <?= $s['ativo']?'checked':'' ?>> Ativa</label><button class="botao botao-secundario" type="submit">Guardar</button></form></details></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>

<?php if ($aba === 'planos'): ?>
<section class="cartao seccao"><h2>Novo plano</h2>
<form method="post" action="admin/seguradoras-planos.php?aba=planos" class="grelha"><?= csrf_campo() ?><input type="hidden" name="acao" value="guardar_plano"><input type="hidden" name="id" value="0">
    <div class="campo"><label>Seguradora/entidade</label><select name="seguradora_id" required><option value="">—</option><?php foreach ($seguradoras as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="campo"><label>Nome do plano</label><input type="text" name="nome" required maxlength="120"></div>
    <div class="campo" style="grid-column:1/-1"><label>Descrição</label><input type="text" name="descricao" maxlength="255"></div>
    <div class="campo" style="grid-column:1/-1"><label>Condições gerais</label><textarea name="condicoes" rows="3" maxlength="5000"></textarea></div>
    <div class="campo campo-checkbox"><label><input type="checkbox" name="ativo" value="1" checked> Ativo</label></div>
    <div class="campo"><button class="botao" type="submit">Criar</button></div>
</form></section>
<section class="seccao"><h2>Planos</h2>
<div class="tabela-scroll"><table class="tabela"><thead><tr><th>Plano</th><th>Seguradora</th><th>Tipo</th><th>Estado</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($planos as $p): ?><tr><td><?= e($p['nome']) ?></td><td><?= e($p['seguradora']) ?></td><td><?= e($tiposSeg[$p['seguradora_tipo']] ?? $p['seguradora_tipo']) ?></td><td><?= $p['ativo']?'Ativo':'Inativo' ?></td>
<td><a class="botao botao-secundario" href="admin/seguradoras-planos.php?aba=regras&plano=<?= (int)$p['id'] ?>">Regras/tratamentos</a>
<details><summary>Editar</summary><form method="post" action="admin/seguradoras-planos.php?aba=planos" style="display:grid;gap:.3rem;margin-top:.3rem"><?= csrf_campo() ?><input type="hidden" name="acao" value="guardar_plano"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><select name="seguradora_id"><?php foreach ($seguradoras as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $p['seguradora_id']==$s['id']?'selected':'' ?>><?= e($s['nome']) ?></option><?php endforeach; ?></select><input type="text" name="nome" value="<?= e($p['nome']) ?>" required><input type="text" name="descricao" value="<?= e($p['descricao'] ?? '') ?>"><textarea name="condicoes" rows="2"><?= e($p['condicoes'] ?? '') ?></textarea><label><input type="checkbox" name="ativo" value="1" <?= $p['ativo']?'checked':'' ?>> Ativo</label><button class="botao botao-secundario" type="submit">Guardar</button></form></details></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>

<?php if ($aba === 'regras'): ?>
<section class="seccao">
<?php if (!$planoSel): ?>
    <div class="alerta alerta-info">Selecione um plano para configurar tratamentos abrangidos, comparticipações, preços convencionados e descontos.</div>
    <div class="campo"><label>Plano</label><select onchange="if(this.value)location='admin/seguradoras-planos.php?aba=regras&plano='+this.value"><option value="">— escolher —</option><?php foreach ($planos as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['seguradora'].' — '.$p['nome']) ?></option><?php endforeach; ?></select></div>
<?php else: ?>
    <h2>Regras: <?= e($planoSel['seguradora'].' — '.$planoSel['nome']) ?></h2>
    <p class="ajuda">Tipo: <?= e($tiposSeg[$planoSel['seguradora_tipo']] ?? $planoSel['seguradora_tipo']) ?>. Configure apenas o que existe no contrato. <strong>Preço convencionado</strong> define o valor a pagar pelo paciente (desconto = preço original - convencionado). <strong>Comparticipação %</strong> com <strong>valor máximo</strong> reduz o valor a pagar (ex.: seguro comparticipa até X€). Campos de desconto legado mantêm compatibilidade.</p>
    <div class="cartao seccao"><h3>Adicionar/atualizar tratamento</h3>
    <form method="post" action="admin/seguradoras-planos.php?aba=regras&plano=<?= (int)$planoId ?>" class="grelha"><?= csrf_campo() ?><input type="hidden" name="acao" value="guardar_regra"><input type="hidden" name="plano_id" value="<?= (int)$planoId ?>">
        <div class="campo"><label>Tratamento</label><select name="tratamento_id" required><option value="">—</option><?php foreach ($tratamentos as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="campo"><label>Preço convencionado (€)</label><input type="number" name="preco_convencionado" step="0.01" min="0" placeholder="Opcional"></div>
        <div class="campo"><label>Comparticipação (%)</label><input type="number" name="percentagem_comparticipacao" step="0.01" min="0" max="100" placeholder="Opcional"></div>
        <div class="campo"><label>Valor máx. comparticipado (€)</label><input type="number" name="valor_max_comparticipado" step="0.01" min="0" placeholder="Opcional"></div>
        <div class="campo"><label>Desconto legado: tipo</label><select name="tipo_desconto"><option value="percentagem">Percentagem</option><option value="valor_fixo">Valor fixo</option></select></div>
        <div class="campo"><label>Desconto legado: valor</label><input type="number" name="valor" step="0.01" min="0" value="0"></div>
        <div class="campo" style="grid-column:1/-1"><label>Condições específicas</label><input type="text" name="condicoes_especificas" maxlength="255"></div>
        <div class="campo campo-checkbox"><label><input type="checkbox" name="ativo" value="1" checked> Regra ativa</label></div>
        <div class="campo"><button class="botao" type="submit">Guardar regra</button></div>
    </form></div>

    <h3>Tratamentos configurados</h3>
    <?php if (!$regras): ?><p class="ajuda">Nenhum tratamento configurado neste plano.</p><?php else: ?>
    <div class="tabela-scroll"><table class="tabela"><thead><tr><th>Tratamento</th><th>Convencionado</th><th>Compart. %</th><th>Máx.</th><th>Legado</th><th>Condições</th><th></th></tr></thead><tbody>
    <?php foreach ($regras as $r): ?><tr>
        <td><?= e($r['tratamento']) ?><?= !($r['ativo']??1)?' <span class="ajuda">(inativa)</span>':'' ?></td>
        <td><?= $r['preco_convencionado']!==null?e(formatar_preco($r['preco_convencionado'])):'—' ?></td>
        <td><?= $r['percentagem_comparticipacao']!==null?e($r['percentagem_comparticipacao']).'%':'—' ?></td>
        <td><?= $r['valor_max_comparticipado']!==null?e(formatar_preco($r['valor_max_comparticipado'])):'—' ?></td>
        <td class="ajuda"><?= e($r['tipo_desconto']) ?> <?= e($r['valor']) ?></td>
        <td class="ajuda"><?= e($r['condicoes_especificas'] ?? '—') ?></td>
        <td><form method="post" action="admin/seguradoras-planos.php?aba=regras&plano=<?= (int)$planoId ?>" class="form-inline" onsubmit="return confirm('Remover esta regra?');"><?= csrf_campo() ?><input type="hidden" name="acao" value="remover_regra"><input type="hidden" name="plano_id" value="<?= (int)$planoId ?>"><input type="hidden" name="tratamento_id" value="<?= (int)$r['tratamento_id'] ?>"><button class="botao botao-secundario" type="submit">Remover</button></form></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
<?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
