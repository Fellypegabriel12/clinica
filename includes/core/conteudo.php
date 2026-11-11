<?php
declare(strict_types=1);

if (!function_exists('conteudo')) {
    function conteudo(string $chave, string $fallback = ''): string
    {
        static $cache = null;
        if ($cache === null) {
            try {
                $st = db()->query('SELECT chave, valor FROM conteudo_site');
                $cache = [];
                foreach ($st as $r) { $cache[$r['chave']] = $r['valor']; }
            } catch (Throwable $e) { $cache = []; }
        }
        return $cache[$chave] ?? $fallback;
    }
}
if (!function_exists('conteudo_html')) {
    function conteudo_html(string $chave, string $fallback = ''): string
    {
        return nl2br(conteudo($chave, $fallback));
    }
}
if (!function_exists('conteudo_lista')) {
    function conteudo_lista(string $chave, string $fallback = ''): array
    {
        $texto = conteudo($chave, $fallback);
        return array_values(array_filter(array_map('trim', explode("\n", $texto))));
    }
}
if (!function_exists('conteudo_por_grupo')) {
    function conteudo_por_grupo(PDO $pdo, string $grupo): array
    {
        $st = $pdo->prepare('SELECT * FROM conteudo_site WHERE grupo = ? ORDER BY chave');
        $st->execute([$grupo]);
        return $st->fetchAll();
    }
}
if (!function_exists('conteudo_grupos')) {
    function conteudo_grupos(): array
    {
        return [
            'hero'        => 'Hero (topo da página inicial)',
            'prep'        => 'Consulta de Preparação',
            'servicos'    => 'Serviços',
            'equipa'      => 'Equipa',
            'navegacao'   => 'Menu e navegação',
            'sobre'       => 'A Nossa Clínica',
            'contactos'   => 'Contactos',
            'trabalha'    => 'Trabalha connosco',
            'rodape'      => 'Rodapé',
            'seo'         => 'SEO (Google)',
            'legal'       => 'Aviso legal',
            'privacidade' => 'Política de Privacidade',
        ];
    }
}