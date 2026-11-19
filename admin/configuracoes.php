<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/gestao.php';
require __DIR__ . '/../includes/gateway.php';

$u   = exigir_login(['admin']);
$pdo = db();

$campos = [
    'bloco_agenda_min'              => ['Bloco da agenda (min)',           'int',   'De 15 a 60.'],
    'reserva_temporaria_min'        => ['Reserva temporária (min)',        'int',   'Quanto tempo um horário fica reservado.'],
    'cancelamento_antecedencia_h'   => ['Antecedência para cancelar (h)',  'int',   'Mínimo de horas antes da consulta.'],
    'sinal_percentagem'             => ['Sinal (%)',                       'int',   'Percentagem do total cobrada como sinal.'],
    'pagamento_limite_min'          => ['Prazo para pagar online (min)',   'int',   'Após este prazo, a marcação é cancelada.'],
    'pagamento_presencial_ativo'    => ['Aceitar pagamento presencial',   'bool',  '1 = sim, 0 = não.'],
    'pagamento_sinal_ativo'         => ['Aceitar sinal',                   'bool',  '1 = sim, 0 = não.'],
    'pagamento_online_ativo'        => ['Aceitar pagamento eletrónico',    'bool',  '1 = sim, 0 = não. Só fica disponível com Stripe totalmente configurado (chave secreta + chave publicável + segredo do webhook).'],
    'metodos_pagamento_presenciais' => ['Métodos presenciais (códigos)',    'text',  'CSV: dinheiro,cartao_debito,cartao_credito,mbway_presencial,transferencia,outro'],
    'gateway_pagamentos'            => ['Gateway online',                   'text',  'stripe, eupago ou vazio. O Stripe usa as credenciais do ambiente/.env ou os campos abaixo.'],
    'gateway_ambiente'              => ['Ambiente gateway',                 'text',  'teste ou producao.'],
    'site_base_url'                 => ['URL público do site',              'text',  'URL absoluto usado nos retornos do gateway (ex.: https://clinicadentaria.pt ou https://dominio.pt/clinica), sem barra final. Vazio = deteta a partir do pedido.'],
    'gateway_webhook_segredo'       => ['Segredo webhook (gateway)',        'password',  'Token para autenticar callbacks. Deixar em branco para manter o valor atual.'],
    'stripe_publishable_key'        => ['Stripe chave publicável (API)',    'text',  'pk_test_... ou pk_live_.... É pública e pode ir para o browser; não dá acesso à conta.'],
    'stripe_secret_key'             => ['Stripe chave secreta (API)',       'password',  'sk_test_... para testes. Nunca colar chaves live em ambiente de teste. Deixar em branco para manter.'],
    'stripe_webhook_secret'         => ['Stripe segredo do webhook',        'password',  'whsec_... do endpoint ou do Stripe CLI. Deixar em branco para manter.'],
    'eupago_api_key'                => ['Eupago API key (sandbox/prod.)',   'password',  'Opcional; credenciais do fornecedor. Deixar em branco para manter.'],
    'bloqueio_login_min'            => ['Bloqueio após logins falhados (min)', 'int', 'Após N tentativas falhadas.'],
    'max_tentativas_login'          => ['Máx. tentativas de login',        'int',   'Antes de bloquear.'],
    'moeda'                         => ['Moeda',                           'text',  'Ex.: EUR.'],

    'clinica_nome'                  => ['Nome da clínica',                'text',  'Denominação legal/comercial (usada no site, SEO e rodapé).'],
    'clinica_logotipo_url'          => ['Logótipo (caminho/URL)',         'text',  'Ex.: uploads/identidade/logo.png ou URL. Vazio = usa o ícone padrão.'],
    'clinica_favicon_url'           => ['Favicon/ícone (caminho/URL)',    'text',  'Ex.: uploads/identidade/favicon.png. Vazio = sem favicon personalizado.'],
    'clinica_nif'                   => ['NIF',                             'text',  'Número de identificação fiscal.'],
    'clinica_ers'                   => ['Registo ERS',                     'text',  'Número de registo na ERS.'],
    'clinica_morada'                => ['Morada',                          'text',  'Morada completa.'],
    'clinica_diretor'               => ['Diretor clínico',                'text',  'Nome do diretor clínico.'],
    'clinica_diretor_omd'           => ['Cédula OMD do diretor',          'text',  'Ex.: Cédula OMD n.º 12345.'],
    'licenca_funcionamento_ers'     => ['Licença de funcionamento (ERS)', 'text',  'N.º de licença da clínica.'],

    'mapa_embed_url'                => ['URL do mapa (embed)',            'text',  'Ex.: https://www.google.com/maps/embed?...'],
    'mapa_link'                     => ['Link do Google Maps',            'text',  'Link para abrir a localização.'],
    'contacto_email'                => ['Email de contacto público',      'text',  'Email mostrado no site/contactos.'],
    'contacto_telefone'             => ['Telefone público',                'text',  'Aparece na página de contactos.'],
    'whatsapp_numero'               => ['Número de WhatsApp',             'text',  'Ex.: 351912345678 (sem espaços).'],

    'email_recepcao_contactos'      => ['Email receção: mensagens de contacto', 'text', 'Destino interno para mensagens do formulário (registo em BD continua ativo).'],
    'email_recepcao_consultas'      => ['Email receção: consultas/marcações',   'text', 'Endereço para receber pedidos/confirmações de consulta (quando envio de email estiver ativo).'],
    'email_notificacoes_remetente'  => ['Email remetente (notificações)',       'text', 'Endereço usado como remetente em emails automáticos (requer transporte configurado).'],
    'email_notificacoes_nome'       => ['Nome do remetente (notificações)',     'text', 'Ex.: nome da clínica.'],

    'smtp_ativo'                    => ['SMTP ativo',                     'bool',  'Ativar quando o transporte SMTP estiver integrado e testado.'],
    'smtp_host'                     => ['SMTP host',                      'text',  'Servidor SMTP (ex.: smtp.seudominio.pt).'],
    'smtp_porta'                    => ['SMTP porta',                     'int',   'Normalmente 587 (TLS) ou 465 (SSL).'],
    'smtp_seguranca'                => ['SMTP segurança',                 'text',  'tls, ssl ou vazio.'],
    'smtp_utilizador'               => ['SMTP utilizador',                'text',  'Conta de autenticação SMTP.'],
    'smtp_password'                 => ['SMTP palavra-passe',             'password', 'Deixar em branco para manter. Guardar com cuidado; não expor.'],

    'rede_instagram_ativa'          => ['Mostrar Instagram',               'bool',  'Desative para ocultar sem apagar o link.'],
    'instagram_url'                 => ['Instagram (URL)',                'text',  'Link completo.'],
    'rede_facebook_ativa'           => ['Mostrar Facebook',                'bool',  ''],
    'facebook_url'                  => ['Facebook (URL)',                 'text',  'Link completo.'],
    'rede_tiktok_ativa'             => ['Mostrar TikTok',                  'bool',  ''],
    'tiktok_url'                    => ['TikTok (URL)',                   'text',  'Link completo.'],
    'rede_whatsapp_ativa'           => ['Mostrar WhatsApp (rede)',         'bool',  'Usa o link/número configurado.'],
    'whatsapp_url'                  => ['WhatsApp (link opcional)',       'text',  'Se vazio, usa o número de WhatsApp.'],
    'rede_telegram_ativa'           => ['Mostrar Telegram',                'bool',  ''],
    'telegram_url'                  => ['Telegram (URL)',                 'text',  'Canal, grupo ou perfil.'],
    'rede_youtube_ativa'            => ['Mostrar YouTube',                 'bool',  ''],
    'youtube_url'                   => ['YouTube (URL)',                  'text',  'Link completo.'],
    'rede_linkedin_ativa'           => ['Mostrar LinkedIn',                'bool',  ''],
    'linkedin_url'                  => ['LinkedIn (URL)',                 'text',  'Link completo.'],

    'anos_existencia'               => ['Anos de existência',             'int',   'Mostrado na homepage. 0 = esconder.'],
    'pacientes_satisfeitos'         => ['Pacientes satisfeitos',          'int',   'Mostrado na homepage. 0 = esconder.'],
    'consultas_realizadas'          => ['Consultas realizadas',           'int',   'Mostrado na homepage. 0 = esconder.'],

    'clinica_filosofia'             => ['Filosofia',                       'longtext', 'Aparece em sobre.php. Uma linha por parágrafo.'],
    'clinica_tecnologia'            => ['Tecnologia',                      'longtext', 'Aparece em sobre.php. Uma linha por parágrafo.'],
    'clinica_instalacoes'           => ['Instalações',                     'longtext', 'Aparece em sobre.php. Uma linha por parágrafo.'],
    'clinica_compromissos'          => ['Compromissos',                    'longtext', 'Aparece em sobre.php. Um compromisso por linha.'],
];

$erros = [];

/**
 * Processa um upload de identidade.
 * BUGFIX: devolve agora a path gravada (ou null se não houve upload), para o
 * chamador poder injetá-la em $valores e evitar que o loop seguinte a sobrescreva.
 * BUGFIX: limpa o ficheiro antigo (se estava em uploads/identidade/) depois de gravar o novo.
 */
function cfg_guardar_identidade_upload(PDO $pdo, string $fileKey, string $configKey, array $mimes, int $maxBytes, array &$erros): ?string
{
    $f = $_FILES[$fileKey] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $erros[] = 'Não foi possível enviar o ficheiro (' . $configKey . ').';
        return null;
    }
    if (($f['size'] ?? 0) > $maxBytes) {
        $erros[] = 'O ficheiro ' . $configKey . ' excede o tamanho máximo permitido.';
        return null;
    }
    $info = @getimagesize($f['tmp_name']);
    $mime = (is_array($info) && isset($info['mime'])) ? $info['mime'] : (string)($f['type'] ?? '');
    $ext = $mimes[$mime] ?? null;
    if ($ext === null && $configKey === 'clinica_favicon_url') {
        $nomeOrig = (string)($f['name'] ?? '');
        if (preg_match('/\.ico$/i', $nomeOrig) && in_array($mime, ['image/x-icon','image/vnd.microsoft.icon','application/octet-stream',''], true)) {
            $ext = 'ico';
        }
    }
    if ($ext === null) {
        $erros[] = 'Formato inválido para ' . $configKey . '. Use PNG, JPG ou WEBP (favicon também pode ser ICO).';
        return null;
    }
    $dir = __DIR__ . '/../uploads/identidade';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $nome = $configKey . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $nome)) {
        $erros[] = 'Não foi possível guardar o ficheiro (' . $configKey . ').';
        return null;
    }
    @chmod($dir . '/' . $nome, 0644);

    // Ler path antiga para poder apagar o ficheiro órfão
    $st = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave = ?');
    $st->execute([$configKey]);
    $antiga = (string)($st->fetchColumn() ?: '');

    $valor = 'uploads/identidade/' . $nome;
    $up = $pdo->prepare('UPDATE configuracoes SET valor = ? WHERE chave = ?');
    $ins = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?)');
    $stChk = $pdo->prepare('SELECT 1 FROM configuracoes WHERE chave = ?');
    $stChk->execute([$configKey]);
    if ($stChk->fetchColumn()) { $up->execute([$valor, $configKey]); }
    else { $ins->execute([$configKey, $valor]); }

    // Apagar ficheiro antigo se era um upload gerido por nós (não apaga URLs externas)
    if ($antiga !== '' && str_starts_with($antiga, 'uploads/identidade/')) {
        $ficheiroAntigo = __DIR__ . '/../' . $antiga;
        if (is_file($ficheiroAntigo) && $ficheiroAntigo !== $dir . '/' . $nome) {
            @unlink($ficheiroAntigo);
        }
    }

    return $valor;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $valores = $_POST['cfg'] ?? [];

    // Uploads opcionais de identidade (SVG desativado por segurança).
    // BUGFIX crítico: as paths devolvidas são injetadas em $valores para o loop
    // seguinte não as sobrescrever com o valor (antigo/vazio) do campo de texto.
    try {
        $novoLogo = cfg_guardar_identidade_upload($pdo, 'clinica_logo_upload', 'clinica_logotipo_url', ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'], 2 * 1024 * 1024, $erros);
        if ($novoLogo !== null) { $valores['clinica_logotipo_url'] = $novoLogo; }
        $novoFavicon = cfg_guardar_identidade_upload($pdo, 'clinica_favicon_upload', 'clinica_favicon_url', ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'], 512 * 1024, $erros);
        if ($novoFavicon !== null) { $valores['clinica_favicon_url'] = $novoFavicon; }
    } catch (Throwable $e) {
        error_log('Upload identidade: ' . $e->getMessage());
        $erros[] = 'Ocorreu um erro ao processar os ficheiros de identidade.';
    }

    try {
        $pdo->beginTransaction();
        $up  = $pdo->prepare('UPDATE configuracoes SET valor = ? WHERE chave = ?');
        $ins = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?)');

        foreach ($campos as $chave => [$rotulo, $tipo, $ajuda]) {
            $v = (string)($valores[$chave] ?? '');

            if ($tipo === 'password') {
                $v = trim($v);
                if ($v === '') {
                    continue; // manter valor atual
                }
                $v = mb_substr($v, 0, 255);
            } elseif ($tipo === 'int') {
                if ($v === '') {
                    continue; // campo opcional vazio: manter valor atual/default
                }
                if (!ctype_digit($v) || (int)$v < 0 || (int)$v > 99999) {
                    $erros[] = $rotulo . ': deve ser um número inteiro ≥ 0.';
                    continue;
                }
            } elseif ($tipo === 'bool') {
                $v = ($v === '1') ? '1' : '0';
            } elseif ($tipo === 'longtext') {
                $v = mb_substr(trim($v), 0, 5000);
            } else {
                $v = mb_substr(trim($v), 0, 255);
            }

            if ($erros === []) {
                $st = $pdo->prepare('SELECT 1 FROM configuracoes WHERE chave = ?');
                $st->execute([$chave]);
                if ($st->fetchColumn()) {
                    $up->execute([$v, $chave]);
                } else {
                    $ins->execute([$chave, $v]);
                }
            }
        }

        if ($erros === []) {
            $pdo->commit();
            registar_admin('editar_configuracoes', 'configuracoes');
            flash_definir('sucesso', 'Configurações guardadas.');
            redirecionar('admin/configuracoes.php');
        } else {
            $pdo->rollBack();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Erro ao guardar configurações: ' . $e->getMessage());
        $erros[] = 'Ocorreu um erro. Tenta novamente.';
    }
}

$st = $pdo->query('SELECT chave, valor FROM configuracoes');
$atuais = [];
foreach ($st as $r) { $atuais[$r['chave']] = $r['valor']; }

$gatewayAtual  = trim((string)($atuais['gateway_pagamentos'] ?? ''));
$onlineAtivo   = (string)($atuais['pagamento_online_ativo'] ?? '0') === '1';
$gatewayPronto = gateway_pagamento_disponivel($pdo);

$tituloPagina = 'Configurações';
require __DIR__ . '/../includes/cabecalho.php';
echo estilos_gestao();
?>

<p><a href="admin/index.php">← Painel</a></p>
<h1>Configurações</h1>

<p class="ajuda">
    Estado do pagamento online: gateway = <strong><?= $gatewayAtual !== '' ? e($gatewayAtual) : 'vazio' ?></strong>;
    interruptor = <strong><?= $onlineAtivo ? 'ativo' : 'inativo' ?></strong>;
    método visível aos pacientes = <strong><?= ($onlineAtivo && $gatewayPronto) ? 'sim' : 'não' ?></strong>.
</p>
<?php if ($onlineAtivo && !$gatewayPronto): ?>
    <div class="alerta alerta-erro" role="alert">
        O pagamento eletrónico está marcado como ativo, mas o gateway não está operacional.
        Os pacientes não verão essa opção. Configure o Stripe (chave secreta + segredo de webhook)
        ou defina «Aceitar pagamento eletrónico» como «Não».
    </div>
<?php elseif ($gatewayAtual !== '' && !$gatewayPronto): ?>
    <div class="alerta alerta-info" role="status">
        O gateway «<?= e($gatewayAtual) ?>» está selecionado mas não é utilizável para cobranças
        (Eupago em scaffold ou credenciais Stripe incompletas). O pagamento presencial continua disponível.
    </div>
<?php endif; ?>
<p class="ajuda">Estes valores controlam o comportamento do site. As alterações aplicam-se imediatamente. Campos sensíveis (palavras-passe/segredos) ficam mascarados; deixe em branco para manter o valor atual. O envio automático de emails (SMTP) só funciona quando existir transporte/integração configurado e testado no servidor.</p>

<?php foreach ($erros as $erro): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="cartao">
    <form method="post" action="admin/configuracoes.php" enctype="multipart/form-data" novalidate>
        <?= csrf_campo() ?>
        <div class="cartao" style="margin-bottom:1rem;">
            <h2 style="margin-top:0;">Identidade visual (uploads opcionais)</h2>
            <p class="ajuda">Pode definir caminhos/URLs nos campos abaixo ou enviar ficheiros aqui. Após um envio bem-sucedido, o caminho é atualizado automaticamente.</p>
            <div class="campo">
                <label for="clinica_logo_upload">Logótipo (PNG/JPG/WEBP, máx. 2 MB)</label>
                <input type="file" id="clinica_logo_upload" name="clinica_logo_upload" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
            </div>
            <div class="campo">
                <label for="clinica_favicon_upload">Favicon/ícone (PNG/JPG/WEBP/ICO, máx. 512 KB)</label>
                <input type="file" id="clinica_favicon_upload" name="clinica_favicon_upload" accept=".png,.jpg,.jpeg,.webp,.ico,image/png,image/jpeg,image/webp,image/x-icon">
            </div>
            <p class="ajuda">Imagens da clínica/equipa/tratamentos: geridas em <a href="admin/hero.php">Hero</a>, <a href="admin/galeria.php">Galeria</a> e <a href="admin/dentistas.php">Dentistas</a>. Textos e menus: <a href="admin/editor-site.php">Editor do site</a>.</p>
        </div>

        <div class="tabela-scroll">
            <table class="tabela">
                <thead><tr><th>Configuração</th><th>Valor</th><th>Ajuda</th></tr></thead>
                <tbody>
                <?php foreach ($campos as $chave => [$rotulo, $tipo, $ajuda]): $v = $atuais[$chave] ?? ''; ?>
                    <tr>
                        <td><label for="cfg-<?= e($chave) ?>"><?= e($rotulo) ?></label></td>
                        <td>
                            <?php if ($tipo === 'bool'): ?>
                                <select id="cfg-<?= e($chave) ?>" name="cfg[<?= e($chave) ?>]">
                                    <option value="1" <?= $v === '1' ? 'selected' : '' ?>>Sim</option>
                                    <option value="0" <?= $v !== '1' ? 'selected' : '' ?>>Não</option>
                                </select>
                            <?php elseif ($tipo === 'int'): ?>
                                <input type="number" id="cfg-<?= e($chave) ?>" name="cfg[<?= e($chave) ?>]" min="0" max="99999" value="<?= e($v) ?>">
                            <?php elseif ($tipo === 'longtext'): ?>
                                <textarea id="cfg-<?= e($chave) ?>" name="cfg[<?= e($chave) ?>]" rows="4" maxlength="5000"><?= e($v) ?></textarea>
                            <?php elseif ($tipo === 'password'): ?>
                                <input type="password" id="cfg-<?= e($chave) ?>" name="cfg[<?= e($chave) ?>]" maxlength="255" value="" autocomplete="new-password" placeholder="<?= $v !== '' ? 'Valor configurado (deixar em branco para manter)' : '' ?>">
                            <?php else: ?>
                                <input type="text" id="cfg-<?= e($chave) ?>" name="cfg[<?= e($chave) ?>]" maxlength="255" value="<?= e($v) ?>">
                            <?php endif; ?>
                        </td>
                        <td><span class="ajuda"><?= e($ajuda) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p><button type="submit" class="botao">Guardar configurações</button></p>
    </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>