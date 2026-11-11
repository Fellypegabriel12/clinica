# Relatório de Auditoria Técnica e Correções

**Projeto:** `C:\wamp64\www\meu-projeto\clinica`  
**Data da auditoria/correções:** 2026-11-09 (sessão local)  
**Âmbito:** análise estática recursiva dos ficheiros reais do projeto + correções diretas em problemas confirmados + testes disponíveis no ambiente (PHP CLI).  
**Regra aplicada:** não confiar cegamente na documentação pré-existente; validar contra os ficheiros/código reais.

---

## 1. Resumo geral

Foi criada uma cópia de segurança completa do projeto antes de alterações e realizada uma auditoria técnica profunda com correções pontuais em problemas **confirmados** nos fluxos de pagamentos/confirmação e no endpoint de webhook. A base de código PHP mantém **0 erros de sintaxe** (`php -l` em 109 ficheiros) após as correções.

Não foram identificados, nesta passagem estática, `include/require` com alvo inexistente (verificador automático de resolução relativa/absoluta). Todos os ficheiros em `admin/*.php` contêm `exigir_login(...)` (controlo de acesso no servidor). Foram feitas verificações dirigidas de RBAC, CSRF, segurança de download de CV e fluxos críticos de pagamento/marcação.

**Limitação principal:** sem execução E2E no WAMP/navegador com base de dados real, sem credenciais/contrato de gateway (Eupago/Ifthenpay) e sem configuração/teste SMTP, não é possível confirmar comportamento funcional completo em runtime para todos os módulos. Funcionalidades dependentes de serviços externos/credenciais permanecem não concluídas por natureza (documentadas).

## 2. Backup e total de ficheiros analisados

| Item | Valor |
|---|---|
| Backup completo (cópia integral) | `C:\Users\HP\AppData\Local\Temp\opencode\clinica-backup-auditoria-20261109-222027` |
| Ficheiros no backup/projeto (inventário recursivo) | 130 |
| Ficheiros PHP (sintaxe verificada) | 109 |
| Erros de sintaxe PHP (`php -l`) antes/depois | 0 / 0 |
| Ambiente PHP CLI | `C:\wamp64\bin\php\php8.4.15\php.exe` (PHP 8.4.15 ZTS) |

Tipos presentes: PHP, SQL (migrações), CSS, JS, Markdown, `.htaccess`, `.gitignore`, imagens em `uploads/`.

## 3. Metodologia

- Inventário recursivo e leitura/análise dos ficheiros nucleares e de risco (auth, marcação, pagamentos, gateway, webhook, retorno, confirmação, admin sensível, downloads, includes/wrappers, migrações, `.htaccess`).
- Verificação automática: `php -l` em todos os `.php`; resolução estática de `require/include` e inspeção de `action` de formulários (heurística); pesquisa de padrões (`exigir_login` em admin, `query()` com strings estáticas, etc.).
- Revisão manual dirigida: `includes/core/config.php`, `servicos/marcacao.php|pagamentos.php|gateway.php|conta.php`, `marcar-pagamento*.php`, `pagamento-*.php`, `marcar-confirmacao.php`, `login.php`, `admin/download-cv.php`, `admin/{configuracoes,utilizadores,pagamentos,auditoria}.php` (RBAC), documentação interna.
- Correções aplicadas apenas quando o problema/risco foi **confirmado** ou a melhoria era necessária para segurança/correção de fluxo sem inventar credenciais.
- Re-teste: `php -l` nos ficheiros alterados e em todos os PHP.

## 4. Mapa de ligações/dependências (verificação estática)

| Verificação | Resultado |
|---|---|
| `require/include/require_once/include_once` com alvo inexistente | **0 ocorrências** detetadas pelo verificador de resolução |
| Padrão de wrappers `includes/` | Confirmado (compatibilidade `core/servicos/ui`) |
| Admin com `exigir_login` | **Todos** os `admin/*.php` contêm `exigir_login` |
| RBAC específico (amostra confirmada) | `admin/configuracoes.php` e `admin/utilizadores.php`/`auditoria.php` exigem `admin`; `admin/pagamentos.php` exige `admin,rececao`; `admin/download-cv.php` exige `admin,rececao` |
| CSRF | Confirmado em fluxos críticos revistos (`csrf_campo`/`csrf_validar`, ex.: login, marcação/pagamento demo). Outros formulários devem ser validados module a module em testes funcionais. |

> Nota: referências dinâmicas, rotas construídas por `BASE_URL`, JS/AJAX e chamadas condicionais podem não ser totalmente captadas por análise estática. Não foram eliminados wrappers/ficheiros por “aparente não uso”.

## 5. Problemas de ligações entre ficheiros

- **Confirmados nesta passagem:** nenhum include/require quebrado detetado estaticamente.
- **Não confirmados / a validar em runtime:** ações de formulário dependentes de estado/sessão, fluxos JS (`tema.js`, `animacoes.js`) e endpoints consumidos por AJAX/fetch (ver secção 10 e 15).

## 6. Dependências em falta

- **Código/PHP:** nenhuma dependência de ficheiro PHP em falta detetada pelo verificador estático.
- **Externas/configuração:** gateway de pagamento real (API/credenciais/contrato, ex. Eupago), transporte SMTP funcional para emails automáticos, e (para validação total) base de dados `clinica_dentaria` com esquema/migrações aplicadas no ambiente de teste.

## 7. Problemas de base de dados

| Gravidade | Achado | Estado |
|---|---|---|
| Média (documentada) | Não existe DDL completo/base integral versionado no repositório; apenas migrações aditivas 001–003. Tabelas/colunas base são parcialmente inferidas do código. | **Não corrigido por alteração de esquema** (evitar suposições destrutivas). Requer dump/DDL real ou migrações base controladas. |
| Baixa/Média | Consultas `SELECT/COUNT` estáticas via `$pdo->query()` em admin (sem interpolação de input) — padrão aceitável; mutações sensíveis revistos usam prepared statements. | **Observado**, sem alteração funcional. |
| Não verificado | Correspondência exata de todas as colunas/tabelas/índices/FKs contra uma BD viva. | Requer execução com BD real + aplicação de migrações. |

**Comunicação PDO:** `db()` singleton, `utf8mb4`, exceções, prepared statements em fluxos críticos revistos; transações/`FOR UPDATE` em reservas, pagamentos, reembolsos e cancelamentos (confirmado em serviços auditados).

## 8. Problemas de segurança

| Gravidade | Ficheiro | Localização/causa | Consequência | Correção aplicada | Teste |
|---|---|---|---|---|---|
| Média | `pagamento-webhook.php` | Aceitava GET e fundia `$_GET`/`$_POST`; condicional vazio (linhas antigas ~29–32) sem efeito; maior superfície para exposição de parâmetros em logs/URL. | Risco de má configuração/exposição se segredos fossem enviados por query string; código confuso. | **Corrigido:** endpoint passa a aceitar apenas **POST** (`405` caso contrário); leitura de `POST` + JSON body; removido condicional vazio; validação delegada ao gateway (fail-closed) e confirmação idempotente mantida. | `php -l`; revisão do fluxo. |
| Baixa (mitigação existente) | `marcar-pagamento-demo.php` | Simulação de pagamento. | Uso indevido fora de dev. | **Já protegido** por `MODO_DEV` + login + ownership + CSRF; mantido. | Revisão. |
| Média (config/ambiente) | `includes/core/config.php` | Credenciais BD por defeito (`root`/vazio), constantes no código. | Inadequado para produção. | **Não alterado** (evitar quebrar WAMP local/inventar credenciais). Documentado; requer configuração segura em produção. | N/A. |
| Baixa | `admin/download-cv.php` | Download controlado. | Risco de path traversal se mal validado. | **Sem correção necessária:** valida prefixo, bloqueia `..`/`\`, `realpath` contido em `uploads/cv`, RBAC `admin/rececao`. | Revisão. |
| Observação | Geral | Proteções de output/CSRF/RBAC/sessão presentes no núcleo; cobertura completa de todos os formulários admin/públicos não executada em browser. | Possíveis lacunas pontuais não detetadas estaticamente. | Auditoria dirigida; recomenda-se teste funcional completo. | Parcial. |

## 9. Problemas de pagamentos

| Gravidade | Ficheiro | Causa | Consequência | Correção aplicada | Teste |
|---|---|---|---|---|---|
| Média | `marcar-confirmacao.php` | Cálculo de “Já pago/Por pagar” somava apenas pagamentos `estado='pago'` pelo valor bruto, sem considerar modelo financeiro completo (`valor_reembolsado`, estados/serviço financeiro). | Valores exibidos podiam ser inconsistentes com reembolsos/parcialidades face ao resto do sistema. | **Corrigido:** inclui `includes/pagamentos.php` e usa `estado_financeiro_marcacao()`; exibe estado financeiro, pago líquido, reembolsado e dívida. | `php -l`; revisão. |
| Alta (funcional/arquitetural) | `marcar-pagamento.php` | Fluxo criava marcação/consultas/pagamento pendente e **encaminhava sempre** pagamentos não presenciais para `marcar-pagamento-demo.php`; sem caminho seguro para gateway real nem tratamento de falha de iniciação. | Pagamentos online reais não utilizáveis; risco de estados confusos se fluxo evoluísse sem controlo; demo não aplicável fora de dev. | **Corrigido/refatorado:** métodos online/demo/gateway distinguidos; `eletronico_antecipado` pode ser ativado com gateway configurado (não apenas MODO_DEV) mantendo demo em MODO_DEV; para modo `gateway`, `iniciarPagamento()` é chamado **dentro da transação** — em falha faz `rollBack` e mostra erro (sem registos órfãos confirmados); em sucesso atualiza metadados do pagamento e redireciona para gateway/retorno conforme resposta. Presencial inalterado. | `php -l`; revisão lógica/transacional. |
| Média (conhecida, dependência externa) | `includes/servicos/gateway.php`, webhook/retorno | Integração Eupago é **scaffold**; sem credenciais/API/implementação final do fornecedor. | Pagamentos online reais não processáveis. | **Não simulada/concluída** (proibido inventar credenciais). Código preparado: abstração, validação fail-closed no scaffold, confirmação idempotente em `gateway_confirmar_pagamento()`, webhook POST autenticado. | Revisão; teste real requer fornecedor/credenciais sandbox. |

**Controlos confirmados no design/código revisto:** valor vem do cálculo no servidor (`resumo_da_marcacao`/totais/sessão de marcação), CSRF em POST, criação em transação, reservas temporárias com expiração e limpeza; `pagamento-retorno.php` não confirma pagamentos (apenas estado); webhook exige validação do gateway e `idempotency_key`; confirmação financeira usa estado consistente no serviço.

## 10. Problemas do painel administrativo

| Gravidade | Achado | Estado |
|---|---|---|
| Baixa (não confirmado como bug) | Cobertura funcional de todos os botões/CRUDs (adicionar/editar/eliminar/aprovar/rejeitar/guardar) em ~35 módulos admin **não executada** em browser com BD. | **Não verificado em runtime**. `php -l` limpo; RBAC presente em todos os admin; amostra crítica revista. |
| Observação | Uso de `query()` estático em listagens/dashboards admin (sem input interpolado). | Aceitável; manter vigilância em futuras alterações. |

Módulos admin presentes (inventário): utilizadores, dentistas, especialidades, tratamentos, marcações, horários, bloqueios, pagamentos, financeiro, seguros/planos, stock (materiais/categorias/movimentos/encomendas), contactos, pedidos, candidaturas, download CV, artigos, galeria, hero, testemunhos, avaliações, parceiros, perguntas frequentes, editor do site, configurações, relatórios, auditoria, lista de espera, benefícios. **Funcionalidade E2E de cada um: validar manualmente no WAMP.**

## 11. Problemas do frontend

| Gravidade | Achado | Estado |
|---|---|---|
| Não verificado | Erros JS em runtime, eventos, responsividade, acessibilidade, tema claro/escuro em todas as páginas, conflitos CSS (`display/visibility/opacity/z-index/overflow`) e renderização da homepage. | Requer teste manual no browser (WAMP). `js/tema.js`, `js/animacoes.js`, `css/estilo.css` existem e fazem parte do layout; sem execução visual não se declaram bugs visuais. |

## 12. Funcionalidades incompletas

| Funcionalidade | Pode concluir localmente? | Estado |
|---|---|---|
| Pagamento online real (Eupago/Ifthenpay) | **Parcialmente** (arquitetura/correções feitas); conclusão real **não** sem credenciais/contrato/API e testes do fornecedor. | Scaffold + webhook idempotente/autenticado + fluxo gateway-aware melhorado; **pendente integração real**. |
| SMTP/emails automáticos | **Não totalmente** sem configuração/credenciais e implementação/transporte efetivo. | Campos/config podem existir; transporte funcional **não confirmado**. |
| DDL base completo versionado | **Sim**, mas exige dump/esquema real confiável (não inventar). | Apenas migrações aditivas presentes. |
| Lista de espera (automatização completa) | Depende do comportamento pretendido/BD; não validado em E2E. | A verificar. |
| Notas clínicas/funcionalidades clínicas avançadas | Depende de requisitos/BD/UI. | A verificar. |
| Testes E2E automatizados | **Sim** (implementação futura). | Em falta. |

## 13. Ficheiros corrigidos

| Ficheiro | Correção |
|---|---|
| `marcar-confirmacao.php` | Uso de `estado_financeiro_marcacao()`; exibição financeira consistente (líquido/reembolsos/dívida/estado). |
| `pagamento-webhook.php` | Apenas POST; remoção de merge com GET e condicional vazio; validação/confirmação idempotente mantidas. |
| `marcar-pagamento.php` | Fluxo gateway-aware; iniciação de gateway dentro da transação com rollback em falha; distinção demo/gateway; redirect seguro; preservação de presencial/demo MODO_DEV. |
| `docs/RELATORIO-AUDITORIA-CORRECOES.md` | **Criado** (este relatório). |

## 14. Descrição de cada correção

1. **Confirmação financeira (cliente):** eliminada inconsistência entre soma simples de `pago` e o modelo financeiro real (reembolsos/dívida líquida), usando o serviço central de pagamentos.
2. **Webhook endurecido:** reduzida exposição de parâmetros e clarificado o caminho de autenticação (gateway) + idempotência; método HTTP restrito a POST.
3. **Fluxo de pagamento na marcação:** separação clara entre presencial, demonstração (dev) e gateway real; prevenção de estados órfãos quando a iniciação do gateway falha (rollback). Mantida compatibilidade com o comportamento atual em MODO_DEV e com a ausência de credenciais (gateway scaffold devolve erro claro).

## 15. Testes executados e resultados reais

| Teste | Comando/método | Resultado real |
|---|---|---|
| Sintaxe PHP (baseline) | `php -l` recursivo em `*.php` | **109 ficheiros, 0 erros** |
| Sintaxe PHP (pós-correção) | `php -l` em ficheiros alterados + todos | **109 ficheiros, 0 erros** |
| Includes/requires em falta | Análise estática (resolução de caminhos) | **0 problemas** detetados |
| RBAC admin (presença) | Pesquisa `exigir_login` em `admin/*.php` | **Presente em todos** |
| RBAC admin (amostra) | Leitura de configurações/utilizadores/pagamentos/auditoria/download-cv | Papéis restritos conforme esperado |
| Revisão segurança pagamentos | Leitura de webhook/retorno/gateway/demo/marcação/confirmação | Controlos-chave presentes; webhook e fluxo de pagamento melhorados |
| Backup | `Copy-Item` recursivo + contagem | **OK** (`FILES=130`) |

**Não executados (motivo):** E2E browser/WAMP com BD viva; testes funcionais de formulários/CRUDs admin; pagamentos reais/sandbox com fornecedor; envio SMTP; validação visual responsiva/tema claro-escuro; AJAX/fetch em runtime. **Não foram inventados resultados.**

## 16. Problemas que continuam por resolver / não verificados

- Validação funcional completa de todos os módulos `admin/*` e páginas públicas em ambiente WAMP+BD.
- Integração real do gateway (credenciais, chamadas API, mapeamento exato de callbacks/webhooks do fornecedor, testes de sandbox/produção controlada).
- SMTP/transporte real de emails e testes de entrega.
- DDL/esquema base completo e verificação contra BD real (todas as tabelas/colunas/FKs/índices/procedures/views se existirem).
- Testes automatizados (PHPUnit/E2E).
- Auditoria visual/frontend completa (JS runtime, acessibilidade, temas, CSS).
- Revisão módulo a módulo de todos os formulários para CSRF/validação/autorização fina em casos não cobertos pela amostra.

## 17. Funcionalidades que dependem de serviços externos

- **Pagamentos online:** credenciais/contrato com fornecedor (ex. Eupago), configuração de chaves/segredos/webhook e validação conforme API/produto contratado.
- **Email/SMTP:** servidor/credenciais, configuração segura e testes.
- **Infra/BD de produção:** credenciais dedicadas, TLS/HTTPS, backups, políticas RGPD/legais operacionais.

## 18. Recomendações finais

1. Executar/aplicar migrações necessárias numa cópia da BD e validar todos os fluxos críticos (marcação → pagamento → confirmação/webhook simulado autenticado → cancelamento/reembolso).
2. Testar manualmente o painel admin por perfil (admin/receção/dentista) e confirmar que cada ação persiste e reflete no site.
3. Configurar gateway em **sandbox** primeiro; manter webhook com segredo forte, POST, HTTPS e idempotência; nunca confirmar por URL de retorno.
4. Remover/evitar uso de credenciais por defeito em produção; gerir segredos fora do código-fonte versionado.
5. Versionar DDL base (ou dump sanitizado de esquema) para reprodutibilidade.
6. Adicionar testes automatizados mínimos para reservas concorrentes, cálculos financeiros e transições de estado de pagamento/marcação.
7. Realizar validação visual e de acessibilidade (claro/escuro, mobile) e rever JS em consola do browser.

## 19. Mapa atualizado de dependências (resumo)

- **Entrada:** páginas raiz + `admin/*` → `includes/config.php` (wrapper) → `includes/core/config.php` (+ `core/conteudo.php`, `core/cookies.php` usados pela UI/serviços conforme necessário).
- **Serviços:** `marcacao.php` (agenda/reservas/benefícios/fluxo), `pagamentos.php` + `gateway.php` (financeiro/gateway), `conta.php` (área pessoal/cancelamento), `stock.php`, `gestao.php`, `consulta-preparacao.php`.
- **UI:** `cabecalho.php`/`rodape.php` e componentes em `includes/ui/*` (com wrappers de compatibilidade).
- **Dados:** MySQL `clinica_dentaria` via PDO; tabelas nucleares confirmadas por uso incluem `utilizadores`, `marcacoes`, `consultas`, `consultas_tratamentos`, `horarios_ocupados`, `pagamentos`, `configuracoes`, `registo_admin`, etc. (ver migrações e documentação técnica).
- **Integrações:** webhooks/retorno de pagamento, uploads (`uploads/`), recursos estáticos `css/`/`js/`.

---
*Relatório gerado com base em evidências estáticas e testes CLI disponíveis; itens não verificados em runtime estão explicitamente assinalados.*
## Atualização Fase 3 (2026-11-09, continuação) — correção confirmada em testes

### C-F3-01 (confirmado/corrigido): `estado_financeiro_marcacao` contabilizava pagamentos pendentes como liquidados
- **Evidência:** teste funcional em `clinica_dentaria_testes_phase3` (fixture isolada) mostrou que marcação com `total_previsto=100` e pagamento `presencial` `estado='pendente'` `valor=100` era calculada como `pago_liq=100, divida=0, estado=Pago` (incorreto), e `registar_pagamento_presencial_detalhado` rejeitava com "já está liquidada". Relatórios/admin filtravam por estado pago, criando inconsistência.
- **Causa:** `SUM(GREATEST(0, valor - valor_reembolsado))` sem filtro de `estado` em `includes/servicos/pagamentos.php::estado_financeiro_marcacao`.
- **Correção:** `pago_liq` soma apenas `pago,reembolsado,parcialmente_reembolsado`; `pendente_registado` inclui `parcialmente_pago`.
- **Ficheiros:** `includes/servicos/pagamentos.php` (original) + cópia de teste sincronizada.
- **Validação:** `php -l` OK. Fixtures (BD teste, limpas, original intacta): pendente 100€ → `Pendente, pago=0, divida=100`; parcial 40/100 → `Parcialmente pago, pago=40, divida=60, remanescente pendente`; total 100€ → `Pago, resto=0`; reembolso 80€ → `Reembolsado, valor_reembolsado=80`. Marcação E2E presencial ref `CD-2026-E57DF6` consistente.
