# Relatório — FASE 5: Auditoria Visual, Testes Administrativos Completos e Segurança Avançada
**Projeto original:** `C:\wamp64\www\meu-projeto\clinica`  
**Data/hora (execução):** 2026-11-09 ~23:51 (local) a 2026-11-10 00:15 (evidência registada).  
**Base de dados original (NÃO usada para escritas de teste):** `clinica_dentaria` — **confirmada intacta** (contagens de marcadores P4/P5/utilizadores/galeria/stock de teste = 0 nas verificações finais).  
**Base de dados de testes (isolada, única BD com escritas):** `clinica_dentaria_testes_phase3`.  
**Cópia de aplicação para testes HTTP:** `C:\Users\HP\AppData\Local\Temp\opencode\clinica-phase3-testapp` (sincronizada do original; única diferença funcional intencional: `includes/core/config.php` aponta para BD de testes, UTF-8 sem BOM). Servidor: PHP 8.4.15 built-in `127.0.0.1:8093` (iniciado/parado conforme necessário).  
**Backup do projeto (Fase 5):** `C:\Users\HP\AppData\Local\Temp\opencode\clinica-backup-phase5-20261109-235116` (`C:\Users\HP\AppData\Local\Temp\opencode\`).  
**Backups/BD de fases anteriores mantidos** (Phase4, auditoria, dump Phase2, etc.).  
**PHP lint (projeto):** `php -l` em todos os `*.php` — **0 erros** (verificado).

## 1. Resumo geral
A Fase 5 avançou a partir do estado validado nas Fases 1–4, sem repetir testes já aprovados desnecessariamente. Foram executados: verificação do mecanismo **modo claro/escuro + persistência** (in-app browser); **fluxo completo de alteração** cliente→admin (aprovar e rejeitar) com atualização de horários/estados/consistência; **cancelamento administrativo** com impacto financeiro (slots libertados, reembolso/estados coerentes); testes administrativos reais em **Definições** (leitura/alteração/guardar/persistir/restaurar com fidelidade de campos), **Relatórios** (carga/filtros + spot-check DB), **Utilizadores** (criar/editar/ativar-desativar; eliminar não suportado no handler observado), **Stock** (criar, movimentos entrada/saída, quantidades inválidas rejeitadas a nível de serviço/UI), **Galeria** (upload inválido rejeitado por validação de tipo/conteúdo, upload PNG válido, ficheiro presente, eliminação remove registo e ficheiro). Segurança avançada (amostra controlada): acesso direto a admin negado, CSRF rejeitado (403) em ação admin sem token, XSS armazenado escapado (confirmado em fases anteriores) e refletido sem payload bruto no cenário testado, evidência de **controlo de tentativas de login** (`tentativas_login`; 7 tentativas falhadas bloquearam efetivamente o login no teste, limpo na BD de testes), validação de uploads na galeria com `finfo`+`getimagesize`, **sem `session_regenerate_id` encontrado** (risco/observação). Gateway/SMTP: abstração preparada, webhook fail-closed com autenticação por segredo + idempotência estrutural; **sem credenciais/transporte real** — bloqueado por dependência externa. **Limitação principal:** browsers Playwright/Chromium não instalados (download falhou/timeout; `ms-playwright` sem builds) — auditoria visual/interativa completa (consola JS em todas as interações, rede/AJAX programática, modais/calendários complexos) **não totalmente executável**; usadas capturas Edge headless (screenshots) + in-app browser (consola/snapshot/avaliação DOM) com evidência parcial. Todos os dados de teste criados foram limpos; **BD original intacta**.

## 2. Ferramentas utilizadas
- **PHP 8.4.15 (CLI/built-in server)**, `php -l` (sintaxe).
- **MySQL 8.4.7** (`mysql.exe` CLI, root local WAMP) — consultas/verificações/limpeza **apenas** em `clinica_dentaria_testes_phase3`; contagens de controlo em `clinica_dentaria`.
- **PowerShell / curl (PHP)** para testes HTTP/CSRF/sessão e uploads multipart (CURLFile).
- **Edge headless** (`C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`, v157.0.4341.42) para screenshots responsivos/páginas.
- **In-app browser tools (OpenCode desktop, Chromium)**: `tabs.open/list/focus`, `snapshot`, `console`, `evaluate` (JS controlado para mecanismo de tema/persistência), `reload`, `wait`.
- **npx** 11.13.0 / Node v26.2.0; **Playwright CLI 1.49.0** disponível mas **browsers Chromium não instalados** (instalação falhou/timeout).

## 3. Browsers utilizados
| Browser/Ferramenta | Uso | Resultado |
|---|---|---|
| In-app Chromium (browser tools) | Console JS (homepage), snapshot DOM, avaliação DOM (tema/localStorage), reload/persistência | **APROVADO COM OBSERVAÇÕES** (mecanismos testados; screenshots integrados do browser tool não visualizáveis pelo modelo; tab não mantida visível para screenshot nativo em alguns casos) |
| Microsoft Edge headless 157.0.4341.42 | Screenshots estáticos de páginas/resoluções | **APROVADO COM OBSERVAÇÕES** (evidência visual estática; sem interação programática completa/consola/rede) |
| Playwright Chromium (browsers) | Automação completa prevista | **NÃO TESTADO / BLOQUEADO POR DEPENDÊNCIA DE AMBIENTE** (browsers não instalados; download falhou/timeout) |

## 4. Páginas testadas (evidência)
Públicas (HTTP 200/redirects esperados, sem `Fatal/Uncaught` nos cenários): `index.php`, `dentistas.php`, `tratamentos.php`, `contactos.php`, fluxo de marcação, `login.php`, `registo.php` (carregamento verificado em fases anteriores; captura login/home), `minhas-marcacoes.php`/área pessoal (fluxos autenticados). Admin: `admin/index.php`, `admin/configuracoes.php`, `admin/relatorios.php`, `admin/utilizadores.php`, `admin/utilizadores-novo.php`, `admin/stock.php`, `admin/galeria.php`, `admin/marcacoes.php`, `admin/pedidos.php` (operações reais conforme secções abaixo). Acesso direto sem autenticação a páginas admin chave → **302 para `/login.php`** (negado).

## 5. Screenshots dos problemas visuais / evidência visual
**Diretório:** `C:\Users\HP\AppData\Local\Temp\opencode\clinica-docs\screenshots-phase5\`

| Ficheiro | Resolução/Página | Observação |
|---|---:|---|
| `home-1366x768.png` | 1366×768, home | Evidência estática (modelo não visualiza pixels; ficheiro gerado) |
| `home-1920x1080.png` | 1920×1080 | Desktop largo |
| `home-1024x768.png` | 1024×768 | Tablet/laptop |
| `home-768x1024.png` | 768×1024 | Tablet portrait |
| `home-mobile-390x844.png` / `home-360x800.png` | Mobile | Mobile |
| `dentistas-1366x768.png`, `tratamentos-1366x768.png`, `contactos-1366x768.png`, `login-1366x768.png`, `admin-login-390x844.png` | Páginas-chave | Evidência estática |

**Problema visual/console confirmado (evidência concreta):** recurso **404** `http://127.0.0.1:8093/uploads/dentistas/825815881b356966aac71d152fedff11.jpg` na homepage (in-app browser console, nível error). Causa: `dentistas.fotografia` do registo **id 2 “ASD”** aponta para esse caminho **tanto na BD de testes como na BD original `clinica_dentaria`**, mas o ficheiro **não existe** em `uploads/dentistas/` de ambas as cópias. Impacto: imagem quebrada/ausente na UI (não fatal PHP). **Ação:** **não alterada** a BD original (sem backup/justificação de alteração de dados reais e registo aparenta ser placeholder). Recomendação: remover/corrigir referência ou repor ficheiro em ambiente controlado com aprovação. **APROVADO COM OBSERVAÇÕES** (funcionalidade não fatal; dado inconsistente).

**Outros problemas visuais (alinhamento/contraste/sobreposição/CSS detalhado):** **NÃO TESTADOS** exaustivamente por inspeção visual humana automatizada (limitação Playwright/visualização de screenshots pelo modelo). Páginas carregaram sem `Fatal/Uncaught` nos cenários testados.

## 6. Resultados dos testes responsivos
| Resolução | Evidência | Resultado | Classificação |
|---:|---|---|---|
| 1920×1080 | `home-1920x1080.png` | Captura gerada, sem fatal HTTP | **APROVADO COM OBSERVAÇÕES** |
| 1366×768 | Várias páginas (home/dentistas/tratamentos/contactos/login) | Capturas geradas | **APROVADO COM OBSERVAÇÕES** |
| 1024×768 | `home-1024x768.png` | Captura gerada | **APROVADO COM OBSERVAÇÕES** |
| 768×1024 | `home-768x1024.png` | Captura gerada | **APROVADO COM OBSERVAÇÕES** |
| 390×844 | `home-mobile-390x844.png`, `admin-login-390x844.png` | Capturas geradas | **APROVADO COM OBSERVAÇÕES** |
| 360×800 | `home-360x800.png` | Captura gerada | **APROVADO COM OBSERVAÇÕES** |

Menu mobile, grelhas, modais, calendários, tabelas admin, scroll horizontal e legibilidade detalhada **NÃO TESTADOS** interativamente (limitação Playwright/browsers).

## 7. Modo claro e modo escuro
| Verificação | Evidência (in-app browser, DOM) | Resultado | Classificação |
|---|---|---|---|
| Alternância funcional | `#botao-tema` clicado via handler; `data-tema` mudou `escuro`→`claro`, botão `☀️ Alternar tema`→`🌙 Alternar tema` | OK | **APROVADO** |
| Persistência | `localStorage.tema` `escuro`→`claro`; após `reload` manteve `claro`/botão correspondente | OK | **APROVADO COM OBSERVAÇÕES** (testado em página home; navegação multi-página não repetida exaustivamente, mas mecanismo é global/localStorage) |
| Contraste/legibilidade visual em ambos os temas, modais, formulários, cartões | Não auditado pixel-a-pixel (sem Playwright/inspeção visual completa) | Parcial | **APROVADO COM OBSERVAÇÕES / NÃO TESTADO** (detalhe visual) |

Estado do browser restaurado para tema `escuro` após teste. Nenhuma alteração de CSS/identidade visual realizada.

## 8. Resultados dos testes administrativos
| Módulo | Operações testadas (reais, BD de testes, CSRF, limpeza) | Resultado | Classificação |
|---|---|---|---|
| **Definições** (`admin/configuracoes.php`) | Carga (200, sem fatal, form). Alteração real `anos_existencia` 0→1, POST com **todos os campos não-sensíveis** preenchidos + passwords vazios (manter), redirect 302, DB persistiu `1`; restaurado `0`. Verificação: submissão com ints vazios é rejeitada pela validação (comportamento correto). BD original `anos_existencia` verificada separadamente (sem escrita). | Guardar/persistir OK quando payload fiel/válido | **APROVADO COM OBSERVAÇÕES** (reflexo público no número observado no 1º teste de forma indireta; fidelidade exige submissão completa válida — por design/risco de UI parcial se cliente omitir campos) |
| **Relatórios** (`admin/relatorios.php`) | Carga default e com filtros (`de/ate`) — 200, sem fatal, conteúdo esperado. Spot-check DB: 18 consultas em 2026 na BD de testes (query coerente com lógica). | OK | **APROVADO COM OBSERVAÇÕES** (cálculos renderizados server-side; comparação exaustiva valor-a-valor em HTML não automatizada por encoding; filtros base/sem-registos extremos não exaustivos) |
| **Utilizadores** | Criar staff receção (`admin/utilizadores-novo.php`, validações/password) → id criado. Desativar/reativar via `admin/utilizadores.php` (com `ativo_enviado`) → `ativo 0→1` em BD. **Eliminar:** nenhuma ação `apagar/eliminar` observada no handler de `utilizadores.php`. Impedir operações sem permissão: acesso direto negado (302 login). | Criar/editar/ativar-desativar OK; eliminar não suportado/observado | **APROVADO COM OBSERVAÇÕES** / **NÃO TESTADO** (eliminar; alteração de permissões para além de papel/admin guards profundos em todos os cenários) |
| **Stock** (`admin/stock.php`) | Criar material (qty 10). Movimento **entrada** +5 → 15; **saída** -3 → 12 (DB). Inválidos (serviço `stock_registar_movimento`): qty 0, negativa, tipo inválido → **rejeitados**. Limpeza. **Edição de produto:** ação de edição não observada no handler lido (`novo_material/movimento/alternar_ativo`) | Movimentos/validações OK; edição de produto não confirmada | **APROVADO COM OBSERVAÇÕES** / **NÃO TESTADO** (editar produto se não existir ação) |
| **Galeria** (`admin/galeria.php`) | Upload **inválido** (conteúdo PHP disfarçado de JPEG) → **rejeitado** (0 registos), sem fatal. Upload **PNG válido** → registo criado, ficheiro existe em `uploads/galeria/` (testapp). **Eliminar** → registo removido e ficheiro apagado. Validação usa `finfo`/`getimagesize` + lista de mimes/extensões permitidas. | OK | **APROVADO** |
| **Marcações/Pagamentos/Pedidos** (Fase 5) | Alteração aprovada/rejeitada (ver secção 9). Cancelamento admin com pagamento → estados/slots/reembolso corretos. | OK nos cenários testados | **APROVADO** |

## 9. Resultados dos fluxos de marcações (completos Fase 5)
**Evidência:** `C:\Users\HP\AppData\Local\Temp\opencode\clinica-docs\phase5_alteration_flow.php` + `phase5_admin_deep.php` (BD de testes, HTTP/CSRF, fixtures limpas).

| Cenário | Evidência | Resultado | Classificação |
|---|---|---|---|
| Pedido de alteração (cliente) | `alterar-consulta.php` (200) → POST `novo_inicio` 2026-12-15 14:00, motivo `P5ALTTEST` → `pedidos_alteracao` **id 2, pendente** | Criado | **APROVADO** |
| Consulta do pedido no admin | `admin/pedidos.php` autenticado (admin) viu pedido/referência pendente | Visível | **APROVADO** |
| Aprovação | POST `aprovar_pedido` (CSRF) → `pedidos_alteracao=aprovado`; consulta **09:00-10:00 → 14:00-15:00**; `horarios_ocupados` **09:00,09:30 → 14:00,14:30**; marcação `confirmada` | Efetivada, horários atualizados, consistente | **APROVADO** |
| Rejeição | Fixture separada: pedido recusado → `pedidos_alteracao=recusado`; consulta/horários **inalterados** (09:00,09:30) | Sem efeitos colaterais | **APROVADO** |
| Atualização horários ocupados / consistência | Comprovada em aprovação/rejeição e no cancelamento admin (slots=0). Relações marcação/consulta/paciente/dentista íntegras nas fixtures. | OK | **APROVADO** |
| Cancelamento administrativo + impacto financeiro | Admin `admin/marcacoes.php` `acao=cancelar` sobre consulta com pagamento `pago` 250€ → `marcacao/consulta=cancelada`, `slots=0`, pagamento `reembolsado` `valor_reembolsado=250.00` | OK | **APROVADO** |
| Histórico da consulta | Campos de estado/horários/pedidos verificados; auditoria/histórico detalhado por tabela de logs não inventariado exaustivamente | Parcial | **APROVADO COM OBSERVAÇÕES** |
| Conflitos reais de concorrência | Não reproduzidos deterministicamente (Fase 4: UNIQUE+23000 e tratamento transacional confirmados) | Limitação | **APROVADO COM OBSERVAÇÕES** |

## 10. Resultados da auditoria de segurança (avançada, amostra controlada)
| Área | Evidência | Resultado | Classificação |
|---|---|---|---|
| SQL Injection (indicativo) | Payload em slot rejeitado; PDO/prepared statements nos fluxos revistos (fases anteriores) | Sem evidência de exploração no teste | **APROVADO COM OBSERVAÇÕES** (não exaustivo) |
| XSS armazenado | Contacto com `<script>` renderizado escapado no admin (Fase 4, limpo) | Mitigado no cenário | **APROVADO COM OBSERVAÇÕES** |
| XSS refletido | `admin/utilizadores.php?q=<script>alert("P5RX")</script>` (autenticado): **payload bruto não presente** na resposta (200, sem fatal) | Sem reflexão bruta observada | **APROVADO COM OBSERVAÇÕES** |
| CSRF | POST sem `csrf` em fluxo público → 403 (Fase 4). **POST admin `configuracoes` sem token → 403** (Fase 5). `csrf_validar`/campos presentes nos formulários revistos. | Proteção ativa (amostra) | **APROVADO** (amostra) |
| IDOR / acesso indevido | Confirmação/alteração de consulta de outro utilizador negadas (Fase 4). Admin protegido por `exigir_login`/RBAC. | OK (amostra) | **APROVADO** (amostra) |
| Acesso direto a páginas admin (sem auth) | `index/utilizadores/configuracoes/relatorios/stock/galeria` → **302 `/login.php`** | Negado | **APROVADO** (amostra) |
| Gestão de sessões / fixação de sessão | **Atualização (Fase 6):** `session_regenerate_id(true)` **está implementado** no login bem-sucedido (`login.php`), com rotação de CSRF; cookies com `HttpOnly`, `SameSite=Lax`, `secure` condicional em HTTPS, `strict_mode`. Persistia observação de hardening adicional (flags em todos os contextos, revisão exaustiva). | Mitigação base presente | **APROVADO COM OBSERVAÇÕES** |
| Rate limiting / tentativas de login | Tabela `tentativas_login` existe (test DB). 7 tentativas com password errada produziram mudança de comportamento e o login correto passou a falhar com indicação de bloqueio/tentativas; **após limpeza dos registos de teste em `clinica_dentaria_testes_phase3`**, login voltou a funcionar. `clinica_dentaria` sem registos do teste. | Mecanismo presente/evidenciado | **APROVADO COM OBSERVAÇÕES** (cobertura exaustiva de thresholds/config/IP/UX não testada) |
| Uploads maliciosos / validação | Galeria: ficheiro com conteúdo PHP e nome/tipo JPEG **rejeitado** (validação `finfo` + `getimagesize` + extensões permitidas; `resultado` exige consentimento). Parceiros/upload CV validados em fases anteriores. Diretório `uploads/identidade` existe. | OK (amostra) | **APROVADO COM OBSERVAÇÕES** |
| Exposição de ficheiros privados / mensagens de erro | `display_errors` não ativado nos fluxos normais observados; erros controlados. Acesso direto a admin negado. Auditoria exaustiva de paths/`uploads`/config não concluída. | Parcial | **APROVADO COM OBSERVAÇÕES** |
| Cabeçalhos HTTP de segurança (local 8093) | Presentes: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), microphone=(), camera()`. **Ausentes observados:** `Content-Security-Policy`, `Strict-Transport-Security` (HSTS relevante em HTTPS/prod). | Parcial | **APROVADO COM OBSERVAÇÕES** |
| Proteção de credenciais / dados sensíveis no frontend | Sem credenciais expostas nos relatórios; passwords mascarados em definições (manter vazio). Revisão exaustiva de frontend/JS não concluída. | Parcial | **APROVADO COM OBSERVAÇÕES** |

**Testes não realizados (segurança):** DAST/pentest exaustivo, fuzzing, verificação completa de CSP, testes de sessão em todos os fluxos, abuso/DoS, verificação de todos os endpoints para IDOR/CSRF.

## 11. Gateway de pagamentos e SMTP
| Componente | Estado | Evidência | Necessário para teste real | Classificação |
|---|---|---|---|---|
| Gateway online (abstração) | **Parcialmente implementado** | `includes/servicos/gateway.php`: `GatewayPagamento`, `GatewayNaoConfigurado` (fail-closed, mensagem clara), `GatewayEupagoScaffold` (sem cobrança real sem API key; `iniciarPagamento` retorna erro controlado), `gateway_obter` por config. Configs: `gateway_pagamentos`, `gateway_ambiente`, `gateway_webhook_segredo`, `eupago_api_key`. | Implementação API do fornecedor + credenciais **sandbox** (Eupago/Ifthenpay) | **BLOQUEADO POR DEPENDÊNCIA EXTERNA** |
| Webhook (`pagamento-webhook.php`) | **Implementado com controlos base** | **POST obrigatório** (405 caso contrário), validação via gateway, **fail-closed** se inválido/sem segredo, autenticação por `hash_equals` (token/header vs `gateway_webhook_segredo`), exige `idempotency_key` (Eupago: `identificador/referencia/transaction_id`), confirmação delegada a `gateway_confirmar_pagamento` (idempotência de processamento). **Validação exata depende do contrato/produto Eupago** (scaffold). | Segredo de webhook real/sandbox, payloads reais do fornecedor, testes de replay/assinatura conforme contrato | **APROVADO COM OBSERVAÇÕES** (estrutura) / **BLOQUEADO POR DEPENDÊNCIA EXTERNA** (validação end-to-end real) |
| SMTP / envio de emails | **Configuração armazenada; transporte automático não confirmado como integrado** | Campos em `configuracoes` (`smtp_ativo/host/porta/segurança/utilizador/smtp_password`, remetente). `rg` por `mail(`, PHPMailer, transporte em `includes/servicos/notificacoes.php` não retornou uso direto (verificar implementação completa de notificações/transporte). UI indica “requer transporte/integração configurado e testado”. | Servidor/credenciais SMTP de teste, integração de transporte (PHPMailer/similar) se aplicável, testes de entrega/erros | **BLOQUEADO POR DEPENDÊNCIA EXTERNA** / **APROVADO COM OBSERVAÇÕES** (config presente) |

**Nenhuma credencial real inventada ou utilizada.**

## 12. Problemas encontrados
| # | Problema | Impacto | Evidência | Classificação |
|---|---|---|---|---|
| P5-01 | 404 imagem dentista: `uploads/dentistas/825815881b356966aac71d152fedff11.jpg` (dentista 2 “ASD”) | Imagem quebrada na UI; ruído na consola | Console in-app + DB/teste/original: referência existe, ficheiro ausente em ambos | **APROVADO COM OBSERVAÇÕES** (dado; correção de BD original requer aprovação/backup) |
| P5-02 | Sem `session_regenerate_id` no login/fluxos (pesquisa no código) | Risco de session fixation (dependente do modelo de sessão) | `rg session_regenerate` sem resultados | **APROVADO COM OBSERVAÇÕES** (risco a mitigar) |
| P5-03 | CSP/HSTS ausentes nas respostas locais observadas | Hardening incompleto (CSP mitiga XSS; HSTS em HTTPS) | Amostra de headers HTTP local | **APROVADO COM OBSERVAÇÕES** |
| P5-04 | Módulos admin sem operações completas observadas | “Eliminar utilizador” e “editar produto stock” não confirmados no handler/UI lido | Código `utilizadores.php`/`stock.php` | **NÃO TESTADO** (se ausente, lacuna funcional; não classificado como bug sem confirmação de requisito) |
| P5-05 | Validação visual/interativa completa limitada | Possíveis issues CSS/JS/interação não detetados | Playwright browsers não instalados | **NÃO TESTADO** (parcial) |
| P5-06 | Integrações externas não testáveis end-to-end | Gateway/SMTP não validados com serviços reais | Sem credenciais/contrato | **BLOQUEADO POR DEPENDÊNCIA EXTERNA** |

## 13. Correções realizadas (Fase 5)
- **Nenhuma correção de código do projeto original** foi aplicada nesta fase (alterações funcionais anteriores validadas; problemas encontrados são de dados/ambiente/hardening com risco ou dependência externa). 
- **Infra de teste (cópia):** criado `_p5_svctest.php` temporário em `clinica-phase3-testapp` para teste de serviço e **removido**; sincronização/config BOM tratada em Fase 4. Registos de `tentativas_login` do teste admin **removidos da BD de testes** para permitir continuidade; **BD original não alterada**.

## 14. Ficheiros alterados
| Ficheiro | Alteração |
|---|---|
| `C:\wamp64\www\meu-projeto\clinica\docs\RELATORIO-AUDITORIA-FASE-5.md` | **Criado** (este relatório). |

*Nenhum ficheiro do código-fonte do projeto original foi modificado na Fase 5.*

## 15. Testes aprovados
- Tema claro/escuro: alternância e persistência (`localStorage.tema`/`data-tema`) — mecanismo.
- Alteração completa: pedido cliente → admin aprova (consulta/horários/estados atualizados) e rejeita (sem efeitos).
- Cancelamento admin com pagamento: estados `cancelada`/reembolso, `valor_reembolsado`, slots libertados.
- Definições: carga, alteração+persistência com payload fiel, restauro.
- Relatórios: carga/filtros sem fatal (spot-check DB).
- Utilizadores: criar, desativar, reativar (limpeza).
- Stock: criar, entrada/saída com consistência DB, rejeição de qty 0/negativa/tipo inválido (serviço).
- Galeria: rejeição upload inválido, upload PNG válido + ficheiro, eliminação BD+ficheiro.
- Segurança (amostra): acesso direto admin negado, CSRF 403 sem token (admin config), XSS refletido sem payload bruto, XSS armazenado escapado (Fase 4), validação upload galeria.

## 16. Testes reprovados
- **Nenhum** reprovado com evidência de falha funcional nos cenários executados.

## 17. Testes não realizados / parcialmente realizados
- Playwright completo (Chromium): consola/rede/interações programáticas/modais/calendários/JS em todas as páginas — browsers não instalados.
- Inspeção visual humana detalhada de todos os screenshots/contrastes/sobreposições/scroll horizontal em todas as resoluções.
- Utilizadores: eliminar (se suportado noutro local), gestão exaustiva de permissões/RBAC por matriz.
- Stock: edição de produto (ação não observada), cenários completos de encomendas/categorias/movimentos UI.
- Galeria: edições/consentimento em todos os casos, acesso direto a uploads e hardening webserver.
- Relatórios: validação numérica exaustiva UI↔DB para todos os cards/filtros/exportações.
- Concorrência real de marcação (duas submissões simultâneas).
- Segurança exaustiva: DAST/fuzzing, CSP efetiva, fixação/regeneração de sessão em todos os logins, todos os endpoints IDOR/CSRF, rate-limit completo.
- Gateway sandbox + webhook real assinado; SMTP entrega real.

## 18. Dependências externas em falta
- **Playwright Chromium browsers** instalados (`npx playwright install chromium` concluído com sucesso num ambiente com rede/cache adequado).
- **Credenciais sandbox + contrato/config** de gateway (Eupago/Ifthenpay), segredo de webhook de teste, especificação de assinatura/payload.
- **Configuração/credenciais SMTP de teste** e transporte de envio integrado/validado.

## 19. Riscos que continuam por resolver
- **P5-01 (atualizado Fase 6)**: referência de imagem dentista quebrada (dado em BD original+teste). **Tratamento no código corrigido** (`avatar_dentista` passa a usar fallback quando o ficheiro local não existe), eliminando o 404 no render; a referência órfã em `clinica_dentaria` permanece (não alterada sem autorização).
- **P5-02 (atualizado Fase 6)**: `session_regenerate_id(true)` confirmado em `login.php`; manter revisão de hardening de sessão/cookies em produção (HTTPS).
- **P5-03**: falta de CSP (e HSTS em produção HTTPS).
- Cobertura incompleta de testes visuais/interativos por limitação de browser automation.
- Webhook de gateway: validação específica do fornecedor pendente; nunca aceitar confirmações não autenticadas.
- Alterações em massa em Definições exigem submissão fiel de todos os campos válidos (risco operacional se UI/cliente omitir campos).

## 20. Recomendações para a próxima fase
1. Instalar browsers Playwright e executar suite E2E/visual (consola, rede, interações, mobile, temas) com evidência capturada.
2. Corrigir **P5-01** com procedimento controlado (backup, decisão sobre registo “ASD”, repor ficheiro ou limpar referência).
3. Implementar `session_regenerate_id(true)` no login bem-sucedido e rever flags de cookie de sessão (`HttpOnly`, `Secure` em HTTPS, `SameSite`).
4. Definir/implementar **CSP** compatível e HSTS em HTTPS; rever headers no servidor de produção.
5. Completar validação gateway em **sandbox** (Eupago) com webhook idempotente/assinado conforme contrato; adicionar testes automatizados de replay.
6. Integrar e testar **SMTP** em ambiente de staging com caixa de teste; validar erros/retries.
7. Fechar lacunas admin: confirmar/implementar eliminar utilizador e editar produto stock se requisitos; testes de permissões por papel.
8. Teste de concorrência controlado para reservas/slots.

---
**Classificação global da Fase 5:** **APROVADO COM OBSERVAÇÕES** (fluxos críticos de alteração/cancelamento financeiro/admin real e controlos de segurança amostrados aprovados; auditoria visual/interativa completa e integrações externas limitadas por ambiente/dependências). **Base de dados original `clinica_dentaria` preservada intacta.**