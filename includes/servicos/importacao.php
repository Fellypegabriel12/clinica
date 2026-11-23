<?php
declare(strict_types=1);

/**
 * Leitura e pré-validação de exportações CSV para migração.
 *
 * Esta primeira versão é deliberadamente read-only: não grava registos de
 * pacientes, contas, consultas ou pagamentos na base de dados.
 */

function importacao_normalizar_cabecalho(string $valor): string
{
    $valor = mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', $valor)), 'UTF-8');
    $valor = strtr($valor, [
        'á'=>'a', 'à'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
        'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
        'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
        'ó'=>'o', 'ò'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o',
        'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
        'ç'=>'c', 'ñ'=>'n',
    ]);
    if (function_exists('iconv')) {
        $transliterado = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        if (is_string($transliterado)) {
            $valor = $transliterado;
        }
    }
    $valor = preg_replace('/[^a-z0-9]+/', '_', $valor) ?? '';
    return trim($valor, '_');
}

/** @return array{delimitador:string,campos:int} */
function importacao_detectar_delimitador(string $primeiraLinha): array
{
    $melhor = ['delimitador' => ';', 'campos' => 0];
    foreach ([";", ",", "\t", "|"] as $delimitador) {
        $campos = str_getcsv($primeiraLinha, $delimitador, '"', '');
        $quantidade = is_array($campos) ? count($campos) : 0;
        if ($quantidade > $melhor['campos']) {
            $melhor = ['delimitador' => $delimitador, 'campos' => $quantidade];
        }
    }
    return $melhor;
}

/**
 * @return array{
 *   ok:bool,erro?:string,avisos?:array,delimitador?:string,encoding?:string,
 *   cabecalhos?:array,linhas?:int,linhas_vazias?:int,linhas_irregulares?:int,
 *   duplicados_email?:int,colunas_sensiveis?:array,amostra?:array
 * }
 */
function importacao_analisar_csv(string $conteudo): array
{
    if ($conteudo === '') {
        return ['ok' => false, 'erro' => 'O ficheiro está vazio.'];
    }
    $encoding = 'UTF-8';
    if (str_starts_with($conteudo, "\xFF\xFE")) {
        $conteudo = mb_convert_encoding(substr($conteudo, 2), 'UTF-8', 'UTF-16LE');
        $encoding = 'UTF-16 LE convertido para UTF-8';
    } elseif (str_starts_with($conteudo, "\xFE\xFF")) {
        $conteudo = mb_convert_encoding(substr($conteudo, 2), 'UTF-8', 'UTF-16BE');
        $encoding = 'UTF-16 BE convertido para UTF-8';
    } elseif (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
        $conteudo = substr($conteudo, 3);
        $encoding = 'UTF-8 com BOM';
    } elseif (!mb_check_encoding($conteudo, 'UTF-8')) {
        $convertido = function_exists('iconv')
            ? @iconv('Windows-1252', 'UTF-8//IGNORE', $conteudo)
            : false;
        if (!is_string($convertido) || !mb_check_encoding($convertido, 'UTF-8')) {
            return ['ok' => false, 'erro' => 'Não foi possível reconhecer a codificação. Exporta o ficheiro em UTF-8.'];
        }
        $conteudo = $convertido;
        $encoding = 'Windows-1252 convertido para UTF-8';
    }

    if (str_contains($conteudo, "\0")) {
        return ['ok' => false, 'erro' => 'O ficheiro parece conter dados binários. Exporta-o como CSV em UTF-8.'];
    }

    $primeiraLinha = strtok($conteudo, "\r\n");
    if (!is_string($primeiraLinha) || trim($primeiraLinha) === '') {
        return ['ok' => false, 'erro' => 'Não foi encontrada uma linha de cabeçalhos.'];
    }

    $detecao = importacao_detectar_delimitador($primeiraLinha);
    if ($detecao['campos'] < 2) {
        return ['ok' => false, 'erro' => 'Não foi possível identificar pelo menos duas colunas. Confirma se o ficheiro é CSV.'];
    }

    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        return ['ok' => false, 'erro' => 'Não foi possível preparar a leitura do ficheiro.'];
    }
    fwrite($stream, $conteudo);
    rewind($stream);

    $cabecalhosRaw = fgetcsv($stream, null, $detecao['delimitador'], '"', '');
    if (!is_array($cabecalhosRaw) || count($cabecalhosRaw) < 2 || count($cabecalhosRaw) > 60) {
        fclose($stream);
        return ['ok' => false, 'erro' => 'O CSV tem um número de colunas inválido. O limite é de 60.'];
    }

    $cabecalhos = [];
    $normalizados = [];
    $duplicadosCabecalho = [];
    foreach ($cabecalhosRaw as $indice => $cabecalho) {
        $rotulo = trim((string)$cabecalho);
        if ($indice === 0) {
            $rotulo = preg_replace('/^\xEF\xBB\xBF/', '', $rotulo) ?? $rotulo;
        }
        $normalizado = importacao_normalizar_cabecalho($rotulo);
        if ($rotulo === '' || $normalizado === '') {
            fclose($stream);
            return ['ok' => false, 'erro' => 'Existe uma coluna sem nome. Corrige os cabeçalhos antes de continuar.'];
        }
        if (isset($normalizados[$normalizado])) {
            $duplicadosCabecalho[] = $rotulo;
        }
        $normalizados[$normalizado] = $indice;
        $cabecalhos[] = $rotulo;
    }
    if ($duplicadosCabecalho !== []) {
        fclose($stream);
        return ['ok' => false, 'erro' => 'Existem cabeçalhos repetidos ou equivalentes: ' . implode(', ', array_unique($duplicadosCabecalho)) . '. Renomeia essas colunas para evitar associações erradas.'];
    }

    $emailIndice = null;
    foreach ($normalizados as $nome => $indice) {
        if (in_array($nome, ['email', 'e_mail', 'correio_eletronico'], true)) {
            $emailIndice = $indice;
            break;
        }
    }

    $sensivelPalavras = [
        'diagnostico', 'anamnese', 'processo_clinico', 'observacao_clinica',
        'radiografia', 'radiografias', 'medicacao', 'alergia', 'historico_saude',
        'dados_clinicos', 'doenca', 'patologia',
    ];
    $colunasSensiveis = [];
    foreach ($normalizados as $nome => $indice) {
        foreach ($sensivelPalavras as $palavra) {
            if (str_contains($nome, $palavra)) {
                $colunasSensiveis[] = $cabecalhos[$indice];
                break;
            }
        }
    }

    $linhas = 0;
    $linhasVazias = 0;
    $linhasIrregulares = 0;
    $duplicadosEmail = 0;
    $emailsVistos = [];
    $amostra = [];
    $maxLinhas = 10000;
    $maxCelulaBytes = 20000;

    while (($registo = fgetcsv($stream, null, $detecao['delimitador'], '"', '')) !== false) {
        if ($registo === [null] || $registo === []) {
            $linhasVazias++;
            continue;
        }
        $todosVazios = true;
        foreach ($registo as $valor) {
            if (trim((string)$valor) !== '') {
                $todosVazios = false;
                break;
            }
        }
        if ($todosVazios) {
            $linhasVazias++;
            continue;
        }

        $linhas++;
        if ($linhas > $maxLinhas) {
            fclose($stream);
            return ['ok' => false, 'erro' => 'O ficheiro ultrapassa o limite de 10 000 registos por pré-visualização. Divide a exportação em ficheiros mais pequenos.'];
        }

        if (count($registo) !== count($cabecalhos)) {
            $linhasIrregulares++;
            if (count($registo) > count($cabecalhos)) {
                continue;
            }
            $registo = array_pad($registo, count($cabecalhos), '');
        }

        $linha = [];
        foreach ($cabecalhos as $indice => $cabecalho) {
            $valor = (string)($registo[$indice] ?? '');
            if (strlen($valor) > $maxCelulaBytes) {
                fclose($stream);
                return ['ok' => false, 'erro' => 'Foi encontrada uma célula demasiado grande (mais de 20 KB). Verifica se o CSV inclui anexos codificados ou conteúdo indevido.'];
            }
            $linha[$cabecalho] = mb_substr(trim($valor), 0, 180);
        }

        if ($emailIndice !== null) {
            $email = mb_strtolower(trim((string)($registo[$emailIndice] ?? '')));
            if ($email !== '') {
                if (isset($emailsVistos[$email])) {
                    $duplicadosEmail++;
                }
                $emailsVistos[$email] = true;
            }
        }

        if (count($amostra) < 10) {
            $amostra[] = $linha;
        }
    }
    fclose($stream);

    if ($linhas === 0) {
        return ['ok' => false, 'erro' => 'O ficheiro contém cabeçalhos, mas não tem registos para analisar.'];
    }

    $avisos = [];
    if ($linhasIrregulares > 0) {
        $avisos[] = $linhasIrregulares . ' linha(s) têm um número de colunas diferente do cabeçalho. Devem ser revistas antes de qualquer importação.';
    }
    if ($duplicadosEmail > 0) {
        $avisos[] = $duplicadosEmail . ' email(s) repetidos dentro do próprio ficheiro. Isto não prova que sejam duplicados na base de dados atual.';
    }
    if ($colunasSensiveis !== []) {
        $avisos[] = 'Foram detetados cabeçalhos potencialmente clínicos. Utiliza apenas dados autorizados e um ambiente protegido; esta pré-visualização não guarda o ficheiro.';
    }

    return [
        'ok' => true,
        'delimitador' => $detecao['delimitador'] === "\t" ? 'TAB' : $detecao['delimitador'],
        'encoding' => $encoding,
        'cabecalhos' => $cabecalhos,
        'linhas' => $linhas,
        'linhas_vazias' => $linhasVazias,
        'linhas_irregulares' => $linhasIrregulares,
        'duplicados_email' => $duplicadosEmail,
        'colunas_sensiveis' => $colunasSensiveis,
        'avisos' => $avisos,
        'amostra' => $amostra,
    ];
}
