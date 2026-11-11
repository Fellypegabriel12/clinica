# Relatório de Testes Completos — Fase 3

**Projeto original:** `C:\wamp64\www\meu-projeto\clinica` (não alterado para apontar à BD de testes; correções de código aplicadas nas Fases 1–2/3 preservadas)  
**Data:** 2026-11-09  
**Base:** `docs/RELATORIO-AUDITORIA-CORRECOES.md`, `docs/RELATORIO-TESTES-FUNCIONAIS.md` (não repetidos).

## 1. Ambiente de testes seguro (obrigatório)

| Item | Resultado | Evidência |
|---|---|---|
| Backup ficheiros | Existente (Fase 1) | `C:\Users\HP\AppData\Local\Temp\opencode\clinica-backup-auditoria-20261109-222027` |
| Backup BD (dump) | Criado | `C:\Users\HP\AppData\Local\Temp\opencode\clinica_dentaria-backup-20261109-phase2.sql` e dump de restore `clinica_dentaria_phase3-restore.sql` |
| Cópia isolada da BD | **Criada**: `clinica_dentaria_testes_phase3` (drop/create + restore de `clinica_dentaria`; sem `USE/CREATE DATABASE` no dump de restore) | 44 tabelas; contagens iniciais coerentes (ex.: utilizadores 2 antes dos testes, dentistas 3, tratamentos 7, pacientes 5, marcações 16) |
| BD original `clinica_dentaria` | **Não alterada** nos testes de escrita | Verificações pós-teste (ex.: `hero_titulo` sem marcador de teste; sem dentista/tratamento de teste) |
| Cópia da aplicação para testes | Criada, configurada só para BD de testes | `C:\Users\HP\AppData\Local\Temp\opencode\clinica-phase3-testapp` — `includes/core/config.php` com `DB_NAME='clinica_dentaria_testes_phase3'` (**apenas na cópia temp**) |
| Servidor de testes | PHP built-in server (cópia temp) | `php -S 127.0.0.1:8093 -t .` em `clinica-phase3-testapp` — GET `/index.php` → 200, sem fatal |
| Utilizadores de teste (admin/receção/dentista/cliente) | Criados **apenas** em `clinica_dentaria_testes_phase3.utilizadores` (IDs 5–8, ativos) | Perfis: `admin`, `rececao`, `dentista`, `paciente` |
| Credenciais de teste documentadas | Ficheiro local, fora do projeto | `C:\Users\HP\AppData\Local\Temp\opencode\clinica-docs\CREDENCIAIS-TESTE-PHASE3.local.txt` (**não publicar/incluir no projeto**) |

**Estado:** APROVADA (ambiente isolado operacional para testes HTTP/BD).

## 2. Painel administrativo (sessão autenticada, cópia de testes)

| Módulo/teste | Procedimento | Esperado | Obtido | Erro | Correção | Re-teste | Estado |
|---|---|---|---|---|---|---|---|
| Login admin | POST `login.php` com CSRF + credenciais de teste (app 8093) | Acesso a admin | Redirect/título “Administração” (HTTP 200 final) | — | — | OK | APROVADA |
| Editor do site (acesso) | GET `admin/editor-site.php?grupo=hero` autenticado | Página carrega, sem redirect login | 200, sem fatal, CSRF presente | — | — | OK | APROVADA |
| **Editor do site (save via formulário)** | Admin autenticado; GET `grupo=hero` → extrair CSRF + todos `c[...]`; POST com `hero_titulo` alterado; verificar BD teste + página pública; restaurar valor; verificar BD original | Guardar persiste e reflete no público; original intacta | `save_code=200`, flash sucesso, `db_value`=marcador na **BD de testes**, `public_reflected=true`, `orig_db` original inalterado | — | — | OK | **APROVADA** (round-trip admin form → BD → público) |
| Renderização autenticada (20 módulos-chave) | GET autenticado (admin): `index,configuracoes,dentistas,tratamentos,especialidades,marcacoes,pagamentos,financeiro,horarios,bloqueios,utilizadores,stock,editor-site,artigos,testemunhos,galeria,parceiros,contactos,auditoria,relatorios` | 200, sem fatal, sem redirect login, CSRF presente | Todos 200, `fatal=false`, `redir_login=false`, `has_csrf=true` | — | — | OK | **APROVADA** (carregamento autenticado/render base) |
| CRUD completo por módulo (criar/editar/guardar/eliminar/pesquisar/filtrar/aprovar/rejeitar/exportar) para **todos** os módulos admin | Submissões autenticadas com payloads válidos por módulo | Persistência e mensagens corretas | Não executado exaustivamente (payloads/formatos específicos por módulo; volume alto). Evidência forte: editor save + render autenticado + RBAC. | — | — | — | **NÃO TESTADA** (CRUD exaustivo por todos os módulos) |
| Validação de formulários/mensagens | Submissões inválidas por módulo | Erros amigáveis, sem fatal | Não testado sistematicamente | — | — | — | NÃO TESTADA |

## 3. Comunicação painel administrativo ↔ site público (testes reais, BD de testes)

| Teste | Procedimento (BD `clinica_dentaria_testes_phase3`, app 8093) | Esperado | Obtido | Estado |
|---|---|---|---|---|
| Hero/título — **form admin save** | Admin: GET editor hero (CSRF+campos `c[]`) → POST save com título alterado → verificar BD teste + `index.php` → restaurar; verificar BD original | Persistência + reflexo público; original intacta | Save 200/flash sucesso; DB teste atualizada; `public_reflected=true`; `orig_db` inalterado | **APROVADA** |
| Contactos (público→admin) | POST `contactos.php` (CSRF) → mensagem em `mensagens_contacto` (BD teste) → admin lista/marca lida/apaga → cleanup | Registo criado e gerido no admin | `success_msg=true`, `db_row` criada, vista no admin, `lida=1`, `remaining=0`, `orig_untouched=0` | **APROVADA** |
| Artigos/blog (admin CRUD→público) | Admin: create via `admin/artigos.php` (CSRF, guardar) → verificar BD + `blog.php` → edit → delete; verificar original | CRUD persiste e reflete no blog | Create DB ok, `blog_reflects=true`; edit título ok; `remaining=0`, `orig_untouched=0` | **APROVADA** |
| Testemunhos (integração+admin ações) | Insert testemunho válido (visível+consentimento) na BD teste → `index.php` reflete → admin toggle visibilidade → delete; verificar original | Visível no site, ações admin funcionam | `home_has=true`; `vis_after_toggle=0`; `remaining=0`; `orig_untouched=0` | **APROVADA COM OBSERVAÇÕES** (criação por form admin envolve consentimento/upload; ações/listagem/toggle/delete comprovadas) |
| Parceiros (admin ações) | Insert parceiro na BD teste (logo dummy) → admin lista/toggle/delete; verificar original | Listagem e ações admin funcionam | `listed=true`, `vis_after_toggle=0`, `remaining=0`, `orig_untouched=0` | **APROVADA COM OBSERVAÇÕES** (criação via form requer upload de logo) |
| Dentista no site público (integração DB→público) | Insert/delete na BD teste + GET `dentistas.php` | Reflete | `FOUND=True`, limpo, original intacto | **APROVADA** (integração; CRUD por form admin não exaustivo neste batch) |
| Tratamento no site público (integração DB→público) | Insert/delete na BD teste + GET `tratamentos.php` | Reflete | `FOUND=True`, limpo | **APROVADA** (integração) |
| Configurações | GET autenticado `admin/configuracoes.php` | 200, sem fatal, CSRF | 200, `fatal=false`, `has_csrf=true` | **APROVADA COM OBSERVAÇÕES** (save `cfg[]` amplo não automatizado para evitar sobrescrita em massa) |
| Galeria/horários/hero uploads/contactos info/rodapé/outros módulos | CRUD+reflexo via UI | — | Não testados neste batch | **NÃO TESTADA** |


### 3b. CRUD administrativo por formulário (evidência, BD teste, cleanup)

Todos executados via HTTP autenticado (CSRF) contra `http://127.0.0.1:8093` → `clinica_dentaria_testes_phase3`; registos de teste criados, validados (BD + reflexão pública quando aplicável) e **removidos**; **BD original `clinica_dentaria` sem marcadores**.

| Módulo | Operações (form/ação) | Evidência | Estado |
|---|---|---|---|
| Especialidades | create (`guardar`), edit, `alternar_ativo`, delete (cleanup direto após teste) | DB criada/editada, `ativo` alternado, `remaining=0`, `orig_untouched=0` | **APROVADA** |
| Dentistas (sem foto) | create, edit, `alternar_ativo`, delete | Criado/editado, `public_reflects=true` em `dentistas.php`, toggle OK, `remaining=0`, `orig_untouched=0` | **APROVADA** |
| Tratamentos | create, edit (nome/preço/duração), `alternar_ativo`, delete | Criado/editado (`59.00,45min`), `public_reflects=true` em `tratamentos.php`, `remaining=0`, `orig_untouched=0` | **APROVADA** |
| Horários clínica | save `horarios_funcionamento` (POST `b[dia][n]`) → verificar DB → **restaurar defaults** | `flash_ok=true`, DB alterada (2 blocos teste) e restaurada (10 blocos padrão); original intacta | **APROVADA** |
| Bloqueios | create (futuro), delete | Criado em `bloqueios_horario`, removido, `orig_untouched=0` | **APROVADA** |
| Testemunhos (form completo, sem foto, consentimento) | create com `consentimento_confirmado=1`, refletido na home, edit, `alternar_visivel`, apagar | `home_reflects=true`, edit OK, `vis_after_toggle=0`, `remaining=0`, `orig_untouched=0` | **APROVADA COM OBSERVAÇÕES** (upload foto não exercitado no form) |
| Parceiros (form + **upload logo multipart**) | create com `CURLFile` PNG 1x1, listagem admin, `alternar_visivel`, apagar + cleanup ficheiro | `logo` guardado (`uploads/parceiros/*.png`), `listed=true`, toggle/delete OK, `orig_untouched=0` | **APROVADA** |
| Configurações (save `cfg[]`) | POST massivo tentado (extração de campos); handler validou/rejeitou submissão inconsistente (sem corromper estado crítico restaurado) | `save_code=200` com alertas de validação; valores-chave verificados/restaurados a partir do original; acesso/auth/CSRF OK | **APROVADA COM OBSERVAÇÕES** (save completo via UI requer payload fiel a todos os `$campos`; risco de sobrescrita evitado) |


## 4. Sistema de marcações

*Nota:* `autorizacoes_paciente` (cliente teste 8 → paciente 1, `proprio`, `pode_marcar=1`) existe **apenas em `clinica_dentaria_testes_phase3`**.

| Teste | Procedimento (BD teste / app 8093) | Esperado | Obtido | Estado |
|---|---|---|---|---|
| Login cliente | POST `login.php` (CSRF) | Acesso à área pessoal | 200, `area-pessoal.php` | **APROVADA** |
| Área pessoal / pagamentos / marcações | GET autenticado | 200, sem fatal | 200, sem fatal | **APROVADA** |
| Entrada/marcar + guardas de fluxo | GET passos sem sessão | Carrega ou redireciona controlado, sem fatal | OK, sem `Fatal/Uncaught` | **APROVADA** |
| **E2E completo (presencial, multi-passo POST)** | Cliente → `marcar-pacientes?dentista=1&tratamento=7` → POST continuar (paciente 1, contacto) → POST `marcar-calendario` slot `2026-11-10 09:00:00` (dentista 1) → POST `marcar-tratamentos` `trat[1][]=7` (60min) → resumo → POST `marcar-pagamento` `metodo=presencial`, `aceita_condicoes=1` | Marcação confirmada, BD consistente, confirmação visível; original intacta | **Sucesso** → `marcar-confirmacao.php?ref=CD-2026-E57DF6`. BD teste: `marcacoes` id17 `CD-2026-E57DF6` **confirmada** 250.00 user8; `consultas` id19 pac1 dent1 2026-11-10 09:00–10:00 **confirmada** 250.00; `consultas_tratamentos` trat7 250.00. Confirmação cliente 200 (ref visível, sem fatal). `clinica_dentaria` sem a ref. **Cleanup executado** (0 restantes). | **APROVADA** (fluxo presencial E2E) |
| Cenários adicionais: alterar, cancelar, expiração, duplicados, indisponíveis, conflitos concorrentes | Testes dedicados | Comportamento correto | Não executados neste batch | **NÃO TESTADA** (cenários adicionais) |

## 5. Sistema financeiro / pagamentos

| Teste | Procedimento (BD `clinica_dentaria_testes_phase3`) | Esperado | Obtido | Estado |
|---|---|---|---|---|
| Páginas financeiras (acesso) | GET `admin/pagamentos`, `admin/financeiro`, `meus-pagamentos` sem login | Protegidas | Redirect login | **APROVADA** |
| **Registo presencial completo** (service-level, fixture isolada) | `registar_pagamento_presencial_detalhado` (marcação confirmada 100€, pendente 100€, método `dinheiro`, staff 5) | Transição Pendente→Pago, referência gerada, BD consistente | **OK**: resultado `resto=0`, ref `CLIN-*`; estado `Pago` (`pago=100, divida=0`); fixture criada/limpa; original intacta | **APROVADA** (nível serviço, BD teste) |
| **Pagamento parcial + dívida/remanescente** | Registo presencial de 40€ sobre 100€ (pendente 100€) | Parcialmente pago, pendente 60€ recriado | **OK**: `Parcialmente pago` (`pago=40, divida=60, pendente_reg=60`); pagamento pago `mbway_presencial` 40€ + pendente remanescente 60€; fixture limpa | **APROVADA** (nível serviço) |
| **Reembolso administrativo total** | `registar_reembolso_administrativo` (pagamento `pago` 80€) | Estado reembolsado, `valor_reembolsado` correto | **OK**: `reembolsado`/estado `Reembolsado`, `valor_reembolsado=80.00`, `pago_liq=0`; fixture limpa; original intacta | **APROVADA** (nível serviço) |
| **Bug confirmado e corrigido (Fase 3)**: `estado_financeiro_marcacao` | Pagamento `pendente` 100€ / total 100€ | `divida=100`, estado Pendente (não contar pendente como pago) | **Antes (bug):** contava `SUM(valor-valor_reembolsado)` de todos os estados como `pago_liq` → marcava como liquidada/Pago. **Correção aplicada** (projeto + cópia teste): `pago_liq` soma apenas estados `pago,reembolsado,parcialmente_reembolsado`; `pendente_registado` inclui `parcialmente_pago`. Verificado: `pago=0, divida=100, estado=Pendente`. `php -l` OK. | **CORRIGIDA / APROVADA** |
| Gateway/sandbox real, webhook autenticado em ambiente integrado, SMTP real, cancelamento E2E com impacto financeiro completo por UI | Credenciais/configuração externa | — | Não testado (dependências externas / E2E UI adicional) | **DEPENDENTE DE CONFIGURAÇÃO EXTERNA / NÃO TESTADA** |

*Nota:* a marcação presencial E2E (secção 4) confirmou o fluxo de reserva/confirmação; o registo `pagamentos` criado no fluxo de marcação presencial segue a lógica da aplicação (estado/valores conforme implementação). A evidência financeira detalhada acima valida as transições de estado via serviços de pagamento.

## 6. Frontend no browser (Playwright/automação)

| Teste | Procedimento | Esperado | Obtido | Estado |
|---|---|---|---|---|
| Navegação, mobile, botões, formulários, modais, calendário, pesquisa, filtros, animações, imagens, tema claro/escuro/alternância, responsividade, consola JS, erros de rede, AJAX/fetch | Playwright/browser automatizado | Sem erros funcionais/visuais | **npx/Playwright PS1 bloqueado por ExecutionPolicy**; binários Playwright não confirmados/instalados; execução de browser automatizado **não realizada** | **NÃO TESTADA** (limitação de ferramentas/ambiente; conforme regra, não declarado concluído) |

## 7. Segurança e validação (amostra/estática + correções confirmadas)

| Achado | Estado |
|---|---|
| RBAC no servidor em admin/páginas protegidas | APROVADA (verificado) |
| CSRF em fluxos revistos | APROVADA COM OBSERVAÇÕES (amostra; cobertura exaustiva requer testes por módulo) |
| SQL Injection (PDO/prepared statements em fluxos críticos revistos) | APROVADA COM OBSERVAÇÕES |
| XSS (uso de `e()`/escape no núcleo; cobertura total não varrida) | APROVADA COM OBSERVAÇÕES |
| Uploads/download CV (path traversal mitigado no `download-cv`) | APROVADA COM OBSERVAÇÕES |
| Webhook pagamentos (POST, validação gateway fail-closed, idempotência) | APROVADA (melhorado Fase 1) |
| Redeclarações fatais (cookies/redes sociais) | **Corrigidas** Fase 2 | 

## 8. Correções adicionais realizadas (Fases 2/3)
Ver `RELATORIO-TESTES-FUNCIONAIS.md` e `RELATORIO-AUDITORIA-CORRECOES.md`. Principais: `marcar-confirmacao.php` (financeiro), `pagamento-webhook.php` (POST/auth clara), `marcar-pagamento.php` (gateway-aware, rollback), `includes/ui/rodape.php` + `includes/ui/redes-sociais.php` (redeclare), `includes/core/cookies.php` + `ui/rodape.php` (redeclare). `php -l` final: **109 ficheiros, 0 erros**.

## 9. Estado final por classificação (atualizado)

| Área | Classificação |
|---|---|
| Isolamento de ambiente/BD de testes (original intacta) | **APROVADA** |
| Login admin/cliente (HTTP, CSRF, BD testes) | **APROVADA** |
| Proteção admin/área pessoal + guardas de fluxo | **APROVADA** |
| Renderização autenticada admin (20 módulos-chave) | **APROVADA** |
| CRUD admin por formulário (especialidades, dentistas, tratamentos, horários clínica, bloqueios) | **APROVADA** |
| Editor do site: save por formulário → BD → público | **APROVADA** |
| Contactos: público→BD→admin (listar/marcar lida/apagar) | **APROVADA** |
| Artigos: admin CRUD (form) → blog público | **APROVADA** |
| Testemunhos: form completo (consentimento) + reflexão + ações | **APROVADA COM OBSERVAÇÕES** (upload foto não exercitado) |
| Parceiros: form + upload multipart + ações | **APROVADA** |
| Dentistas/Tratamentos: CRUD form + reflexão pública | **APROVADA** |
| Configurações: load/auth/CSRF/validação | **APROVADA COM OBSERVAÇÕES** (save massivo automatizado com fidelidade total a `$campos` não concluído) |
| CRUD exaustivo autenticado (todos módulos/ações/casos de erro) | **NÃO TESTADA** |
| Reflexo admin→público via UI (todos módulos) | **NÃO TESTADA** (cobertura parcial substancial aprovada) |
| Marcação E2E (presencial, POSTs multi-passo, BD consistente) | **APROVADA** |
| Marcação: alterar/cancelar/expiração/duplicados/conflitos | **NÃO TESTADA** |
| Financeiro: presencial completo/parcial+remanescente/reembolso (serviço, BD teste) | **APROVADA** |
| Financeiro: E2E UI completo/histórico/cancelamento-impacto/casos adicionais | **NÃO TESTADA** (parcial) |
| Bug financeiro `estado_financeiro_marcacao` (pendente contado como pago) | **CORRIGIDA / APROVADA** |
| Gateway pagamentos real/sandbox | **DEPENDENTE DE CONFIGURAÇÃO EXTERNA** |
| Frontend browser (Playwright/consola JS/visual) | **NÃO TESTADA** |
| Segurança (amostra) | **APROVADA COM OBSERVAÇÕES** |

## 10. Para atingir “aprovação” completa (próximos passos objetivos)
1. Executar CRUD autenticado por perfil em `clinica_dentaria_testes_phase3` (ou nova cópia) com um runner (Playwright/Cypress) ou checklist manual assistido; registar evidências por módulo.
2. Validar reflexo UI admin→público para hero, contactos, horários, blog/artigos, testemunhos, galeria, parceiros, rodapé, configurações.
3. Testar marcação E2E (incl. expiração/duplicados/cancelamento) e financeiro (presencial/pendente/parcial/reembolso/dívida).
4. Configurar gateway **sandbox** + webhook HTTPS e testar confirmação idempotente.
5. Resolver execução de browser automation no host (policy/instalação Playwright browsers) e correr suite frontend.

## 11. Limpeza recomendada (pós-testes)
- Parar servidor PHP `127.0.0.1:8093` se ainda ativo.
- Remover utilizadores `*.phase3@teste.local` de `clinica_dentaria_testes_phase3` e/ou eliminar a BD de testes quando concluído.
- Manter `CREDENCIAIS-TESTE-PHASE3.local.txt` fora do repositório/projeto.