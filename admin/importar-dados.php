<?php
declare(strict_types=1);

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/servicos/importacao.php';

$u = exigir_login(['admin']);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$analise = null;
$erros = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validar();

    $ficheiro = $_FILES['ficheiro_csv'] ?? null;
    if (!is_array($ficheiro)) {
        $erros[] = 'Seleciona um ficheiro CSV.';
    } elseif (($ficheiro['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $codigoErro = (int)($ficheiro['error'] ?? UPLOAD_ERR_NO_FILE);
        $erros[] = match ($codigoErro) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O ficheiro excede o limite de tamanho permitido pelo servidor.',
            UPLOAD_ERR_PARTIAL => 'O envio do ficheiro ficou incompleto. Tenta novamente.',
            UPLOAD_ERR_NO_FILE => 'Seleciona um ficheiro CSV.',
            default => 'Não foi possível receber o ficheiro.',
        };
    } else {
        $nome = (string)($ficheiro['name'] ?? '');
        $tamanho = (int)($ficheiro['size'] ?? 0);
        $temporario = (string)($ficheiro['tmp_name'] ?? '');

        if (strtolower(pathinfo($nome, PATHINFO_EXTENSION)) !== 'csv') {
            $erros[] = 'Nesta primeira versão são aceites apenas ficheiros CSV. Um ficheiro Excel pode ser exportado para CSV.';
        } elseif ($tamanho <= 0 || $tamanho > 5 * 1024 * 1024) {
            $erros[] = 'O ficheiro deve ter entre 1 byte e 5 MB.';
        } elseif (!is_uploaded_file($temporario)) {
            $erros[] = 'O ficheiro recebido não passou a validação de upload.';
        } else {
            $conteudo = file_get_contents($temporario);
            if (!is_string($conteudo) || strlen($conteudo) !== $tamanho) {
                $erros[] = 'Não foi possível ler o ficheiro completo.';
            } else {
                $analise = importacao_analisar_csv($conteudo);
                if (empty($analise['ok'])) {
                    $erros[] = (string)($analise['erro'] ?? 'Não foi possível analisar o CSV.');
                    $analise = null;
                } else {
                    // A auditoria guarda apenas contagens, nunca valores de pacientes.
                    registar_admin(
                        'previsualizar_importacao_csv',
                        'importacao',
                        null,
                        'Pré-visualização CSV: ' . (int)$analise['linhas'] . ' registos, '
                            . count($analise['cabecalhos']) . ' colunas, delimitador '
                            . (string)$analise['delimitador'] . '. Nenhum dado foi importado.'
                    );
                }
            }
            unset($conteudo);
        }
    }
}

$tituloPagina = 'Importação de dados';
require __DIR__ . '/../includes/cabecalho.php';
?>

<p><a href="admin/seccao-sistema.php">← Administração do sistema</a></p>
<h1>Importar dados de outro sistema</h1>
<p class="ajuda">Ferramenta inicial para analisar exportações de programas antigos antes de planear a migração.</p>

<div class="alerta alerta-info" role="status">
    <strong>Modo de pré-visualização.</strong>
    Nesta fase, o ficheiro é lido temporariamente e analisado, mas nenhum paciente, funcionário,
    consulta, pagamento ou documento é gravado na base de dados.
</div>

<?php foreach ($erros as $erro): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endforeach; ?>

<section class="cartao">
    <h2>1. Escolher uma exportação</h2>
    <p class="ajuda">
        Aceita CSV até 5 MB, com cabeçalho na primeira linha. São detetados automaticamente
        os separadores ponto e vírgula, vírgula, tabulação e barra vertical.
        Também tenta reconhecer UTF-8, UTF-8 com BOM, UTF-16 e Windows-1252.
    </p>
    <form method="post" action="admin/importar-dados.php" enctype="multipart/form-data">
        <?= csrf_campo() ?>
        <div class="campo">
            <label for="ficheiro_csv">Ficheiro CSV</label>
            <input type="file" id="ficheiro_csv" name="ficheiro_csv" accept=".csv,text/csv" required>
        </div>
        <p class="ajuda">
            No Excel, normalmente podes usar <strong>Ficheiro → Guardar como → CSV UTF-8</strong>.
            Não envies ficheiros reais de pacientes para experiências sem autorização da clínica.
        </p>
        <button type="submit" class="botao">Analisar ficheiro</button>
    </form>
</section>

<?php if (is_array($analise)): ?>
    <section class="cartao">
        <h2>2. Resultado da análise</h2>
        <div class="tabela-scroll">
            <table class="tabela">
                <tbody>
                    <tr><th>Registos analisados</th><td><?= (int)$analise['linhas'] ?></td></tr>
                    <tr><th>Colunas detetadas</th><td><?= count($analise['cabecalhos']) ?></td></tr>
                    <tr><th>Linhas vazias ignoradas</th><td><?= (int)$analise['linhas_vazias'] ?></td></tr>
                    <tr><th>Linhas com colunas irregulares</th><td><?= (int)$analise['linhas_irregulares'] ?></td></tr>
                    <tr><th>Emails repetidos no ficheiro</th><td><?= (int)$analise['duplicados_email'] ?></td></tr>
                    <tr><th>Separador</th><td><?= e((string)$analise['delimitador']) ?></td></tr>
                    <tr><th>Codificação</th><td><?= e((string)$analise['encoding']) ?></td></tr>
                </tbody>
            </table>
        </div>

        <?php foreach (($analise['avisos'] ?? []) as $aviso): ?>
            <div class="alerta alerta-info" role="status"><?= e((string)$aviso) ?></div>
        <?php endforeach; ?>

        <?php if (!empty($analise['colunas_sensiveis'])): ?>
            <p class="ajuda">
                Cabeçalhos potencialmente clínicos detetados:
                <strong><?= e(implode(', ', $analise['colunas_sensiveis'])) ?></strong>.
                Esta análise não substitui a validação de permissões e de proteção dos dados.
            </p>
        <?php endif; ?>

        <h3>Colunas identificadas</h3>
        <ul>
            <?php foreach ($analise['cabecalhos'] as $cabecalho): ?>
                <li><?= e((string)$cabecalho) ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Amostra dos primeiros registos</h3>
        <p class="ajuda">São apresentados no máximo 10 registos. Os valores são truncados para facilitar a leitura.</p>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead>
                    <tr>
                        <?php foreach (array_slice($analise['cabecalhos'], 0, 8) as $cabecalho): ?>
                            <th><?= e((string)$cabecalho) ?></th>
                        <?php endforeach; ?>
                        <?php if (count($analise['cabecalhos']) > 8): ?>
                            <th>Outras colunas</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analise['amostra'] as $linha): ?>
                        <tr>
                            <?php foreach (array_slice($analise['cabecalhos'], 0, 8) as $cabecalho): ?>
                                <td><?= e((string)($linha[$cabecalho] ?? '')) ?></td>
                            <?php endforeach; ?>
                            <?php if (count($analise['cabecalhos']) > 8): ?>
                                <td>+<?= count($analise['cabecalhos']) - 8 ?> colunas</td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="alerta alerta-info" role="status">
            <strong>Importação não executada.</strong>
            Ainda falta identificar o sistema de origem, mapear os campos, detetar correspondências
            com os registos existentes e validar as relações. Não uses esta pré-visualização como
            prova de que os dados podem ser importados sem perdas.
        </div>
    </section>
<?php endif; ?>

<section class="cartao">
    <h2>O que esta ferramenta ainda não faz</h2>
    <ul>
        <li>Não liga diretamente a bases de dados ou APIs de programas externos.</li>
        <li>Não importa ficheiros Excel XLS/XLSX, XML, SQL, imagens ou documentos nesta versão.</li>
        <li>Não cria nem atualiza pacientes, contas, funcionários, consultas ou pagamentos.</li>
        <li>Não deteta duplicados entre o ficheiro e a base de dados atual.</li>
        <li>Não substitui o backup, o mapeamento dos campos nem a aprovação da clínica.</li>
    </ul>
    <p class="ajuda">
        Estas capacidades serão acrescentadas por fases, depois de conhecermos os formatos
        e as regras do sistema que pretendemos migrar.
    </p>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
