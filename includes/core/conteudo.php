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
if (!function_exists('sanitizar_url_editor')) {
    /**
     * Valida uma URL para href do editor de conteúdo.
     * Aceita apenas http, https, mailto e URLs relativas.
     * Devolve null quando a URL é inválida/perigosa.
     */
    function sanitizar_url_editor(string $url): ?string
    {
        // Decodifica entidades (ex.: jav&#x61;script:) antes de analisar.
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') { return null; }

        // Remove espaços, caracteres de controlo e formatadores (zero-width, etc.).
        // Browsers ignoram-nos em esquemas; removê-los evita bypasses do tipo "java\tscript:".
        $url = (string)preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        $url = (string)preg_replace('/[\p{Z}\p{C}]+/u', '', $url);
        if ($url === '') { return null; }
        if (mb_strlen($url, 'UTF-8') > 2048) { return null; }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m)) {
            $esquema = strtolower($m[1]);
            if (!in_array($esquema, ['http', 'https', 'mailto'], true)) {
                return null;
            }
        }
        return $url;
    }
}

if (!function_exists('sanitizar_html_editor')) {
    /**
     * Sanitiza HTML do editor de conteúdo com allowlist de tags e atributos.
     * Remove elementos ativos (script/style/iframe/...) e desembrulha tags não
     * permitidas, preservando o texto. Valida todos os href.
     */
    function sanitizar_html_editor(string $html): string
    {
        $html = trim($html);
        if ($html === '') { return ''; }
        if (mb_strlen($html, 'UTF-8') > 20000) {
            $html = mb_substr($html, 0, 20000, 'UTF-8');
        }
        if (!class_exists('DOMDocument')) {
            // Sem a extensão DOM não é possível sanitizar: devolver texto inerte.
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $permitidas = ['p','br','strong','em','a','ul','ol','li','h2','h3','h4'];
        $remover    = ['script','style','iframe','object','embed','form','input','button','select','textarea','svg','math','link','meta','base','noscript','template'];

        $doc = new DOMDocument('1.0', 'UTF-8');
        $anterior = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="__conteudo_editor__">' . $html . '</div></body></html>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if (!$ok) { return ''; }

        $raiz = $doc->getElementById('__conteudo_editor__');
        if (!$raiz) { return ''; }

        $nos = [];
        foreach ($raiz->getElementsByTagName('*') as $no) {
            $nos[] = $no;
        }

        for ($i = count($nos) - 1; $i >= 0; $i--) {
            $no = $nos[$i];
            $nome = strtolower($no->nodeName);

            if (in_array($nome, $remover, true)) {
                if ($no->parentNode !== null) { $no->parentNode->removeChild($no); }
                continue;
            }

            if (!in_array($nome, $permitidas, true)) {
                // Desembrulhar: mantém o conteúdo, remove a tag.
                $pai = $no->parentNode;
                if ($pai === null) { continue; }
                while ($no->firstChild !== null) {
                    $pai->insertBefore($no->firstChild, $no);
                }
                $pai->removeChild($no);
                continue;
            }

            $href   = $nome === 'a' ? $no->getAttribute('href') : '';
            $title  = $nome === 'a' ? $no->getAttribute('title') : '';
            $target = $nome === 'a' ? $no->getAttribute('target') : '';

            // Allowlist de atributos: remover todos antes de repor os válidos.
            $atributos = [];
            foreach ($no->attributes as $attr) { $atributos[] = $attr->nodeName; }
            foreach ($atributos as $nomeAttr) { $no->removeAttribute($nomeAttr); }

            if ($nome === 'a') {
                $hrefSeguro = sanitizar_url_editor($href);
                if ($hrefSeguro === null) {
                    // Link inválido: desembrulhar para texto simples.
                    $pai = $no->parentNode;
                    if ($pai !== null) {
                        while ($no->firstChild !== null) {
                            $pai->insertBefore($no->firstChild, $no);
                        }
                        $pai->removeChild($no);
                    }
                    continue;
                }
                $no->setAttribute('href', $hrefSeguro);
                $title = trim($title);
                if ($title !== '') { $no->setAttribute('title', mb_substr($title, 0, 200, 'UTF-8')); }
                if (strtolower(trim($target)) === '_blank') {
                    $no->setAttribute('target', '_blank');
                    $no->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }

        $saida = '';
        foreach ($raiz->childNodes as $filho) {
            $saida .= $doc->saveHTML($filho);
        }
        return trim($saida);
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