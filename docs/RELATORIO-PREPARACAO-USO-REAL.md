# Relatório de Preparação para Uso Real — Clínica Dentária
**Projeto:** `C:\wamp64\www\meu-projeto\clinica` (PHP 8.4.15, MySQL 8.4.7, PDO, WAMP/Windows)  
**Data:** 2026-11-11  
**BD existente (confirmada):** `clinica_dentaria` @ `localhost` (WAMP MySQL local). Conexão identificada em `includes/core/config.php` (`DB_HOST/DB_NAME/DB_USER/DB_PASS`) — **credenciais/segredos não expostos neste relatório**.  
**Inspeção BD:** realizada **em modo de leitura** (esquema/tabelas/colunas/relações/índices/triggers/rotinas) via `information_schema`; **nenhum dado original modificado**.

## 1. Backups e isolamento (executado e confirmado)
| Item | Caminho/identificador | Evidência |
|---|---|---|
| Cópia de segurança completa do projeto (antes de alterações) | `C:\Users\HP\AppData\Local\Temp\opencode\clinica-backup-realprep-20261111-101259` | **136 ficheiros** copiados (recursivo). |
| Backup da BD (dump restaurável) | `C:\Users\HP\AppData\Local\Temp\opencode\clinica_dentaria-backup-realprep-20261111-101259.sql` | 93.238 bytes; contém `CREATE TABLE` (44 tabelas). **Validação de restauro:** restauro de cópia transformada do dump em BD temporária → **44 tabelas**, BD temporária removida (`VALIDATED=yes`). Dump original preservado. |
| Cópia isolada do projeto (trabalho/testes) | `C:\Users\HP\AppData\Local\Temp\opencode\clinica-realprep-work` | Cópia do projeto; `includes/core/config.php` apontado a BD isolada. |
| Cópia isolada da BD (testes/validação) | `clinica_dentaria_realprep_test` | Criada por dump/restauro a partir de `clinica_dentaria`; **44 tabelas** (igual ao original). Escritas de teste/validação apenas nesta BD (com limpeza). |

> **Não** foram apagadas, esvaziadas, substituídas nem migradas a BD original nem dados existentes. Operações destrutivas/irreversíveis não executadas.

## 2. Inspeção realizada (código e BD)
- **Código/config/deps/docs/logs:** estrutura completa (PHP/JS/CSS, `admin/`, `includes/core|servicos|ui`, `migracoes/`, `docs/`, `logs/`, `uploads/`, `.htaccess`). Sem `composer.json`/`package.json` detetados. 3 migrações SQL aditivas (`001_configuracoes_valor_text.sql`, `002_pagamentos_seguros_planos.sql`, `003_rastreabilidade_redes_sociais.sql`). Sem cron/jobs detetados (expiração/limpeza dependente de execução web).
- **BD real:** 44 tabelas (InnoDB, utf8mb4), PK/FK/índices/constraints presentes nas tabelas nucleares; **0 triggers, 0 rotinas**. Campos/estruturas relevantes confirmados (ex.: `pacientes` sem `nif`, com `numero_utente_sns`; `pagamentos` com idempotência/gateway; `registo_admin` com rastreabilidade; `configuracoes.valor` TEXT).
- **Comparação esquema↔código:** pesquisa de uso de tabelas/colunas em PHP e confronto com esquema real (análise estática; exaustividade absoluta não garantida para SQL dinâmico/greps truncados — ver limitações).

## 3. Problemas confirmados, causas, impacto e prioridade
| ID | Prioridade | Localização | Causa (evidência) | Impacto |
|---|---|---|---|---|
| P-01 | **Alta** | `admin/financeiro.php` (query/pesquisa/UI linhas ~56,62,79,107,110,111) | Código referencia `pacientes.nif` em `SELECT`/`WHERE`/render, mas `clinica_dentaria.pacientes` **não possui** coluna `nif` (esquema real). | Pesquisa de histórico financeiro por paciente falha com erro SQL “Unknown column `p.nif`”, quebrando a funcionalidade. |
| P-02 | **Alta** | `recuperar-password.php` (~23-32, ~63-65) | Anti-abuso de sessão filtra janela mas **não regista** a tentativa atual em `$_SESSION['recup_tentativas']` (limite ineficaz). Inserção em `tentativas_login` usa `$ip` **não definido** no âmbito (deveria usar `ip_cliente()`). | Rate-limit por sessão ineficaz (abuso/ruído de tokens); aviso/valor incorreto de IP no registo, reduzindo fiabilidade/auditoria. |
| P-03 | **Alta** | `recuperar-password.php`, `admin/configuracoes.php` (ausência de transporte) | Sem implementação de envio de email (`mail()`/PHPMailer/SMTP) detetada; em produção a UI indica envio mas não existe transporte integrado; em `MODO_DEV` expõe link (demonstração). | Recuperação/notificações externas podem não chegar a utilizadores reais (dependência de suporte manual/link dev). |
| P-04 | **Média-Alta** | `includes/servicos/gateway.php`, `marcar-pagamento.php`, `pagamento-webhook.php` | Gateway online em scaffold/fail-closed (sem credenciais/API/validação exata do fornecedor); webhook estruturado (POST, autenticação `hash_equals`, `idempotency_key`, dedupe) mas dependente de contrato/config. | Cobrança online real indisponível sem integração/credenciais; risco operacional se ativado sem validação completa. |
| P-05 | **Média** | `includes/core/config.php` (cookies/HTTPS) | Cookie de sessão `secure` condicional a `$_SERVER['HTTPS']`; sem redirecionamento forçado HTTP→HTTPS na aplicação; HSTS apenas em HTTPS. | Em HTTP/má deteção atrás de proxy, cookies podem transitar sem proteção de transporte. (Dependente de infraestrutura/HTTPS em produção.) |
| P-06 | **Média** | `uploads/.htaccess`, `admin/dentistas.php`, `includes/core/config.php` (avatar) | `uploads/.htaccess` só desativa indexação (sem negação explícita de execução de scripts em imagens); renderização de avatar tentava carregar ficheiro local inexistente. | Defesa em profundidade de uploads aquém do ideal; referência órfã causava 404/imagem quebrada (mitigado em código). |
| P-07 | **Média** | `clinica_dentaria.dentistas` (id 2 “ASD”) / `uploads/dentistas/` | `fotografia` referencia `uploads/dentistas/825815881b356966aac71d152fedff11.jpg` inexistente (confirmado em BD original e disco; 2ª foto existente). | Dado órfão; impacto visual mitigado por fallback. **BD original não alterada.** |
| P-08 | **Média** | Projeto (global) | Cobertura de testes visuais/E2E completa limitada (Playwright browsers não instalados no ambiente); matriz exaustiva RBAC/IDOR/CSRF, DAST, concorrência real e todos os módulos/casos não concluída. | Risco residual não detetado em cenários não exercitados. |
| P-09 | **Baixa-Média** | `admin/utilizadores.php`, `admin/stock.php` (observação) | Ações “eliminar utilizador” e “editar produto stock” não observadas nos handlers revistos. | Lacuna funcional a confirmar face a requisitos (não comprovada como defeito). |
| P-10 | **Informativo (corrigido documentalmente)** | Relatório Fase 5 / `login.php` | `session_regenerate_id(true)` **existe** no login (com rotação CSRF); nota anterior de ausência estava incorreta. | Falso positivo corrigido; mitigação base de fixação de sessão presente. |

**Não confirmado (exige validação adicional):** conformidade legal/RGPD/ERS/OMD; segurança efetiva do servidor/proxy em produção; backups operacionais/rotação/monitorização/logs; ausência de outras incompatibilidades coluna-a-coluna além de P-01 em todo o SQL dinâmico; completude fiscal (recibos/faturas/reconciliação bancária); comportamento real de gateway/SMTP externos; exaustividade de todos os fluxos (lista de espera, registos clínicos dedicados — notas clínicas em consultas existem, módulo de prontuário estruturado não identificado; faturação/recibos fiscais não identificados).

## 4. Correções realizadas (código) e ficheiros alterados
Correções aplicadas na **cópia de trabalho** e, por serem seguras/validadas (com backup prévio do projeto), aplicadas também ao **projeto original**:

| Ficheiro | Alteração (resumo) | Estado |
|---|---|---|
| `admin/financeiro.php` | Substituição de `p.nif` (inexistente) por `p.numero_utente_sns` em `SELECT`/`WHERE`/parâmetros e atualização de rótulos/placeholder/coluna para “Nº utente SNS”. | **Corrigido e Testado** (carga/pesquisa 200 sem erro SQL na BD isolada). |
| `recuperar-password.php` | Definição de `$ip = ip_cliente()`; registo da tentativa atual em `$_SESSION['recup_tentativas']` quando dentro do limite (anti-abuso de sessão efetivo). | **Corrigido e Testado** (sem fatal/undefined `$ip`; 6º pedido bloqueado na sessão). |
| `includes/core/config.php` | (Fase 6, preservado) `avatar_dentista`: fallback a iniciais quando ficheiro local referenciado não existe (evita 404/imagem quebrada). (Fase 6) Cabeçalhos: **CSP compatível** adicionada; **HSTS** apenas quando `HTTPS` ativo. | **Corrigido e Testado** (sem 404 do avatar órfão; headers presentes; smokes sem quebra). |
| `docs/RELATORIO-AUDITORIA-FASE-5.md` | Correção factual (sessão/P5-02, tratamento P5-01). | **Atualizado**. |
| `docs/RELATORIO-FINAL-PREPARACAO-PRODUCAO.md` | Relatório final Fase 6. | **Criado** (fase anterior). |
| `docs/RELATORIO-PREPARACAO-USO-REAL.md` | Este relatório. | **Criado**. |

> `php -l` (todos os `*.php` do projeto, após correções): **0 erros**.

## 5. Alterações propostas à base de dados
- **Necessárias para incompatibilidades confirmadas:** **nenhuma**. O P-01 foi resolvido por correção de código alinhada ao esquema real.
- **Migrações existentes (aditivas, no repositório):** `001` (valor TEXT em `configuracoes`), `002` (pagamentos/seguros-planos), `003` (rastreabilidade/redes sociais). O esquema real inspecionado **já reflete** estas estruturas (campos/índices/constraints observados). **Não aplicado nada à BD original.**
- **Propostas opcionais (dependem de decisão/requisito — NÃO aplicar à BD original sem autorização):** (a) eventual coluna `pacientes.nif` (ou equivalente) se requisito funcional/legal o exigir — exigiria migração aditiva, atualização de UI/consultas e validação; (b) revisão de índices/constraints adicionais conforme carga (não confirmado como necessário). Qualquer migração deve ser aditiva, documentada, testada na cópia isolada e com backup/restauro validados.

## 6. Testes e comandos realmente executados e resultados
| # | Comando/teste | Contexto | Resultado |
|---|---|---|---|
| 1 | Cópia recursiva do projeto (`Copy-Item -Recurse`) | Backup | **136 ficheiros** em `clinica-backup-realprep-20261111-101259`. |
| 2 | `mysqldump -u root --routines --triggers --single-transaction --databases clinica_dentaria --result-file=...` | Backup BD | Dump criado (93.238 B). |
| 3 | Restauro de dump (transformado para BD temp) + contagem tabelas + drop temp | Validação restauro | **44 tabelas** restauradas; BD temporária removida (**VALIDATED**). |
| 4 | Criação `clinica_dentaria_realprep_test` (dump/restauro de `clinica_dentaria`) | Isolamento | **44 tabelas**. Escritas posteriores apenas nesta BD. |
| 5 | Cópia isolada do projeto + ajuste `DB_NAME` para BD de teste | Isolamento | `clinica-realprep-work` operacional contra `clinica_dentaria_realprep_test`. |
| 6 | `mysql ... information_schema` (TABLES/COLUMNS/CONSTRAINTS/KEYS/STATISTICS/TRIGGERS/ROUTINES) | Inspeção BD (leitura) | Esquema real extraído (44 tabelas, 0 triggers/rotinas). |
| 7 | `php -l` recursivo em projeto/cópia | Lint PHP | **0 erros** (antes e após correções). |
| 8 | `php -S 127.0.0.1:8094` (cópia isolada) + HTTP: login admin temp + `GET /admin/financeiro.php?paciente=ana` | Validação P-01 | **200, sem Fatal/Uncaught/Unknown column**; UI atualizada para “nº utente SNS”. (Teste com utilizador admin temporário criado/removido na BD isolada.) |
| 9 | HTTP: `POST /recuperar-password.php` x6 (sessão) com email inexistente | Validação P-02 | **6º pedido bloqueado** (“Demasiados pedidos”), **sem fatal/undefined `$ip`**; registos de teste limpos na BD isolada. |
| 10 | (Fases anteriores, aproveitadas) smokes público/admin, RBAC 403, CSRF 403, SQLi login rejeitado, XSS amostras, idempotência gateway/dedupe, alteração aprovar/rejeitar, cancelamentos/financeiro, tema/persistência, screenshots estáticos | Cópias isoladas/BDs de teste | Conforme `docs/RELATORIO-*` (evidências em `C:\Users\HP\AppData\Local\Temp\opencode\clinica-docs\`, `screenshots-phase5/`). |
| 11 | Verificação de integridade BD original | Pós-testes | Contagens de marcadores/artefactos de teste em `clinica_dentaria` = **0** (amostras verificadas). |

## 7. Itens que ficaram por validar e porquê
| Item | Motivo |
|---|---|
| Pagamentos online reais (Eupago) end-to-end/sandbox com fornecedor | **Dependência externa:** credenciais/contrato, API e payload/assinatura de webhook específicos (sem simulação de credenciais/cobranças). |
| Entrega real de emails (confirmação, recuperação, notificações, contacto) via SMTP | **Transporte não integrado** no código e ausência de credenciais/servidor de teste configurado. |
| Auditoria visual/E2E completa (interações, JS/rede, modais/calendários, todas as resoluções) | **Ambiente:** browsers Playwright/Chromium não instalados (timeout). |
| Matriz exaustiva RBAC/IDOR/CSRF, DAST, concorrência real, todos os módulos/ações/filtros/relatórios | **Escopo/ambiente:** cobertura amostral realizada; exaustividade não concluída. |
| HTTPS/HSTS/cookies `Secure` e CSP afinada em produção, comportamento atrás de proxy | **Infraestrutura:** depende de configuração real de servidor/certificado/proxy. |
| Conformidade legal/RGPD/ERS/OMD, faturação/recibos fiscais, reconciliação bancária, backups operacionais/monitorização | **Fora de validação técnica automatizada completa / requisitos/integrações externas.** |
| Exaustividade absoluta de incompatibilidades esquema↔código em SQL dinâmico | **Limitação de análise estática:** P-01 confirmada e corrigida; outras não confirmadas. |

## 8. Credenciais, serviços externos ou decisões ainda necessários
- **Gateway de pagamentos (Eupago/fornecedor):** credenciais sandbox e produção, `gateway_pagamentos/ambiente/api_key/webhook_segredo`, especificação de assinatura/payload/idempotência, URL HTTPS de callback; testes com fornecedor antes de go-live.
- **SMTP:** host/porta/TLS, utilizador/palavra-passe, remetente/destinos válidos (SPF/DKIM/DMARC recomendado) e integração de transporte de envio; testes de entrega/erros/anti-duplicação.
- **Infra/produção:** HTTPS (certificado), configuração de proxy (se aplicável), variáveis de ambiente/segredos fora do código, utilizador BD dedicado (não `root`), `MODO_DEV` desativado, logs/backup/monitorização.
- **Decisões de negócio/requisitos:** correção do dado órfão da fotografia do dentista “ASD” na BD original (repor ficheiro ou limpar referência) com autorização; necessidade de campo NIF em pacientes, recibos/faturação fiscal, escopo de “registos clínicos/prontuário” estruturado, e confirmação de operações admin desejadas (eliminar utilizador, editar stock).
- **Validação funcional:** revisão humana em staging com dados simulados (fluxos clínicos, financeiros, permissões, conteúdos legais).

## 9. Resumo para revisão (antes de publicação, migrações à BD original ou uso com pacientes reais)
- **Acesso/BD:** confirmado `clinica_dentaria` em `localhost` (WAMP). **Backup de projeto (136 ficheiros) e backup BD restaurável (validado, 44 tabelas)** criados; trabalho/testes em cópias isoladas; **BD original intacta**.
- **Confirmado e corrigido:** erro SQL em `admin/financeiro.php` (coluna `nif` inexistente → `numero_utente_sns`) e falhas de anti-abuso/IP em `recuperar-password.php`; mitigação de avatar ausente e reforço de headers (CSP/HSTS condicional) já aplicados; `session_regenerate_id` no login confirmado. `php -l` 0 erros.
- **Pronto com evidência (núcleo):** marcações/alterações/cancelamentos e integridade de slots/estados, lógica financeira interna, controlos base de autenticação/sessão/CSRF/RBAC amostrados e consistência de dados nos cenários testados.
- **NÃO pronto para “100% produção”:** **pagamentos online reais** e **emails transacionais** dependem de integrações/credenciais externas e validação em staging; auditoria visual/E2E e segurança exaustiva não concluídas; referência de imagem órfã persiste na BD original (apenas tratamento no render).
- **Não executar sem autorização explícita:** aplicação de migrações/alterações à BD original, publicação, ou uso com pacientes reais/dados reais. Recomenda-se staging com HTTPS, sandbox gateway, SMTP de teste, correção do dado órfão aprovada e validação humana dos percursos críticos.
