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
$testes['Mapeamento automático de cabeçalhos portugueses'] = static function (): bool {
    $m = importacao_sugerir_mapeamento_pacientes([
        'Nome do Paciente', 'E-mail', 'Telemóvel', 'Data de Nascimento', 'Número SNS',
    ]);
    return ($m['nome_completo'] ?? '') === 'Nome do Paciente'
        && ($m['email'] ?? '') === 'E-mail'
        && ($m['telemovel'] ?? '') === 'Telemóvel'
        && ($m['data_nascimento'] ?? '') === 'Data de Nascimento'
        && ($m['numero_utente_sns'] ?? '') === 'Número SNS';
};
$testes['Mapeamento não reutiliza colunas'] = static function (): bool {
    $m = importacao_sugerir_mapeamento_pacientes(['Name', 'Full Name']);
    return ($m['nome_completo'] ?? '') === 'Full Name';
};
$testes['Datas reconhecidas e rejeição de datas inválidas'] = static function (): bool {
    return importacao_data_iso('31/12/1990') === '1990-12-31'
        && importacao_data_iso('1990-12-31') === '1990-12-31'
        && importacao_data_iso('31/02/1990') === null;
};
$testes['Validação e correspondência por email na base de dados'] = static function (): bool {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec("CREATE TABLE pacientes (id INTEGER PRIMARY KEY, utilizador_id INTEGER, nome_completo TEXT, email TEXT, telemovel TEXT, data_nascimento TEXT)");
    $pdo->exec("CREATE TABLE utilizadores (id INTEGER PRIMARY KEY, email TEXT, papel TEXT)");
    $pdo->exec("INSERT INTO pacientes (id, nome_completo, email) VALUES (1, 'Paciente Existente', 'existente@example.test')");
    $mapeamento = [
        'nome_completo' => 'Nome',
        'email' => 'E-mail',
        'telemovel' => 'Telefone',
        'data_nascimento' => 'Nascimento',
    ];
    $registos = [
        ['nome_completo' => 'Ana Silva', 'email' => 'existente@example.test', 'telemovel' => '912345678', 'data_nascimento' => '31/12/1990'],
        ['nome_completo' => '', 'email' => 'errado', 'telemovel' => '12', 'data_nascimento' => '31/02/1990'],
    ];
    $r = importacao_validar_registos_pacientes($pdo, $registos, $mapeamento);
    return ($r['validos'] ?? -1) === 1
        && ($r['invalidos'] ?? -1) === 1
        && ($r['correspondencias_email_base_dados'] ?? -1) === 1
        && !empty($r['comparacao_base_dados_disponivel']);
};
$testes['Possíveis duplicados dentro do ficheiro'] = static function (): bool {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec("CREATE TABLE pacientes (id INTEGER PRIMARY KEY, utilizador_id INTEGER, nome_completo TEXT, email TEXT, telemovel TEXT, data_nascimento TEXT)");
    $pdo->exec("CREATE TABLE utilizadores (id INTEGER PRIMARY KEY, email TEXT, papel TEXT)");
    $mapeamento = ['nome_completo' => 'Nome', 'email' => 'Email', 'telemovel' => 'Telefone'];
    $registos = [
        ['nome_completo' => 'Ana Silva', 'email' => 'ana@example.test', 'telemovel' => '912345678'],
        ['nome_completo' => 'Ana Silva', 'email' => 'ANA@example.test', 'telemovel' => '912345678'],
        ['nome_completo' => 'Bruno Costa', 'email' => 'bruno@example.test', 'telemovel' => '912345678'],
    ];
    $r = importacao_validar_registos_pacientes($pdo, $registos, $mapeamento);
    return ($r['possiveis_duplicados_ficheiro'] ?? -1) === 2;
};
$testes['Comparação por telefone e nome/data na base existente'] = static function (): bool {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec("CREATE TABLE pacientes (id INTEGER PRIMARY KEY, utilizador_id INTEGER, nome_completo TEXT, email TEXT, telemovel TEXT, data_nascimento TEXT)");
    $pdo->exec("CREATE TABLE utilizadores (id INTEGER PRIMARY KEY, email TEXT, papel TEXT)");
    $pdo->exec("INSERT INTO pacientes (id, nome_completo, telemovel, data_nascimento) VALUES (1, 'Paciente Telefone', '912 345 678', NULL)");
    $pdo->exec("INSERT INTO pacientes (id, nome_completo, telemovel, data_nascimento) VALUES (2, 'Paciente Maria', NULL, '1985-03-02')");
    $registos = [
        ['nome_completo' => 'Outra Pessoa', 'email' => '', 'telemovel' => '912345678', 'data_nascimento' => ''],
        ['nome_completo' => 'Paciente Maria', 'email' => '', 'telemovel' => '', 'data_nascimento' => '02/03/1985'],
    ];
    $r = importacao_comparar_registos_pacientes($pdo, $registos);
    return !empty($r['ok'])
        && ($r['por_telefone'] ?? -1) === 1
        && ($r['por_nome_data'] ?? -1) === 1
        && ($r['total'] ?? -1) === 2;
};
$testes['Datas de nascimento futuras são rejeitadas'] = static function (): bool {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec("CREATE TABLE pacientes (id INTEGER PRIMARY KEY, utilizador_id INTEGER, nome_completo TEXT, email TEXT, telemovel TEXT, data_nascimento TEXT)");
    $pdo->exec("CREATE TABLE utilizadores (id INTEGER PRIMARY KEY, email TEXT, papel TEXT)");
    $r = importacao_validar_registos_pacientes(
        $pdo,
        [['nome_completo' => 'Ana Silva', 'data_nascimento' => '31/12/2999']],
        ['nome_completo' => 'Nome', 'data_nascimento' => 'Nascimento']
    );
    return ($r['invalidos'] ?? -1) === 1;
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
