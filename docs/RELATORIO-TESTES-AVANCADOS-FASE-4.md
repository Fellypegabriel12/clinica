# Relatório de Testes Avançados, Validação Funcional e Deteção de Erros — Fase 4
**Projeto:** `C:\wamp64\www\meu-projeto\clinica`  
**Data/hora (execução):** 2026-11-09 ~23:28–23:45 (hora local do ambiente)  
**Base de dados original (NÃO usada para escrita):** `clinica_dentaria` (44 tabelas) — **confirmada intacta** (verificações `COUNT(*)` de referências de teste = 0).  
**Base de dados de testes (isolada, todas as escritas de teste):** `clinica_dentaria_testes_phase3` (cópia funcional).  
**Cópia de aplicação para testes HTTP:** `C:\Users\HP\AppData\Local\Temp\opencode\clinica-phase3-testapp` (sincronizada com o projeto, exceto `includes/core/config.php` apontando para a BD de testes).  
**Servidor de testes:** PHP 8.4.15 built-in `127.0.0.1:8093` (iniciado/parado durante a fase).  
**Backup do projeto (Fase 4):** `C:\Users\HP\AppData\Local\Temp\opencode\clinica-backup-phase4-20261109-232758` (133 entradas).  
**PHP lint:** `php -l` em todos os `*.php` do projeto — **0 erros**.

## 1. Estado verificado das correções anteriores (Fase 3)

| Item | Verificação | Resultado | Classificação |
|---|---|---|---|
| C-F3-01 `estado_financeiro_marcacao()` em `includes/servicos/pagamentos.php` | Hash idêntico original/cópia de teste (`15BC259A…`); `SAME_CONTENT=True`; correção presente (`pago_liq` conta apenas `pago,reembolsado,parcialmente_reembolsado`; `pendente_registado` inclui `parcialmente_pago`); pesquisa por `function estado_financeiro_marcacao` no projeto e na cópia de teste = **apenas 1 definição** em `includes/servicos/pagamentos.php` (linha 68) em cada. Comportamento verificado (fixture: pendente 100€ → `Pendente, pago=0, divida=100`). **Nenhuma alteração adicional feita** (já correta). | Correção existe e é consistente; sem versões divergentes. | **APROVADO** |

## 2. Testes avançados das marcações (BD de testes, HTTP/CSRF/sessão, fixtures limpas)

| # | Cenário | Procedimento (evidência) | Resultado observado | Classificação |
|---|---|---|---|---|
| T1 | Data passada / slot manipulado | Fluxo cliente válido até calendário; POST `horarios[1]='2020-01-01 09:00:00'` | **Rejeitado** (não avançou para tratamentos; redirecionado `marcar-pacientes.php`), sem fatal. | **APROVADO** |
| T2 | Horário indisponível/fora do horário (ex. 03:00) | POST slot `2026-11-15 03:00:00` | **Rejeitado**, sem fatal. | **APROVADO** |
| T3 | Mesmo dentista, mesma hora | Fixture confirmada ocupando `2026-11-16 09:00–10:00` (dentista 1, blocos em `horarios_ocupados`). UI calendário: slot **não oferecido** (`occupied_slot_offered_in_ui=false`). POST do mesmo slot | **Rejeitado** (sem avanço), sem fatal. | **APROVADO** |
| T4 | Mesmo paciente, mesmo horário, dentistas diferentes | Fixture: paciente 1 com dentista 1 às `2026-11-17 09:00`. Tentativa cliente (paciente 1) com dentista 2 no mesmo horário (dentista 2 com disponibilidade/tratamento configurados em BD teste) | **Rejeitado** (`advanced_to_tratamentos=false`, redirect `marcar-pacientes.php`). Comportamento seguro observado (impede sobreposição do paciente); mensagem específica não capturada pelo parser. | **APROVADO COM OBSERVAÇÕES** (mecanismo/UX da mensagem a confirmar em detalhe; bloqueio efetivo confirmado) |
| T5 | Formulário incompleto/inválido — CSRF em falta | POST `marcar-pacientes` `acao=continuar` sem `csrf` | **Rejeitado** HTTP **403**. | **APROVADO** |
| T5b | Dados manipulados — paciente não autorizado | POST com `pacientes[]=999999` | **Rejeitado** com mensagem "Há pacientes escolhidos que não podes marcar…", sem avanço. | **APROVADO** |
| T6 | Reenvio/duplicação após sucesso | E2E presencial completo → 1ª submissão criou `CD-2026-F7D45C` (confirmação). `$_SESSION['marcacao']` limpa pelo fluxo; 2º POST pagamento (sem estado) | 2ª tentativa redirecionou para `dentistas.php?modo=marcar`, **sem segundo `ref`**. Booking de teste limpo. | **APROVADO** |
| T7 | Acesso a marcação de outro paciente (IDOR/confirmação) | Cliente teste acedeu `marcar-confirmacao.php?ref=CD-TESTE-001` (pertence a outro utilizador) | Redirect `area-pessoal.php`, **ref não exposta no corpo**, sem fatal. | **APROVADO** |
| T8 | Cancelamento (cliente) | `POST minhas-marcacoes.php` `acao=cancelar` sobre consulta futura pertencente ao cliente (fixture BD teste) | `marcacao=cancelada`, `consulta=cancelada`, `horarios_ocupados` restantes=**0**; original intacta. | **APROVADO** |
| T9 | Alteração (pedido) | `GET alterar-consulta.php?id=<consulta do cliente>` (200, formulário presente) → POST novo início + motivo `P4TEST` | `pedidos_alteracao` criado: `id=1, estado=pendente`; redirect `minhas-marcacoes.php`. | **APROVADO COM OBSERVAÇÕES** (criação de pedido aprovada; fluxo de aprovação/admin do pedido não exercitado ponta a ponta) |
| T10 | Dados inválidos/manipulados (payload tipo SQLi no horário) | `horarios[1]="...'; DROP TABLE marcacoes;--"` | **Rejeitado**, sem fatal; `marcacoes` existe (`table_exists=1`). (Validação por formato/lógica + PDO em geral; não substitui auditoria SQLi exaustiva.) | **APROVADO COM OBSERVAÇÕES** |
| T11 | Conflitos/quase simultâneo | Evidência estrutural: `horarios_ocupados` **UNIQUE(`dentista_id`,`inicio_bloco`)**; `reservar_horario` captura **PDO 23000** → "ocupado por outra pessoa" (transação). Conflito sequencial comprovado (T3). | Proteções DB+aplicação presentes; **race real paralela** não reproduzida deterministicamente por script sequencial. | **APROVADO COM OBSERVAÇÕES** (limitação de reprodução de corrida real) |
| — | Relações BD (consultas/pacientes/dentistas/tratamentos/horários) | E2E Fase 3 (ref `CD-2026-E57DF6`, limpa) + fixtures: `consultas` ligadas a `marcacao/paciente/dentista`, `consultas_tratamentos` ligado, `horarios_ocupados` ligado a consulta/libertado no cancelamento. | Consistência observada nos cenários testados. | **APROVADO** (cenários testados) |

## 3. Testes avançados do sistema financeiro (nível serviço + cancelamento, BD de testes, fixtures limpas)

| # | Cenário | Evidência | Resultado | Classificação |
|---|---|---|---|---|
| F1 | Valor > dívida (ex. 150€ sobre 100€) | `registar_pagamento_presencial_detalhado` | **Rejeitado**: "O valor tem de ser entre 0,01 € e 100,00 €"; estado inalterado. | **APROVADO** |
| F2 | Valores inválidos (0, -10) | idem | **Rejeitados** com a mesma validação. | **APROVADO** |
| F3 | Reembolso parcial (30€ de 100€ pago) | `registar_reembolso_administrativo` | **OK**: `parcialmente_reembolsado`, `pago=70, divida=30, reembolsado=30`, `valor_reembolsado=30.00`. | **APROVADO** |
| F4 | Reembolso > pago (150€ de 100€) | idem | **Rejeitado**: "O valor excede o disponível para reembolso (100,00 €)"; estado inalterado. | **APROVADO** |
| F5 | Cancelamento com pagamento associado (250€ pago) | `cancelar_consulta` (dono) sobre consulta futura com pagamento `pago` + blocos ocupados | **OK**: retorno reembolso 250; `marcacao/consulta=cancelada`; slots=0; pagamento `reembolsado`, `valor_reembolsado=250`, estado financeiro `Reembolsado`. | **APROVADO** |
| F6 | Duplicado após liquidação | 2º registo presencial após liquidar 100€ | **Rejeitado**: "Esta marcação já está liquidada." | **APROVADO** |
| — | Parcial + pendente remanescente, total, reembolso total | Fase 3 continuação (fixtures limpas, original intacta) | 40/100 → `Parcialmente pago, pago=40, divida=60`, remanescente pendente recriado; total → `Pago, resto=0`; reembolso 80€ → `Reembolsado`. | **APROVADO** (reconfirmado) |
| — | Coerência admin/página/BD, histórico completo, gateway/sandbox | Admin/financeiro carregam (200/auth, Fase 3); coerência de transições validada a nível de serviço/BD; UI completa por todos os cenários não exercitada | Parcial. Gateway/sandbox/SMTP reais dependem de configuração externa. | **APROVADO COM OBSERVAÇÕES** / **BLOQUEADO POR DEPENDÊNCIA EXTERNA** (gateway/SMTP) |

## 4. Painel administrativo — operações reais (amostra Fase 3+4)

**Fases 3/4 (BD teste, CSRF, cleanup):** CRUD por formulário + reflexão pública aprovados para **especialidades, dentistas, tratamentos, horários (clínica), bloqueios, artigos, contactos (público→admin), testemunhos (form/consentimento), parceiros (upload multipart)**; editor do site save→BD→público; 20 módulos admin carregam autenticados (200, sem fatal, CSRF).  
**Fase 4 (marcações/admin):** carregamento `admin/marcacoes.php`; cancelamento via fluxo cliente (T8) e lógica `cancelar_consulta` validada; F5 demonstra impacto financeiro.

| Módulos com operações profundas ainda não exaustivas por todos os casos/erros/UI | Exemplos |
|---|---|
| Marcações/Pagamentos/Finanças (todas as ações admin UI), Utilizadores, Stock, Galeria (uploads completos), Relatórios (todos os filtros/export), Definições (save massivo fiel a todos os `$campos`), fluxos de aprovação de pedidos de alteração | Criar/editar/ativar/desativar/eliminar/validações/permissões/CSRF/reflexo em todos os cenários |

Classificação global do item 5 (pedido): **APROVADO COM OBSERVAÇÕES** (cobertura substancial real; não exaustivo em todos os módulos/cenários).

## 5. Testes visuais e funcionais do site (frontend)

| Verificação | Evidência | Classificação |
|---|---|---|
| Disponibilidade de automação browser | Node `v26.2.0`, `npx.cmd` 11.13.0 presentes; **`ms-playwright` browsers ausentes** (`LOCALAPPDATA\ms-playwright` inexistente); sem `package.json`/config Playwright no projeto. | **NÃO TESTADO** (browser automatizado/consola JS/rede/AJAX real) |
| HTTP/renderização (páginas-chave) | Fases 2/3/4: páginas públicas e protegidas respondem (200/redirects esperados), sem `Fatal/Uncaught` nos cenários testados após correção infra (BOM). Booking/marcação/área pessoal/admin carregam; marcação E2E presencial concluída; confirmação renderiza ref. | **APROVADO COM OBSERVAÇÕES** (nível HTTP; não cobre consola JS, CSS visual, responsivo real, tema escuro/claro, modais/interações JS no browser) |
| Secções que “desaparecem” (homepage) | DB→render comprovado para conteúdos/entidades testadas (hero, testemunhos, dentistas, tratamentos, artigos/blog); reflexão dependente de dados/flags (`visivel`, consentimento). Não validado visualmente em browser. | **APROVADO COM OBSERVAÇÕES** |

**Limitação:** consola JS, erros de rede/AJAX, botões/modais/calendário JS, imagens quebradas visuais, modo claro/escuro e responsividade real em dispositivos/ecrãs **não foram validados** por falta de browser automatizado utilizável.

## 6. Segurança e permissões (testes controlados, amostra)

| Item | Evidência | Classificação |
|---|---|---|
| CSRF | POST sem `csrf` em `marcar-pacientes` → **403** (T5). Formulários admin/públicos testados incluem CSRF. | **APROVADO** (amostra; cobertura exaustiva por módulo não concluída) |
| Acesso indevido / IDOR | `marcar-confirmacao.php?ref=` de outro utilizador → redirect `area-pessoal`, ref não exposta (T7). `alterar-consulta.php?id=` de consulta não pertencente → redirect `minhas-marcacoes` (sem fatal). Admin protegido por `exigir_login`/RBAC (Fases 2/3). | **APROVADO** (amostra) |
| SQL Injection (indício controlado) | Payload no campo de horário rejeitado por validação; tabela intacta. PDO/prepared statements observados em fluxos críticos revistos. | **APROVADO COM OBSERVAÇÕES** (não é pentest exaustivo) |
| XSS (armazenado, amostra) | Mensagem de contacto com `<script>alert(1)</script>`: admin **não** renderizou script bruto; marcador escapado (`&lt;script`); dado limpo, original intacto. | **APROVADO COM OBSERVAÇÕES** (amostra) |
| Validação/manipulação de parâmetros | Paciente não autorizado rejeitado; slots/datas/formatos inválidos rejeitados; overpay/valores inválidos/reembolso excessivo rejeitados. | **APROVADO** (cenários testados) |
| Uploads/path traversal/sessões/erros sensíveis | Upload parceiro (PNG válido) aceite via multipart; download-cv com proteções (Fase 2). Revisão exaustiva de uploads/sessões/exposição de erros não concluída. | **APROVADO COM OBSERVAÇÕES** |

## 7. Correções realizadas nesta fase

| ID | Problema | Origem/causa | Correção | Ficheiros | Validação |
|---|---|---|---|---|---|
| INFRA-P4-01 | Fatal em todas as respostas da **cópia de testes**: `strict_types declaration must be the very first statement in .../includes/core/config.php on line 2` (BOM UTF-8) | Ao sincronizar projeto→testapp, a cópia de teste restaurou `config.php` gravado com BOM (PowerShell `Set-Content -Encoding UTF8`). | `config.php` da cópia de teste reescrito a partir do original, alterando **apenas** `DB_NAME` para `clinica_dentaria_testes_phase3`, **sem BOM**. **Projeto original NÃO modificado.** | `C:\Users\HP\AppData\Local\Temp\opencode\clinica-phase3-testapp\includes\core\config.php` (infra de teste) | Login/fluxos HTTP sem fatal; testes avançados reexecutados com evidência limpa. |
| C-F3-01 | (verificado; sem nova alteração) | — | Correção prévia consistente em original/testapp. | `includes/servicos/pagamentos.php` | Hash/definição única/comportamento OK. |

*Nenhuma funcionalidade já aprovada foi alterada desnecessariamente. Bugs novos confirmados de produto (além de C-F3-01 já corrigido) não foram identificados nos cenários executados; riscos/limitações estão classificados como observações/não testados.*

## 8. Alterações na base de dados de testes / limpeza

- Escritas **apenas** em `clinica_dentaria_testes_phase3` (e ficheiros na cópia de testes / upload de parceiro de teste removido). `configuracoes` da BD de testes restaurada a partir da original quando necessário.
- Dados de teste criados e **removidos**: marcações/consultas/pagamentos/ocupações/`pedidos_alteracao`/mensagens com marcadores P4/F3 de teste (verificações finais = 0). Removido resíduo `CD-P4T4CHK`. `CD-2026-F7D45C` (criado em T6) removido.
- **Confirmação:** contagens de referências de teste em `clinica_dentaria` = **0** (BD original intacta).

## 9. Ficheiros analisados (principais, Fase 4)

Relatórios: `docs/RELATORIO-AUDITORIA-CORRECOES.md`, `docs/RELATORIO-TESTES-FUNCIONAIS.md`, `docs/RELATORIO-TESTES-COMPLETOS-FASE-3.md`. Código: `includes/core/config.php`, `includes/servicos/pagamentos.php`, `includes/servicos/marcacao.php`, `includes/servicos/conta.php`, `marcar-pacientes.php`, `marcar-calendario.php`, `marcar-tratamentos.php`, `marcar-resumo.php`, `marcar-pagamento.php`, `marcar-confirmacao.php`, `alterar-consulta.php`, `minhas-marcacoes.php`, `admin/marcacoes.php`, `admin/pagamentos.php`, `admin/financeiro.php`, `contactos.php`, `admin/contactos.php` (e módulos revistos nas fases anteriores). BD: `horarios_ocupados` (UNIQUE), `marcacoes/consultas/pagamentos/consultas_tratamentos/pedidos_alteracao`.

## 10. Dependências externas / bloqueios

- **BLOQUEADO POR DEPENDÊNCIA EXTERNA:** gateway de pagamentos real/sandbox + webhooks assinados/autenticados; SMTP real; credenciais/segredos (não inventados).
- **NÃO TESTADO (ambiente):** frontend em browser automatizado (consola JS, erros de rede/AJAX, responsividade real, tema claro/escuro, modais/calendário JS) — Playwright browsers não instalados.

## 11. Problemas que continuam por resolver / recomendações (próxima fase)

1. **Frontend browser:** instalar/configurar Playwright (browsers) ou alternativa headless e validar JS/console/rede, responsividade, tema escuro/claro, modais/calendário, imagens.
2. **E2E marcações adicionais:** aprovação de pedidos de alteração, alteração efetiva pelo admin, conflitos em corrida real (teste de concorrência controlado), cancelamento por admin com todos os impactos financeiros/UX.
3. **Admin exaustivo:** operações e validações/erros/permissões/CSRF/reflexo público em Utilizadores, Stock, Galeria (uploads), Relatórios, Definições (save completo), e todos os casos de erro por módulo.
4. **Financeiro UI/coerência:** validar admin/páginas financeiras vs BD para todos os estados (incl. cancelados/reembolsos parciais) com evidência de ecrã/dados.
5. **Gateway/SMTP:** configurar sandbox + webhook idempotente/autenticado e transporte SMTP de teste; validar notificações.
6. **Segurança:** revisão exaustiva (SAST/DAST controlado), rate limiting/abuso, cabeçalhos, CSP, gestão de sessões/segredos em produção.

## 12. Resumo de classificações

- **APROVADO:** C-F3-01 consistente; marcações T1–T3,T5,T5b,T6–T8,T10 e relações BD (testadas); financeiro F1–F6 + transições confirmadas + cancelamento com pagamento; CSRF/IDOR (amostra); SQLi indicativo rejeitado; XSS armazenado escapado (amostra).
- **APROVADO COM OBSERVAÇÕES:** T4 (bloqueio de sobreposição do mesmo paciente observado; mensagem não capturada), T9 (pedido criado; aprovação não E2E), T11 (corrida real não reproduzida), admin exaustivo parcial, frontend HTTP sem browser, coerência UI financeira parcial, segurança amostral.
- **REPROVADO:** nenhum nos cenários executados.
- **NÃO TESTADO:** browser/JS/consola/responsivo/tema real; módulos/cenários admin e financeiros não cobertos; aprovação/alteração admin E2E completa.
- **BLOQUEADO POR DEPENDÊNCIA EXTERNA:** gateway sandbox/webhook real, SMTP real.

*Relatório baseado apenas em evidências observadas. A base de dados original `clinica_dentaria` não foi utilizada para escritas de teste e permaneceu intacta (verificado).*