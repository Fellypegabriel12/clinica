# Relatório Final — Preparação para Produção (Fase 6)
**Projeto:** `C:\wamp64\www\meu-projeto\clinica` (PHP 8.4.15, MySQL 8.4.7, PDO, WAMP/Windows)  
**Data/hora:** 2026-11-10 (execução Fase 6)  
**Base de dados original:** `clinica_dentaria` — **não utilizada para escritas de teste**; preservada intacta nas verificações desta fase (contadores de marcadores P6/teste = 0).  
**Base de dados de testes (isolada):** `clinica_dentaria_testes_phase3` (todas as escritas/fixtures de teste, com limpeza).  
**Cópia de testes:** `C:\Users\HP\AppData\Local\Temp\opencode\clinica-phase3-testapp` (sincronizada; `includes/core/config.php` aponta para BD de testes).  
**Backups:** `C:\Users\HP\AppData\Local\Temp\opencode\clinica-backup-phase6-20261110-085016` (antes das correções Fase 6), além dos backups das fases anteriores (`clinica-backup-auditoria-20261109-222027`, `clinica-backup-phase4-20261109-232758`, `clinica-backup-phase5-20261109-235116`, dump `clinica_dentaria-backup-20261109-phase2.sql`).  
**PHP lint:** `php -l` em todos os `*.php` do projeto — **0 erros**.

**Relatórios consultados/aproveitados:** `docs/RELATORIO-AUDITORIA-CORRECOES.md`, `docs/RELATORIO-TESTES-FUNCIONAIS.md`, `docs/RELATORIO-TESTES-COMPLETOS-FASE-3.md`, `docs/RELATORIO-TESTES-AVANCADOS-FASE-4.md`, `docs/RELATORIO-AUDITORIA-FASE-5.md` (atualizado na Fase 6 para corrigir imprecisões e registar correção de tratamento do P5-01/P5-02).

## 1. Resumo geral
O sistema apresenta uma base funcional e de segurança consistente em áreas críticas (marcação/slots, estados financeiros, CSRF, RBAC base, revalidação de utilizador ativo, sessões com regeneração no login, webhook de pagamentos fail-closed com idempotência estrutural), validada por evidências nas Fases 1–6. Na Fase 6 foram corrigidos/mitigados: **(1)** tratamento de fotografias de dentista inexistentes (fallback a iniciais, eliminando 404 de renderização), **(2)** confirmação e documentação da **regeneração de ID de sessão no login** (`session_regenerate_id(true)` em `login.php`) e hardening existente, **(3)** adição de **Content-Security-Policy compatível** e **HSTS condicional em HTTPS** nos cabeçalhos. Foram realizados testes adicionais de integridade/pagamentos (idempotência e segurança de confirmação gateway), segurança/RBAC/smoke admin e verificação de cabeçalhos. **O projeto não é declarado 100% pronto para produção**: dependem de configuração/credenciais externas os pagamentos online reais (Eupago/contrato/sandbox/webhook conforme fornecedor) e o envio real de emails (transporte SMTP não integrado); a auditoria visual/interativa completa com Playwright browsers permaneceu limitada pelo ambiente; e subsistem riscos/observações (referência órfã de imagem na BD original, CSP a afinar em produção se necessário, revisão exaustiva de sessões/cookies em HTTPS real, cobertura exaustiva de todos os módulos/casos).

## 2. Problemas encontrados (Fase 6 e remanescentes)
| # | Problema | Origem/impacto | Estado |
|---|---|---|---|
| F6-P1 | Fotografia dentista referenciada em BD mas ficheiro ausente: `uploads/dentistas/825815881b356966aac71d152fedff11.jpg` (dentista id 2 “ASD”) em `clinica_dentaria` e teste; 2ª referência verificada existe. | BD/recurso órfão; imagem quebrada/404 na consola. | **Corrigido no código** (fallback); referência em BD original **não alterada**. |
| F6-P2 | Dúvida herdada (Fase 5) sobre ausência de `session_regenerate_id`. | Auditoria anterior não localizou; fixação de sessão. | **Não aplicável como bug**: confirmado em `login.php` (regeneração + rotação CSRF). Hardening adicional recomendado. |
| F6-P3 | Ausência de CSP (e HSTS em HTTPS). | Hardening HTTP incompleto. | **Corrigido** (CSP compatível; HSTS apenas se HTTPS). |
| R1 | Sem transporte SMTP real (`mail()`/PHPMailer ausentes); notificações internas em BD, recuperação mostra link em `MODO_DEV`. | Emails reais (confirmação, recuperação, admin, pagamentos, cancelamentos, alterações, contacto) dependem de integração/config. | **BLOQUEADO POR DEPENDÊNCIA EXTERNA / incompleto**. |
| R2 | Pagamento online/gateway Eupago: scaffold sem cobrança real sem credenciais; validação exata de webhook depende de contrato/produto. | Cobrança/confirmação real indisponível sem fornecedor. | **BLOQUEADO POR DEPENDÊNCIA EXTERNA / parcial**. |
| R3 | Playwright Chromium browsers não instalados (timeout). | Auditoria visual/interativa completa limitada. | **NÃO TESTADO** (completo). |
| R4 | Lacunas/observações admin: eliminar utilizador e editar produto stock não observados no handler; permissões/RBAC exaustivas por matriz não concluídas. | Cobertura/funcionalidade a confirmar. | **APROVADO COM OBSERVAÇÕES / NÃO TESTADO**. |
| R5 | Revisão exaustiva de CSP em produção, cookies `Secure` sob HTTPS real, todos os endpoints IDOR/CSRF, DAST, concorrência real. | Cobertura de segurança/performance. | **NÃO TESTADO / observação**. |

## 3. Problemas corrigidos
| # | Correção | Ficheiro(s) | Validação |
|---|---|---|---|
| F6-C1 | `avatar_dentista`: para caminhos locais, só renderiza `<img>` se o ficheiro existir dentro da raiz do projeto; caso contrário fallback para iniciais (evita 404/imagem quebrada). | `includes/core/config.php` | `php -l` OK. In-app browser: **nenhum** erro de consola para `825815881b356966...jpg` (antes 404); homepage 200. |
| F6-C2 | Cabeçalhos: **Content-Security-Policy** compatível (`self`, inline scripts/styles, Google Fonts, embeds Google Maps, imagens https/data/blob, `frame-ancestors 'self'`) e **HSTS** (`max-age=31536000; includeSubDomains`) **apenas quando HTTPS** (`$_SERVER['HTTPS']` ativo). | `includes/core/config.php` | Resposta HTTP inclui `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`; páginas públicas/admin carregam sem fatal/CSP break observado nos smokes. |
| F6-C3 | Correção documental: sessão regenera ID no login (`session_regenerate_id(true)`, rotação CSRF) e estado do P5-01 atualizado. | `docs/RELATORIO-AUDITORIA-FASE-5.md` | Revisão de código (`login.php`, `logout.php`, `core/config.php`). |

## 4. Ficheiros alterados (Fase 6)
| Ficheiro | Alteração |
|---|---|
| `includes/core/config.php` | Fallback de avatar para ficheiro local inexistente; adição de CSP e HSTS condicional em HTTPS. |
| `docs/RELATORIO-AUDITORIA-FASE-5.md` | Atualização de achados (sessão/P5-02, P5-01). |
| `docs/RELATORIO-FINAL-PREPARACAO-PRODUCAO.md` | **Criado**. |

*(Cópia de testes `clinica-phase3-testapp` sincronizada com as correções de código para validação; scripts temporários em temp criados/removidos.)*

## 5. Testes executados
- **Fases anteriores (aproveitados):** inventário/documentação, `php -l` global, correções financeiras/webhook/marcação (F1–F4), booking E2E presencial, financeiro (total/parcial/reembolso/cancelamento), RBAC/IDOR/CSRF/XSS amostras, CRUD admin em vários módulos, fluxo de alteração aprovar/rejeitar (F5), screenshots Edge, tema via browser tools — com evidência nos relatórios anteriores.
- **Fase 6 (executados):** `php -l` (0 erros); verificação de referências de imagens dentistas em BD/disco (original e teste); validação do fallback de avatar e ausência do 404 específico (in-app browser console, BD teste com referência órfã herdada); verificação de headers HTTP (CSP/XFO/XCTO/RP/PP); smoke público (index, dentistas, tratamentos, contactos, login, registo, marcar-pacientes) e **smoke admin** (19 páginas: index, marcações, pedidos, pagamentos, financeiro, dentistas, tratamentos, especialidades, horários, bloqueios, artigos, testemunhos, parceiros, contactos, stock, galeria, relatórios, configurações, utilizadores) — 200 sem fatal; **RBAC** cliente→admin = 403; **SQLi indicativo** no login (payload) rejeitado, sem login/fatal/erro SQL; **integridade auth** utilizador desativado não autentica; **gateway** `gateway_confirmar_pagamento` (serviço, BD teste): confirmação `pago` atualiza pagamento/marcação/consulta e liberta expiração de slot, **idempotência** no replay (deduplicado), confirmação em marcação **cancelada** não promove a `pago` (vai para `em_processamento`), evento sem `idempotency_key` rejeitado; limpeza de fixtures e de tentativas de login de teste.
- **Revisão de código:** sessão/login/logout/cookies/headers, `exigir_login` (revalidação ativo/papel), `avatar_dentista`, gateway/webhook (`pagamento-webhook.php`, `includes/servicos/gateway.php`), pagamentos (`includes/servicos/pagamentos.php`), recuperação/reposição de password, notificações internas, configurações SMTP/email.

## 6. Evidências dos testes
- Console in-app browser: sem erro 404 para `825815881b356966...jpg` após correção; screenshot/headers via `Invoke-WebRequest` (CSP presente). 
- Scripts/evidências em `C:\Users\HP\AppData\Local\Temp\opencode\clinica-docs\` (fase 5/6) e `screenshots-phase5/` (Edge headless, múltiplas resoluções/páginas).
- Resultados estruturados: `phase6_security_admin.php` (RBAC 403, sqli login rejeitado, smokes 200/ok, headers), teste serviço gateway (idempotente/deduplicado, `em_processamento` em cancelada, rejeição sem idempotência), login de utilizador desativado falhado.
- `php -l` recursivo: 0 erros. Contagens de marcadores/originais: 0.

## 7. Resultados dos testes (síntese)
| Área | Resultado | Classificação |
|---|---|---|
| Lint/sintaxe PHP | 0 erros | **APROVADO** |
| Sessões/autenticação base | `session_regenerate_id(true)` no login, rotação CSRF, cookies `HttpOnly`/`SameSite=Lax`/`secure` condicional, strict mode; logout destrói sessão/cookie; utilizador desativado não entra; `exigir_login` revalida estado/papel | **APROVADO COM OBSERVAÇÕES** |
| Cabeçalhos de segurança | XFO, XCTO, RP, Permissions-Policy, **CSP** adicionada (compatível), **HSTS** condicional HTTPS | **APROVADO COM OBSERVAÇÕES** |
| Imagens dentista (404) | Fallback a iniciais quando ficheiro local inexistente; referência órfã em BD original não alterada | **APROVADO COM OBSERVAÇÕES** |
| Marcações/alterações/cancelamentos | E2E e cenários avançados aprovados em fases anteriores; alteração aprovar/rejeitar e cancelamento admin com impacto financeiro (F5/F6) | **APROVADO** |
| Pagamentos/financeiro (lógica) | Estados/cálculos, parcial/reembolso/cancelamento, coerência validadas; gateway confirmar idempotente e seguro no cenário testado | **APROVADO COM OBSERVAÇÕES** |
| Webhook/gateway (código) | POST-only, fail-closed, `hash_equals`, `idempotency_key` exigida, dedupe; Eupago scaffold/validação exata dependente de contrato | **APROVADO COM OBSERVAÇÕES** |
| Pagamento online real (Eupago) | Sem credenciais/contrato/API real; sem cobranças | **BLOQUEADO POR DEPENDÊNCIA EXTERNA** |
| Emails/SMTP | Config presente; **sem transporte** (`mail`/PHPMailer ausentes); recuperação expõe link em `MODO_DEV`; notificações internas em BD | **BLOQUEADO POR DEPENDÊNCIA EXTERNA** / parcial |
| Admin (smoke + operações amostradas) | 19 páginas carregam; CRUD/ações validadas em múltiplos módulos (F3–F6) com limpeza; lacunas/edições/eliminações não observadas em alguns módulos | **APROVADO COM OBSERVAÇÕES** |
| RBAC/controlo de acesso | Cliente 403 em admin; direct-access negado; IDOR amostras negadas | **APROVADO COM OBSERVAÇÕES** |
| Segurança (amostra controlada) | CSRF protegido, SQLi indicativo rejeitado, XSS armazenado escapado/refletido sem payload bruto nos testes, uploads validados (galeria/parceiros), tentativas de login bloqueadas (evidência) | **APROVADO COM OBSERVAÇÕES** |
| Integridade de dados | Slots/libertação, estados financeiros/marcações, utilizador desativado, confirmação gateway idempotente, BD original intacta | **APROVADO** |
| Visual/responsivo/modos/JS completo | Screenshots estáticas e tema (mecanismo) OK; interação completa/Playwright browsers indisponível | **APROVADO COM OBSERVAÇÕES** / **NÃO TESTADO** |

## 8. Problemas ainda existentes
- Referência órfã de fotografia do dentista id 2 na **BD original** (ficheiro ausente) — render tratado, dado não corrigido sem autorização.
- Possível falta de ações “eliminar utilizador” / “editar produto stock” no UI/handler (a confirmar face a requisitos).
- Cobertura incompleta: todas as operações de todos os módulos, todos os perfis, todos os filtros/relatórios, concorrência real, DAST exaustivo, CSP afinada a produção, HTTPS/HSTS/cookies `Secure` em ambiente real.
- Validação visual humana/automação completa pendente.

## 9. Funcionalidades não testadas
- Playwright E2E completo (consola/rede/interações/modais/calendário JS) em todas as páginas/resoluções.
- Gateway **sandbox** real (Eupago/Ifthenpay) com webhooks assinados/payloads reais e replays do fornecedor.
- Envio/entrega real de emails (confirmação marcação, recuperação, notificações admin/pagamentos/cancelamentos/alterações, contacto) via SMTP.
- Matriz exaustiva de permissões por perfil em todas as ações; testes de escalada de privilégios completos.
- Concorrência real de marcação; performance/carga.

## 10. Dependências externas necessárias
- **Browsers Playwright** (Chromium) para automação visual/E2E completa.
- **Gateway de pagamentos:** contrato/credenciais **sandbox** (e depois produção) do fornecedor (ex.: Eupago), parâmetros/assinatura de webhook conforme produto, URL de callback acessível.
- **SMTP:** servidor/conta (host, porta, TLS/SSL, utilizador, palavra-passe), domínio/remetente válido (SPF/DKIM/DMARC recomendado).
- **HTTPS em produção** (certificado) para `Secure` cookies/HSTS efetivos e segurança de tráfego.

## 11. Configurações necessárias para produção
- Definir credenciais reais em ambiente seguro (não em código): BD de produção, `gateway_pagamentos`, `gateway_ambiente` adequado, `eupago_api_key`/credenciais fornecedor, `gateway_webhook_segredo` forte, credenciais SMTP, remetentes/destinos (`contacto_*`, `email_recepcao_*`, `email_notificacoes_*`).
- Garantir `MODO_DEV` desativado (remover `includes/.modo_dev`), `display_errors` off, logs protegidos.
- Ativar HTTPS; confirmar cookies `Secure` e HSTS; rever/ajustar **CSP** conforme recursos reais.
- Rever dados de identidade/legais/ERS/OMD, conteúdos, utilizadores de teste e **referência de imagem órfã** (dentista “ASD”) com procedimento autorizado.
- Políticas de backup/restore BD e ficheiros `uploads/`; permissões de filesystem restritivas.
- Testar fluxos críticos em staging com sandbox gateway e SMTP de teste antes de produção.

## 12. Riscos de segurança
- **Sessão:** regeneração no login presente; recomenda-se rever regeneração em mudanças de privilégio, timeouts, invalidação server-side e `Secure` em HTTPS. (Mitigação parcial.)
- **CSP/HSTS:** CSP adicionada mas pode requerer ajustes; HSTS só efetivo em HTTPS.
- **Webhooks:** estrutura segura, mas validação específica do fornecedor deve ser confirmada com payloads/assinaturas reais.
- **Uploads/exposição:** validações presentes em amostras; manter revisão de armazenamento/acesso direto e tipos permitidos.
- **Dados pessoais/RGPD:** tratar consentimentos, retenção, acessos e logs conforme política legal (fora do âmbito técnico automatizado).
- Cobertura de testes de segurança não exaustiva.

## 13. Estado dos pagamentos
- **Implementado/funcional (interno):** `estado_financeiro_marcacao` (corrigido), pagamentos presenciais detalhados (total/parcial, dívida/resto), reembolsos total/parcial com limites, cancelamento com impacto financeiro, registo/auditoria e notificações internas, estados coerentes.
- **Gateway/webhook (código):** abstração `GatewayPagamento`, `GatewayNaoConfigurado`, `GatewayEupagoScaffold`; `pagamento-webhook.php` POST-only, validação autenticada (`hash_equals`/segredo), **idempotency_key obrigatória**, `gateway_confirmar_pagamento` com dedupe/`FOR UPDATE`, transições seguras (não confirma pago em marcação cancelada), atualização de marcação/consulta/slots/notificação.
- **Funciona agora:** fluxos presenciais e lógica financeira/integração de estados; confirmação gateway simulada em serviço com idempotência.
- **Falta/depende:** integração API real Eupago (MB WAY/Multibanco/cartão conforme contrato), mapeamento exato de assinatura/payload/estados do fornecedor, credenciais **sandbox** e produção, testes de replay/timeout/falha com fornecedor, UI/fluxo de retorno completo conforme método, reconciliação operacional.
- **Necessário:** configurar `gateway_pagamentos=eupago`, `gateway_ambiente`, `eupago_api_key`, `gateway_webhook_segredo`; implementar/validar chamadas API e parsing específico; expor webhook HTTPS acessível. **Sem cobranças reais nesta fase.**

## 14. Estado dos emails
- **Implementado:** configurações SMTP/remetentes/destinos em `configuracoes`; notificações **internas** em BD (`notificacoes`) para eventos (pagamento, marcação, reposição de password concluída, etc.); recuperação de password gera token seguro (hash em BD, expiração/uso único) e em `MODO_DEV` mostra link; contactos registados.
- **Não implementado (transporte):** envio real por SMTP/`mail()`/PHPMailer não encontrado; emails automáticos (confirmação marcação, recuperação com link por email, notificações admin/pagamentos/cancelamentos/alterações) **não entregues externamente** sem integração.
- **Riscos/pendências:** tratamento de falhas/retries/duplicação no envio externo, validação de destinatários/headers, logs sem dados sensíveis — a implementar com transporte.

## 15. Estado da interface
- Páginas públicas/admin carregam sem erros fatais nos smokes; marcação, áreas autenticadas e módulos admin principais funcionais nos testes. Tema claro/escuro com persistência (mecanismo) OK. Responsivo: capturas em várias resoluções sem evidência de fatal; **validação visual/interativa exaustiva NÃO TESTADA** (limitação Playwright). Avatar de dentista trata imagem ausente (Fase 6). Problemas visuais subtis podem existir.

## 16. Estado da base de dados
- Esquema utilizado: 44 tabelas (testes). Integridade relacional observada nos cenários testados (marcações/consultas/pacientes/dentistas/tratamentos/horários/pagamentos). Constraints relevantes presentes (ex.: UNIQUE em `horarios_ocupados` por dentista/bloco). **`clinica_dentaria` intacta**; referência órfã de fotografia (dado) persiste na original. Dados de teste limpos em `clinica_dentaria_testes_phase3`.

## 17. Recomendações finais
1. **Gateway:** integrar e validar Eupago em sandbox com credenciais reais de teste, confirmar assinatura/payload/idempotência/retries do fornecedor, testar retorno e reconciliação.
2. **Email:** integrar transporte SMTP (ex.: PHPMailer ou equivalente compatível), enviar recuperação/confirmações/notificações com tratamento de falhas e sem duplicação, testar em staging.
3. **Dados:** corrigir/remover referência de fotografia órfã do dentista “ASD” na BD original com backup e aprovação.
4. **Segurança:** instalar Playwright e completar E2E/visual; rever CSP em produção, `Secure` cookies e HSTS sob HTTPS, regeneração de sessão em eventos sensíveis, auditoria DAST/IDOR/CSRF exaustiva.
5. **Admin/funcional:** confirmar requisitos de eliminar utilizador/editar stock; completar testes por perfil e relatórios/exportações.
6. **Go-live:** apenas após staging com HTTPS, backups, credenciais externas validadas e checklist operacional.

## 18. Classificação por área
| Área | Classificação | Nota |
|---|---|---|
| Estrutura/código/lint | **APROVADO** | `php -l` 0 erros, compatibilidade mantida |
| Documentação técnica (fases anteriores) | **APROVADO** | Relatórios completos e atualizados |
| Autenticação e sessões | **APROVADO COM OBSERVAÇÕES** | `session_regenerate_id`, CSRF, lockout/tentativas, revalidação ativo; hardening HTTPS/timeouts a rever |
| Autorização/RBAC e acessos diretos | **APROVADO COM OBSERVAÇÕES** | Negação/403 e amostras IDOR ok; matriz exaustiva pendente |
| Marcações (fluxos principais e avançados) | **APROVADO** | E2E, conflitos/validações, alteração aprovar/rejeitar, cancelamentos |
| Pagamentos/financeiro (interno) | **APROVADO** | Cálculos, parcial/reembolso/cancelamento, consistência |
| Gateway integração código/webhook | **APROVADO COM OBSERVAÇÕES** | Idempotente/fail-closed; validação específica fornecedor pendente |
| Pagamentos online reais (Eupago) | **BLOQUEADO POR DEPENDÊNCIA EXTERNA** | Credenciais/contrato/API sandbox-produção |
| Emails/SMTP (transporte e entrega) | **BLOQUEADO POR DEPENDÊNCIA EXTERNA** | Sem integração de envio; config presente |
| Painel administrativo (funcional) | **APROVADO COM OBSERVAÇÕES** | Smoke/CRUDs amostrados; lacunas e cobertura exaustiva pendentes |
| Interface pública/admin (visual) | **APROVADO COM OBSERVAÇÕES** | Sem fatal, tema ok, responsivo parcial; Playwright completo indisponível |
| Segurança aplicacional (amostra) | **APROVADO COM OBSERVAÇÕES** | CSRF/XSS/SQLi/IDOR/uploads/headers melhorados; DAST/CSP fina pendente |
| Integridade e consistência de dados | **APROVADO** | Estados/slots/financeiro/gateway dedupe; BD original intacta |
| Imagens/dados de conteúdo (dentista) | **APROVADO COM OBSERVAÇÕES** | Render resiliente; referência órfã na BD original |
| Testes visuais automatizados completos | **NÃO TESTADO** | Browsers Playwright não instalados |
| Concorrência real / DAST exaustivo | **NÃO TESTADO** | Limitação de ambiente/escopo |

## 19. Classificação geral do projeto
**Classificação geral: APROVADO COM OBSERVAÇÕES** (não pronto para declaração de “100% produção” sem fechar dependências e validações críticas externas).

**Distinguição objetiva:**
- **Efetivamente pronto (com evidência):** núcleo de marcações/alterações/cancelamentos e integridade de slots, lógica financeira interna (incl. correção C-F3-01), autenticação/sessão base segura, RBAC/acesso direto e proteções amostradas (CSRF, validações, XSS/SQLi controlados), tratamento resiliente de avatar ausente, headers de segurança base + CSP compatível, idempotência/segurança estrutural de confirmação de gateway, integridade de dados e preservação da BD original nos testes.
- **Implementado mas precisa de credenciais/configuração:** gateway Eupago (abstração/webhook/caminho de confirmação) e configurações SMTP — requerem credenciais sandbox/produção, contrato/validação do fornecedor e transporte de email + testes end-to-end.
- **Incompleto:** transporte de envio de emails; validação/payload específico do fornecedor no webhook; possíveis operações admin não expostas (eliminar utilizador, editar stock) conforme requisitos.
- **Precisa de correções/ajustes:** referência de imagem órfã na BD original (dado); afinação de CSP/cookies HTTPS em produção; revisão de lacunas admin e hardening fino.
- **Não foi possível testar:** automação visual/E2E completa (Playwright browsers), pagamentos reais/sandbox com fornecedor, entrega real SMTP, concorrência real, auditoria de segurança exaustiva (DAST/matriz completa).

**Prioridades de produção (segurança, integridade, pagamentos, marcações, fiabilidade):** marcações/integridade/financeiro interno e controlos base **sólidos com evidência**; **pagamentos online reais e emails transacionais** permanecem dependentes de integrações externas e **devem ser validados em staging** antes de produção. A BD original foi preservada.