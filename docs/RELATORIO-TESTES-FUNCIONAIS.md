# Relatório de Testes Funcionais (Fase 2)

**Projeto:** `C:\wamp64\www\meu-projeto\clinica`  
**Data:** 2026-11-09 (continuação da Fase 1; ver `docs/RELATORIO-AUDITORIA-CORRECOES.md`)  
**Método:** verificação em ambiente WAMP real + HTTP (GET, seguimento de redirecionamentos) via `Invoke-WebRequest` + inspeção MySQL/MariaDB read-only (`information_schema`, existência de tabelas/colunas) + análise de logs (`php_error.log`, `apache_error.log`) + revisão dirigida de código/fluxos. **Sem browser interativo** (sem consola JS, cliques, modais, validação visual completa).

## Regras cumpridas
- Não repetida a auditoria estática da Fase 1; trabalho continuado a partir dela.
- Sem alterações destrutivas na BD real; criado **dump de backup** da BD antes de verificações: `C:\Users\HP\AppData\Local\Temp\opencode\clinica_dentaria-backup-20261109-phase2.sql`.
- Sem dados fictícios inseridos na BD real. Sem testes CRUD autenticados que alterem dados reais (sem credenciais de teste fornecidas).
- Sem simulação de pagamentos reais; gateway não declarado funcional sem sandbox/credenciais.
- Resultados não inventados; limitações explicitadas.

## 1. Verificação do ambiente real

| Verificação | Resultado obtido | Estado |
|---|---|---|
| PHP CLI | `PHP 8.4.15` (`C:\wamp64\bin\php\php8.4.15\php.exe`) | APROVADA |
| Apache/PHP no WAMP | Apache `2.4.65`, PHP integrado no stack WAMP; HTTP local responde | APROVADA |
| Serviços WAMP | `wampapache64`, `wampmysqld64`, `wampmariadb64` = **Running** | APROVADA |
| MySQL/MariaDB | MySQL `8.4.7` acessível (`mysql.exe`/`mysqldump.exe` em `C:\wamp64\bin\mysql\mysql8.4.7\bin`) | APROVADA |
| BD `clinica_dentaria` | Existe e acessível com utilizador local padrão WAMP (read-only checks) | APROVADA |
| Tabelas | **44 tabelas** detetadas (incl. `configuracao_notificacoes`, `consentimentos_cookies`, `registo_admin`, `pagamentos`, tabelas de marcação/stock/seguros/conteúdo) — consistente com o domínio do projeto | APROVADA (existência) |
| Colunas críticas (amostra) | `pagamentos`: 22 colunas incluindo `consulta_id`, `metodo_detalhe`, `registado_por`, `data_pagamento`, `gateway`, `gateway_metodo`, `idempotency_key`, `falhado_em`, `cancelado_em` (alinhado com migração 002). `registo_admin`: inclui `motivo`, `antes_json`, `depois_json`, `user_agent` (migração 003). `configuracoes.valor`: `text` (migração 001). | APROVADA (alinhamento observado) |
| Migrações aplicadas | Sem tabela de tracking de migrações; evidência indireta indica 001/002/003 aplicadas no estado atual da BD. | APROVADA (inferência com evidência) |
| `php_error.log` | Erros históricos; **dois fatals críticos confirmados** durante Fase 2 (ver secção 8). Após correções, re-testes HTTP sem novo fatal nos casos corrigidos. | COM ERROS (encontrados e corrigidos) |
| `apache_error.log` | Entradas antigas: `guardar-tema.php` no path errado (Nov 07) — sem referência atual no código fonte; acessos indevidos a `includes/*` como scripts (esperável/bloqueável). | NÃO TESTADA (comportamento atual de browser/cache não reproduzido); risco histórico baixo |

## 2. Páginas públicas (HTTP GET, não autenticado quando aplicável)

| Funcionalidade | Página/ficheiro | Procedimento | Resultado esperado | Resultado obtido | Erro | Correção | Pós-correção | Estado |
|---|---|---|---|---|---|---|---|---|
| Página inicial | `index.php` | GET `http://localhost/meu-projeto/clinica/` e `/index.php` | 200, sem fatal PHP | 200, conteúdo HTML; inicialmente fatal `Cannot redeclare redes_sociais_definicoes()` em log; após correção 200, `LEN~17600`, sem marcador fatal no HTML | Redeclaração por `require` duplicado de `ui/redes-sociais.php` | `includes/ui/rodape.php`: `require_once` redes-sociais + uso de `$__pdoRod`; `includes/ui/redes-sociais.php`: `function_exists` guards | 200, sem fatal | APROVADA (HTTP/render base) |
| Institucional | `sobre.php` | GET | 200, sem fatal/warning | 200, `LEN=11523`, sem fatal/warning | — | — | — | APROVADA |
| Dentistas | `dentistas.php` | GET | 200 | 200, `LEN=14491` | — | — | — | APROVADA |
| Tratamentos | `tratamentos.php` | GET | 200 | 200, `LEN=16118` | — | — | — | APROVADA |
| Contactos | `contactos.php` | GET | 200 | 200, `LEN=12102` | — | — | — | APROVADA |
| Blog | `blog.php` | GET | 200 | 200, `LEN=9120` | — | — | — | APROVADA |
| Artigo | `artigo.php` | GET (sem/param padrão) | 200 ou resposta controlada, sem fatal | 200, `LEN=9198` | — | — | — | APROVADA (resposta HTTP) |
| Avaliar | `avaliar.php` | GET | 200 | 200, `LEN=9817` | — | — | — | APROVADA |
| Lista de espera | `lista-espera.php` | GET | 200 | 200, `LEN=9817` | — | — | — | APROVADA |
| Trabalha connosco | `trabalha-connosco.php` | GET | 200 | 200, `LEN=12105` | — | — | — | APROVADA |
| Seguros público | `seguros-publico.php` | GET | 200 | 200, `LEN=10310` | — | — | — | APROVADA |
| Seguros | `seguros.php` | GET (pode exigir contexto/login) | resposta controlada | 200 final (redirecionamento seguido) | — | — | — | APROVADA (HTTP) |
| Login | `login.php` | GET | 200 | 200, `LEN=9710` | — | — | — | APROVADA |
| Registo | `registo.php` | GET | 200 | 200, `LEN=11265` | — | — | — | APROVADA |
| Recuperar password | `recuperar-password.php` | GET | 200 | 200, `LEN=9751` | — | — | — | APROVADA |
| Aviso legal | `aviso-legal.php` | GET | 200 | 200, `LEN=12692` | — | — | — | APROVADA |
| Privacidade | `privacidade.php` | GET | 200 | 200, `LEN=14288` | — | — | — | APROVADA |
| Cookies | `cookies.php` | GET | 200, sem fatal | 200 inicial mas **fatal confirmado em log**: `Cannot redeclare consentimento_cookies()` (duplo `require` de `core/cookies.php`) | Redeclaração por `ui/rodape.php` usar `require` em vez de `require_once` + ausência de guards | `includes/ui/rodape.php`: `require_once core/cookies.php`; `includes/core/cookies.php`: guards `defined/function_exists` | 200, `LEN=11087`, sem fatal | APROVADA (pós-correção) |
| Marcação (entrada/passos) | `marcar.php`, `marcar-calendario/pacientes/tratamentos/resumo/pagamento/confirmacao.php` | GET não autenticado | redirect/login ou resposta controlada, sem 500/fatal | 200 final após redirects, sem fatal/warning no HTML | — | — | — | APROVADA (controlo de acesso/HTTP) |
| Área pessoal/páginas protegidas | `area-pessoal.php`, `perfil.php`, `notificacoes.php`, `minhas-marcacoes.php`, `meus-pagamentos.php` | GET não autenticado | redireciona para login | 200 final em `login.php` (ex.: `area-pessoal` → login, título “Iniciar sessão”) | — | — | — | APROVADA |

**Limitação:** “Conteúdos esperados aparecem corretamente” validado apenas por HTTP/ausência de fatals/marcadores básicos; **não** validado visualmente em browser (layout, secções colapsadas, imagens, JS).

## 3. Painel administrativo ↔ site público

| Funcionalidade | Procedimento | Resultado esperado | Resultado obtido | Estado |
|---|---|---|---|---|
| Acesso não autenticado | GET em 32 páginas `admin/*` (ver lista abaixo) com seguimento de redirects | exigir login / redirecionar para login, sem fatal | Todas retornaram 200 final com título de login, `fatal=false` | APROVADA (proteção HTTP) |
| RBAC por papel (código) | Revisão Fase 1/2 (amostra) | `exigir_login([...])` no servidor | Confirmado em todos os admin; amostra restrita (`configuracoes/utilizadores/auditoria`=admin; `pagamentos/download-cv`=admin,rececao) | APROVADA (código) |
| Reflexo de alterações admin → site público | Operações CRUD reais (ex.: alterar título, criar dentista/tratamento, imagens, hero/rodapé/contactos) | Dados guardados na BD e visíveis no site | **Não executado**: requer credenciais admin e pode alterar dados reais; sem conta de teste segura fornecida. | NÃO TESTADA (requer credenciais/ambiente de teste controlado) |

**Módulos admin testados via GET (não autenticado):** `index, artigos, auditoria, avaliacoes, beneficios, bloqueios, candidaturas, configuracoes, contactos, dentistas, editor-site, especialidades, financeiro, galeria, hero, horarios, lista-espera, marcacoes, pagamentos, parceiros, pedidos, perguntas-frequentes, relatorios, seguradoras-planos, stock, stock-categorias, stock-encomendas, stock-movimentos, testemunhos, tratamentos, utilizadores, utilizadores-novo`.

## 4. Módulos administrativos (CRUD/ações)

| Funcionalidade | Procedimento | Resultado esperado | Resultado obtido | Estado |
|---|---|---|---|---|
| Consultar/adicionar/editar/guardar/eliminar/aprovar/rejeitar/filtrar/exportar (por módulo) | Interação autenticada em cada módulo | Operações funcionais e persistidas | **Não executado** com sessão autenticada; risco de alterar dados reais e ausência de credenciais de teste. `php -l` limpo na Fase 1; presença de `exigir_login` confirmada. | NÃO TESTADA (limitação operacional/segurança de dados) |

## 5. Fluxos de marcação

| Funcionalidade | Procedimento | Resultado esperado | Resultado obtido | Estado |
|---|---|---|---|---|
| Acesso aos passos | GET nos passos de marcação sem login | redirect controlado | Redirect para login/fluxo controlado, sem fatal | APROVADA (HTTP/acesso) |
| Seleção dentista/paciente/tratamento, disponibilidade, reserva temporária, expiração, prevenção duplicados, preços/benefícios/seguros, resumo, confirmação, cancelamento, casos de erro | Teste E2E com utilizador autenticado e dados na BD | Fluxo completo consistente com `servicos/marcacao.php` e regras de agenda/pagamento | **Não executado E2E** (requer login, estado de sessão, escrita em BD). Lógica crítica revista na Fase 1/2; correção do fluxo de pagamento aplicada. | NÃO TESTADA (E2E funcional) / APROVADA (revisão de código + HTTP sem fatal) — classificar como **NÃO TESTADA** para E2E |

## 6. Sistema financeiro

| Funcionalidade | Procedimento | Resultado esperado | Resultado obtido | Estado |
|---|---|---|---|---|
| Páginas/controlo de acesso | GET `admin/pagamentos.php`, `admin/financeiro.php`, `meus-pagamentos.php` (não autenticado) | proteção por login | Redirect para login, sem fatal | APROVADA |
| Registo presencial, pendente/parcial/completo, reembolso, cancelamento, dívida, histórico, estados | Execução com dados de teste autenticados | Valores/estados corretos no servidor e admin | **Não executado** em runtime com escrita. Serviços `pagamentos.php`/`conta.php` revistos; confirmação cliente corrigida para usar `estado_financeiro_marcacao()`. | NÃO TESTADA (funcional com dados) |
| Pagamento online/gateway | Teste com sandbox | Confirmação apenas por webhook autenticado/idempotente | **Sem credenciais/sandbox**; fluxo gateway-aware corrigido no código; scaffold presente. | DEPENDENTE DE CONFIGURAÇÃO EXTERNA |

## 7. Frontend e JavaScript

| Funcionalidade | Procedimento | Resultado esperado | Resultado obtido | Estado |
|---|---|---|---|---|
| Erros PHP fatais visíveis nas páginas testadas | HTTP GET + pesquisa de marcadores | Sem fatal/parse/uncaught no output | Sem marcadores nos GET; dois fatals encontrados via **logs** e corrigidos | APROVADA (para páginas testadas, pós-correção) |
| Consola JS, botões, menus, modais, formulários, calendários, animações, tema claro/escuro, responsividade, sobreposições, imagens | Teste em browser real | Sem erros de interação/visual | **Não executado** (sem browser interativo). `tema.js`/`animacoes.js`/CSS presentes; `guardar-tema.php` sem refs atuais no código (erros apache antigos). | NÃO TESTADA |

## 8. Correções aplicadas na Fase 2

| Gravidade | Ficheiro(s) | Erro confirmado | Causa | Correção | Teste pós-correção |
|---|---|---|---|---|---|
| Crítica | `includes/ui/rodape.php`, `includes/ui/redes-sociais.php` | `Cannot redeclare redes_sociais_definicoes()` (afetava `index.php` e páginas com rodapé) | `ui/rodape.php` fazia `require` de `ui/redes-sociais.php` após já ter sido carregado noutro caminho; funções sem guards. | `require_once` no rodapé; uso de PDO existente; guards `function_exists` nas funções de redes sociais. | `php -l` OK; GET `index.php` 200, sem fatal, conteúdo maior. |
| Crítica | `includes/ui/rodape.php`, `includes/core/cookies.php` | `Cannot redeclare consentimento_cookies()` em `cookies.php` | `ui/rodape.php` fazia `require` de `core/cookies.php` duplicando inclusão; funções/const sem guards. | `require_once` no rodapé; guards `defined`/`function_exists` em `core/cookies.php`. | `php -l` OK; GET `cookies.php` 200, sem fatal. |

## 9. Classificação global (fase 2, com limitações)

| Área | Classificação |
|---|---|
| Ambiente WAMP/PHP/MySQL/BD/tabelas | **APROVADA** (com evidências read-only) |
| Páginas públicas (HTTP, pós-correções) | **APROVADA** |
| Acesso não autenticado a admin/área pessoal | **APROVADA** |
| Comunicação admin→público (alterações reais) | **NÃO TESTADA** |
| CRUD/ações admin autenticadas | **NÃO TESTADA** |
| Marcação E2E (autenticada/BD) | **NÃO TESTADA** |
| Financeiro funcional (escrita/estados reais) | **NÃO TESTADA** |
| Pagamentos online reais | **DEPENDENTE DE CONFIGURAÇÃO EXTERNA** |
| Frontend/JS visual e interativo | **NÃO TESTADA** |

## 10. Recomendações imediatas para completar a Fase 2 em ambiente controlado
1. Testar com utilizadores de teste por perfil (admin/receção/dentista/cliente) numa **cópia da BD**; executar CRUDs admin e confirmar reflexo no site.
2. Executar fluxo completo de marcação (incl. concorrência, expiração, cancelamento, alteração) e financeiro presencial/reembolso.
3. Validar gateway apenas em **sandbox** com webhook HTTPS autenticado.
4. Testar no browser: consola JS, tema claro/escuro, formulários, calendário, responsividade e acessibilidade.
5. Monitorizar `php_error.log`/`apache_error.log` durante testes E2E.