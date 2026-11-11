# Alterações realizadas

## 1. Backup (obrigatório)
- Cópia do projeto criada em: `C:\Users\HP\AppData\Local\Temp\opencode\clinica-backup-reorg-20261109` (antes da reorganização estrutural desta fase).
- Backups anteriores existentes no ambiente (sessões anteriores): projeto `C:\Users\HP\AppData\Local\Temp\opencode\clinica-backup-20261109-003318`; dump DB `C:\Users\HP\AppData\Local\Temp\opencode\db-backups\clinica_dentaria-20261109-012120.sql`.

## 2. Reorganização estrutural (segura, compatível)
### Criados (diretórios/ficheiros)
- `docs/` + `docs/.htaccess` (acesso negado).
- `docs/ESTRUTURA.md`, `docs/ARQUITETURA.md`, `docs/ALTERACOES.md`.
- `logs/` + `logs/.htaccess` (acesso negado).
- `includes/core/`, `includes/servicos/`, `includes/ui/`.

### Movidos (implementações internas) + wrappers de compatibilidade
As implementações foram movidas para subpastas de `includes/` e os caminhos antigos `includes/<ficheiro>.php` passaram a ser wrappers `require_once`:
- **core:** `config.php`, `conteudo.php`, `cookies.php`.
- **servicos:** `gateway.php`, `pagamentos.php`, `marcacao.php`, `gestao.php`, `stock.php`, `conta.php`, `consulta-preparacao.php`.
- **ui:** `cabecalho.php`, `rodape.php`, `hero.php`, `banner-cookies.php`, `numeros.php`, `parceiros.php`, `redes-sociais.php`, `testemunhos.php`, `whatsapp.php`.

**Correções de caminhos internos nas implementações movidas:**
- `includes/ui/cabecalho.php`, `rodape.php`: dependências para `../core/conteudo.php` e `../core/cookies.php` (quando aplicável); componentes UI mantidos no mesmo diretório.
- `includes/servicos/consulta-preparacao.php`: `../core/conteudo.php`.
- `includes/core/config.php`: deteção de `MODO_DEV` passou a aceitar `includes/.modo_dev` **ou** `includes/core/.modo_dev` (compatibilidade após movimento).

### Não movidos (decisão de compatibilidade)
- Entry points PHP da raiz e de `admin/` mantiveram localização/URLs para preservar links, formulários, redirects, `BASE_URL`, AJAX e webhooks/retornos de pagamento.

## 3. Segurança/proteção
- `.htaccess` (raiz): simplificado/limpo (ASCII), mantém bloqueio de extensões sensíveis e ficheiros sensíveis (incl. `RELATORIO-AUDITORIA.md`) e `Options -Indexes`. Removida regra `RedirectMatch` experimental adicionada durante a reorganização para evitar efeitos colaterais.
- `docs/.htaccess`, `logs/.htaccess`: acesso negado.
- `.gitignore`: atualizado para ignorar logs, temporários, backups/SQL soltos e conteúdo sensível de uploads/CV (mantendo `.htaccess`).

## 4. Correções técnicas pontuais (relacionadas com organização/qualidade)
- `admin/beneficios.php`, `admin/contactos.php`: removidos comentários residuais `// <-- FALTAVA`.
- Wrappers de `includes/` garantem compatibilidade com todos os `require/include` existentes.

## 5. Melhorias funcionais/UI/admin já presentes no estado atual (sessões recentes, preservadas)
> Não fazem parte da “nova funcionalidade” desta fase de reorganização; foram implementadas anteriormente e validadas por `php -l`/verificações pontuais.
- Hero CTA com contraste adaptado a claro/escuro; cartões “+ / Ver mais” para serviços/equipa na homepage; CSS associado.
- `includes/ui/cabecalho.php`: logótipo/favicon configuráveis, nome da clínica via configuração, rótulos de navegação via `conteudo_site` (com fallbacks).
- `includes/conteudo.php`: grupo `navegacao`.
- `admin/configuracoes.php`: novos campos (identidade visual URLs, emails operacionais, SMTP), campos sensíveis mascarados (manter valor se vazio), upload seguro de logótipo/favicon (raster/ICO com validação; SVG de upload desativado).
- `index.php`: uso de contactos configuráveis em pontos-chave e lógica de limite/“ver mais”.
- BD (aditivo, já aplicado no ambiente): 12 chaves `nav_*` em `conteudo_site` (grupo `navegacao`), inseridas com `INSERT IGNORE` quando executado.

## 6. Testes executados
- **`php -l` (automático):** 108 ficheiros PHP verificados (raiz, `admin`, `includes` recursivo, excluindo `uploads`) — **0 erros**.
- **Verificações estáticas dirigidas:** `grep` de padrões de includes/paths; confirmação da existência/remoção de temporários `_tmp*` (nenhum encontrado); verificação da estrutura de `includes/` e wrappers.
- **Não executados (requerem ambiente/navegador/credenciais):** E2E UI completo (página inicial, login/registo, área pessoal, fluxo completo de marcação, alteração/cancelamento), pagamentos reais e webhooks reais, testes manuais de uploads no browser, validação visual completa claro/escuro, AJAX/formulários em todos os ecrãs, gestão de stock/tratamentos em cenários completos. Estes devem ser validados manualmente no WAMP/navegador.

## 7. Ficheiros criados/alterados/movidos (resumo)
| Tipo | Caminho | Justificação |
|---|---|---|
| Criado | `docs/`, `docs/.htaccess`, `docs/ESTRUTURA.md`, `docs/ARQUITETURA.md`, `docs/ALTERACOES.md` | Documentação exigida e proteção. |
| Criado | `admin/auditoria.php` | Visualização de rastreabilidade (admin). |
| Criado | `migracoes/003_rastreabilidade_redes_sociais.sql` | Migração aditiva (auditoria reforçada + chaves redes sociais). |
| Criado | `logs/`, `logs/.htaccess` | Registos locais protegidos. |
| Criado | `includes/core/*`, `includes/servicos/*`, `includes/ui/*` | Organização por responsabilidade. |
| Alterado | `includes/*.php` (19 ficheiros) | Substituídos por wrappers de compatibilidade. |
| Alterado | `includes/core/config.php`, `includes/servicos/consulta-preparacao.php`, `includes/ui/cabecalho.php`, `includes/ui/rodape.php` | Caminhos internos após movimento + compatibilidade `.modo_dev`. |
| Alterado | `.htaccess`, `.gitignore` | Proteções e higiene de repositório. |
| Alterado | `admin/beneficios.php`, `admin/contactos.php` | Limpeza de comentários residuais. |
| Alterado (estado atual) | `README.md` | Instruções/estrutura atualizadas. |
| Alterado (estado atual, sessões recentes) | `css/estilo.css`, `index.php`, `includes/conteudo.php` (via core/wrapper), `admin/configuracoes.php` | Melhorias UI/admin/configuração preservadas; documentadas. |
| Eliminado | nenhum ficheiro funcional | Evitada eliminação destrutiva; temporários não encontrados. |
| BD aditiva | `conteudo_site` (`nav_*`) | Suporte à navegação configurável (executada no ambiente). |
