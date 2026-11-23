<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/servicos/importacao.php';

$testes = [];
$testes['CSV separado por ponto e vírgula'] = static function (): bool {
    $r = importacao_analisar_csv("Nome;Email;Telefone\nAna Silva;ana@example.test;912345678");
    return !empty($r['ok']) && ($r['linhas'] ?? 0) === 1 && ($r['delimitador'] ?? '') === ';';
};
$testes['CSV separado por vírgula'] = static function (): bool {
    $r = importacao_analisar_csv("Name,Email\nAlice,a@example.test");
    return !empty($r['ok']) && ($r['linhas'] ?? 0) === 1 && ($r['delimitador'] ?? '') === ',';
};
$testes['UTF-8 com BOM'] = static function (): bool {
    $r = importacao_analisar_csv("\xEF\xBB\xBFNome;Email\nAna;ana@example.test");
    return !empty($r['ok']) && ($r['cabecalhos'][0] ?? '') === 'Nome';
};
$testes['UTF-16 LE com BOM'] = static function (): bool {
    $texto = "Nome;Email\nAna;ana@example.test";
    $conteudo = "\xFF\xFE" . mb_convert_encoding($texto, 'UTF-16LE', 'UTF-8');
    $r = importacao_analisar_csv($conteudo);
    return !empty($r['ok']) && ($r['encoding'] ?? '') === 'UTF-16 LE convertido para UTF-8';
};
$testes['Windows-1252'] = static function (): bool {
    $texto = "Nome;Observação\nJoão;Informação";
    $conteudo = iconv('UTF-8', 'Windows-1252', $texto);
    $r = importacao_analisar_csv((string)$conteudo);
    return !empty($r['ok']) && str_contains((string)($r['amostra'][0]['Observação'] ?? ''), 'Informação');
};
$testes['Cabeçalhos repetidos rejeitados'] = static function (): bool {
    $r = importacao_analisar_csv("Nome;Email;email\nAna;a@example.test;b@example.test");
    return empty($r['ok']) && str_contains((string)($r['erro'] ?? ''), 'repetidos');
};
$testes['Linhas irregulares sinalizadas'] = static function (): bool {
    $r = importacao_analisar_csv("Nome;Email\nAna;a@example.test;extra");
    return !empty($r['ok']) && ($r['linhas_irregulares'] ?? 0) === 1;
};
$testes['Emails repetidos sinalizados'] = static function (): bool {
    $r = importacao_analisar_csv("Nome;Email\nAna;a@example.test\nAna Silva;A@example.test");
    return !empty($r['ok']) && ($r['duplicados_email'] ?? 0) === 1;
};
$testes['Colunas clínicas detetadas'] = static function (): bool {
    $r = importacao_analisar_csv("Nome;Diagnóstico\nAna;Exemplo");
    return !empty($r['ok']) && in_array('Diagnóstico', $r['colunas_sensiveis'] ?? [], true);
};
$testes['CSV sem registos rejeitado'] = static function (): bool {
    $r = importacao_analisar_csv("Nome;Email\n");
    return empty($r['ok']);
};

$falhas = 0;
foreach ($testes as $nome => $teste) {
    try {
        $ok = $teste();
    } catch (Throwable $e) {
        $ok = false;
        echo "EXCEÇÃO: " . $e->getMessage() . PHP_EOL;
    }
    echo ($ok ? '[OK] ' : '[FALHOU] ') . $nome . PHP_EOL;
    if (!$ok) {
        $falhas++;
    }
}
echo count($testes) . ' testes; ' . $falhas . ' falhas.' . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
