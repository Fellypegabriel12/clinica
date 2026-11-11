<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';
require __DIR__ . '/../includes/stock.php';

$u   = exigir_login(['admin', 'rececao']);
$pdo = db();

$hoje = (new DateTimeImmutable('now'))->format('Y-m-d');
$total = [
    'dentistas'     => (int)$pdo->query('SELECT COUNT(*) FROM dentistas WHERE ativo = 1')->fetchColumn(),
    'tratamentos'   => (int)$pdo->query('SELECT COUNT(*) FROM tratamentos WHERE ativo = 1')->fetchColumn(),
    'pacientes'     => (int)$pdo->query('SELECT COUNT(*) FROM pacientes WHERE ativo = 1')->fetchColumn(),
    'mensagens'     => (int)$pdo->query('SELECT COUNT(*) FROM mensagens_contacto WHERE lida = 0')->fetchColumn(),
    'pedidos'       => (int)$pdo->query("SELECT COUNT(*) FROM pedidos_alteracao WHERE estado = 'pendente'")->fetchColumn(),
    'avaliacoes'    => (int)$pdo->query("SELECT COUNT(*) FROM avaliacoes WHERE estado = 'pendente'")->fetchColumn(),
    'stock_alertas' => stock_alertas($pdo),
    'candidaturas'  => (int)$pdo->query("SELECT COUNT(*) FROM candidaturas WHERE estado = 'nova'")->fetchColumn(),
];
$indicadores = [];
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE DATE(inicio)=? AND estado NOT IN ('cancelada')");
    $st->execute([$hoje]); $indicadores['consultas_hoje'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE DATE(inicio)=? AND estado='cancelada'");
    $st->execute([$hoje]); $indicadores['canceladas_hoje'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM lista_espera WHERE estado='ativa'"); $indicadores['lista_espera'] = (int)$st->fetchColumn();
} catch (Throwable $e) { $indicadores = []; }

$tituloPagina = 'Administração';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<style>
/* Botões de atalho do painel — grelha flexível, sem sobreposição */
.acoes-admin {
    display: flex;
    flex-wrap: wrap;
    gap: var(--esp-2);
    margin: 0 0 var(--esp-4);
}
.acoes-admin .botao {
    margin: 0;
    white-space: nowrap;
}
</style>

<h1>Painel de administração</h1>
<p>Olá, <?= e($u['nome']) ?> (<?= e($u['papel']) ?>).</p>

<div class="grelha seccao">
    <div class="cartao texto-centro"><p class="numero"><?= $total['dentistas'] ?></p><p class="ajuda">Dentistas ativos</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= $total['tratamentos'] ?></p><p class="ajuda">Tratamentos ativos</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= $total['pacientes'] ?></p><p class="ajuda">Pacientes</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= $total['mensagens'] ?></p><p class="ajuda">Mensagens por ler</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= $total['pedidos'] ?></p><p class="ajuda">Pedidos de alteração</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= $total['avaliacoes'] ?></p><p class="ajuda">Avaliações por moderar</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= $total['stock_alertas'] ?></p><p class="ajuda">Alertas de stock</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= $total['candidaturas'] ?></p><p class="ajuda">Candidaturas novas</p></div>
</div>
<?php if ($indicadores): ?>
<div class="grelha seccao">
    <div class="cartao texto-centro"><p class="numero"><?= (int)($indicadores['consultas_hoje'] ?? 0) ?></p><p class="ajuda">Consultas hoje (não canceladas)</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= (int)($indicadores['canceladas_hoje'] ?? 0) ?></p><p class="ajuda">Canceladas hoje</p></div>
    <div class="cartao texto-centro"><p class="numero"><?= (int)($indicadores['lista_espera'] ?? 0) ?></p><p class="ajuda">Lista de espera ativa</p></div>
</div>
<?php endif; ?>

<section class="seccao">
    <h2>Gestão clínica</h2>
    <?php if ($u['papel'] === 'admin'): ?>
        <div class="acoes-admin">
            <a class="botao" href="admin/dentistas.php">Gerir dentistas</a>
            <a class="botao" href="admin/horarios.php">Horários</a>
            <a class="botao" href="admin/bloqueios.php">Bloqueios (férias, folgas)</a>
            <a class="botao" href="admin/especialidades.php">Especialidades</a>
            <a class="botao" href="admin/tratamentos.php">Tratamentos</a>
        </div>
    <?php endif; ?>
</section>

<section class="seccao">
    <h2>Comunicação</h2>
    <div class="acoes-admin">
        <a class="botao" href="admin/contactos.php">Mensagens de contacto</a>
        <a class="botao" href="admin/artigos.php">Artigos (informação ao paciente)</a>
        <a class="botao" href="admin/perguntas-frequentes.php">Perguntas frequentes</a>
        <a class="botao" href="admin/galeria.php">Galeria</a>
        <a class="botao" href="admin/hero.php">Imagens do hero (início)</a>
        <a class="botao" href="admin/testemunhos.php">Testemunhos</a>
        <a class="botao" href="admin/parceiros.php">Marcas parceiras</a>
        <a class="botao" href="admin/avaliacoes.php">Avaliações</a>
        <a class="botao" href="admin/candidaturas.php">Candidaturas</a>
    </div>
</section>

<section class="seccao">
    <h2>Marcações e pagamentos</h2>
    <div class="acoes-admin">
        <a class="botao" href="admin/marcacoes.php">Agenda e marcações</a>
        <a class="botao" href="admin/lista-espera.php">Lista de espera</a>
        <a class="botao" href="admin/pedidos.php">Pedidos de alteração</a>
        <a class="botao" href="admin/pagamentos.php">Pagamentos</a>
        <a class="botao" href="admin/financeiro.php">Relatório financeiro</a>
        <a class="botao" href="admin/seguradoras-planos.php">Seguradoras e planos</a>
        <a class="botao" href="admin/beneficios.php">Benefícios e seguros</a>
        <a class="botao" href="admin/relatorios.php">Relatórios</a>
    </div>
</section>

<?php if ($u['papel'] === 'admin'): ?>
<section class="seccao">
    <h2>Recursos e materiais</h2>
    <div class="acoes-admin">
        <a class="botao" href="admin/stock.php">Stock de materiais</a>
        <a class="botao" href="admin/stock-encomendas.php">Encomendas</a>
        <a class="botao" href="admin/stock-categorias.php">Categorias de stock</a>
        <a class="botao" href="admin/stock-movimentos.php">Movimentos de stock</a>
    </div>
</section>

<section class="seccao">
    <h2>Administração do sistema</h2>
    <div class="acoes-admin">
        <a class="botao" href="admin/utilizadores.php">Utilizadores e permissões</a>
        <a class="botao" href="admin/utilizadores-novo.php">Novo utilizador</a>
        <a class="botao" href="admin/configuracoes.php">Configurações</a>
        <a class="botao" href="admin/editor-site.php">Editor do site</a>
        <?php if ($u['papel'] === 'admin'): ?><a class="botao" href="admin/auditoria.php">Auditoria / rastreabilidade</a><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>