<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/marcacao.php';
require __DIR__ . '/includes/conta.php';

$u   = exigir_login();
$pdo = db();

limpar_reservas_expiradas($pdo);
$agora = agora_bd($pdo);

// Notificações
$naoLidas = nao_lidas($pdo, $u['id']);

// Próximas consultas do utilizador
$st = $pdo->prepare(
    "SELECT c.id, c.inicio, c.fim, c.duracao_min, c.estado, c.preco_total,
            p.nome_completo AS paciente, d.nome_completo AS dentista,
            m.referencia
       FROM consultas c
       JOIN marcacoes m ON m.id = c.marcacao_id
       JOIN pacientes p ON p.id = c.paciente_id
       JOIN dentistas d ON d.id = c.dentista_id
      WHERE m.utilizador_id = ?
        AND c.estado IN ('pendente','confirmada')
        AND c.inicio >= ?
      ORDER BY c.inicio
      LIMIT 5"
);
$st->execute([$u['id'], $agora->format('Y-m-d H:i:s')]);
$proximas = $st->fetchAll();

// Contagem de marcações ativas/passadas (para mostrar nos cartões)
$st = $pdo->prepare(
    "SELECT
        SUM(c.estado IN ('pendente','confirmada') AND c.inicio >= :agora) AS ativas,
        SUM(c.estado = 'concluida') AS concluidas,
        SUM(c.estado = 'cancelada') AS canceladas
       FROM consultas c
       JOIN marcacoes m ON m.id = c.marcacao_id
      WHERE m.utilizador_id = :uid"
);
$st->execute([':agora' => $agora->format('Y-m-d H:i:s'), ':uid' => $u['id']]);
// FIX: SUM() sem GROUP BY devolve NULL quando não há linhas; normalizar para int.
$rConta = $st->fetch() ?: [];
$conta = [
    'ativas'     => (int)($rConta['ativas']     ?? 0),
    'concluidas' => (int)($rConta['concluidas'] ?? 0),
    'canceladas' => (int)($rConta['canceladas'] ?? 0),
];

// Benefícios registados por este utilizador
$st = $pdo->prepare(
    "SELECT bp.estado_validacao
       FROM beneficios_paciente bp
       JOIN autorizacoes_paciente a ON a.paciente_id = bp.paciente_id AND a.utilizador_id = ?
      WHERE bp.estado_validacao IN ('registado','em_validacao','validado','rejeitado')
      ORDER BY bp.criado_em DESC
      LIMIT 20"
);
$st->execute([$u['id']]);
$beneficios = $st->fetchAll();
$beneficiosPendentes = 0;
foreach ($beneficios as $b) {
    if (in_array($b['estado_validacao'], ['registado', 'em_validacao'], true)) {
        $beneficiosPendentes++;
    }
}

$tituloPagina = 'A minha área';
require __DIR__ . '/includes/cabecalho.php';
echo estilos_conta();
?>

<h1>Olá, <?= e($u['nome']) ?> </h1>
<?= submenu_conta('inicio', $naoLidas) ?>

<div class="grelha seccao">
    <div class="cartao texto-centro">
        <p class="numero"><?= $conta['ativas'] ?></p>
        <p class="ajuda">Consultas por realizar</p>
    </div>
    <div class="cartao texto-centro">
        <p class="numero"><?= $conta['concluidas'] ?></p>
        <p class="ajuda">Concluídas</p>
    </div>
    <div class="cartao texto-centro">
        <p class="numero"><?= $conta['canceladas'] ?></p>
        <p class="ajuda">Canceladas</p>
    </div>
    <div class="cartao texto-centro">
        <p class="numero"><?= $naoLidas ?></p>
        <p class="ajuda">Notificações por ler</p>
    </div>
</div>

<section class="seccao">
    <h2>Próximas consultas</h2>
    <?php if (!$proximas): ?>
        <div class="alerta alerta-info">
            Não tens consultas marcadas.
            <a href="marcar.php">Marcar consulta</a>
        </div>
    <?php else: ?>
        <div class="grelha grelha-larga">
            <?php foreach ($proximas as $c):
                $ini = utc($c['inicio']);
                $fim = utc($c['fim']);
            ?>
                <article class="cartao consulta-cartao">
                    <strong><?= e(data_extenso($ini)) ?>, <?= e($ini->format('H:i')) ?> – <?= e($fim->format('H:i')) ?></strong>
                    <span><?= e($c['paciente']) ?> com <?= e($c['dentista']) ?></span>
                    <span class="ajuda">
                        <?= e(rotulo('marcacao', $c['estado'])) ?> ·
                        <a href="marcar-confirmacao.php?ref=<?= e(urlencode($c['referencia'])) ?>"><?= e($c['referencia']) ?></a>
                        · <?= e(formatar_preco($c['preco_total'])) ?>
                    </span>
                    <div class="acoes">
                        <a class="botao botao-secundario" href="minhas-marcacoes.php">Ver todas</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($beneficiosPendentes > 0): ?>
    <div class="alerta alerta-info" role="status">
        Tens <?= $beneficiosPendentes ?> benefício(s) a aguardar validação pela clínica.
        <a href="seguros.php">Ver seguros e benefícios</a>
    </div>
<?php endif; ?>

<section class="seccao">
    <h2>Atalhos</h2>
    <p>
        <a class="botao" href="marcar.php">Marcar nova consulta</a>
        <a class="botao botao-secundario" href="minhas-marcacoes.php">As minhas marcações</a>
        <a class="botao botao-secundario" href="perfil.php">Perfil e familiares</a>
        <a class="botao botao-secundario" href="seguros.php">Seguros e benefícios</a>
        <a class="botao botao-secundario" href="notificacoes.php">Notificações</a>
    </p>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>