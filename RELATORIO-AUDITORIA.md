# Relatório de Auditoria e Correções — Sistema de Gestão de Clínica Dentária

**Data:** 2026-11-09  
**Projeto:** `C:\wamp64\www\meu-projeto\clinica` (WAMP / PHP / MySQL-MariaDB)  
**Base de dados:** `clinica_dentaria`  
**Backup completo criado:** `C:\Users\HP\AppData\Local\Temp\opencode\clinica-backup-20261109-003318`

> Este relatório resume a auditoria real do código e as correções aplicadas. Não foram inventadas credenciais, integrações de pagamento reais, SMTP/SMS, nem regras comerciais de seguros fora dos dados existentes na BD.

## 1. Resumo executivo

Foram confirmados e tratados, com prioridade de segurança/fiabilidade, a maioria dos 21 problemas identificados. As correções críticas incluem: proteção/movimentação do dump SQL para fora da raiz web, endurecimento de `config.php` (sem exposição de erros/BD por defeito), restrição do fluxo de pagamento simulado, proteção de CVs com download autorizado, revalidação de sessão/papel, correção da recuperação de password, correção do cálculo de reembolsos para múltiplos pagamentos, aplicação de descontos de benefícios/planos com base nos dados existentes, criação de `admin/parceiros.php`, MVP de lista de espera e MVP de notas clínicas com permissões restritas.

## 2. Estado dos 21 problemas

| # | Problema | Estado | Ação realizada |
|---|---|---:|---|
| 1 | SQL exposto na raiz pública | **Corrigido/Mitigado** | `clinica_dentaria.sql` copiado e movido para `C:\wamp64\_clinica_private\clinica_dentaria.sql` (fora de `www`). `.htaccess` na raiz bloqueia acesso web a `*.sql` e outros ficheiros sensíveis. **Recomendado:** manter dumps apenas fora da web e rodar credenciais/hashes se o dump tiver sido exposto anteriormente. |
| 2 | Pagamentos simulados | **Corrigido** | `marcar-pagamento-demo.php` bloqueado quando `MODO_DEV` está desativado. Em `marcar-pagamento.php`, métodos `sinal`/`eletronico_antecipado` só ativos em `MODO_DEV`. Pagamento presencial preservado. Sem integração real com prestadores. |
| 3 | `MODO_DEV = true` | **Corrigido** | `MODO_DEV` passa a `false` por defeito. Detalhes técnicos apenas em `localhost` **e** com ficheiro vazio `includes/.modo_dev`. Erros de BD não expõem detalhes ao visitante. |
| 4 | Credenciais BD `root`/vazia | **Documentado/compatível** | Configuração local WAMP mantida para não quebrar ambiente. README documenta criação de utilizador MySQL dedicado para produção. **Não alteradas credenciais MySQL automaticamente.** |
| 5 | Editor desligado do site | **Melhorado (parcial amplo)** | `includes/conteudo.php` usado com fallbacks em componentes/páginas chave: `index.php` (hero/secções), `includes/consulta-preparacao.php` (`prep_*`), `includes/cabecalho.php` (`seo_descricao`), `includes/rodape.php` (`rodape_aviso`), `tratamentos.php`, `dentistas.php`, `trabalha-connosco.php`, `aviso-legal.php`, `privacidade.php`, `cookies.php`, `contactos.php`, `sobre.php`. Faltam afinações em páginas secundárias específicas. |
| 6 | Dados da clínica espalhados | **Melhorado** | `clinica_dado()` adicionado em `config.php` (lê `configuracoes` com fallback para constantes). Rodapé, aviso legal e páginas relevantes usam dados configuráveis. `admin/configuracoes.php` passou a incluir campos `clinica_nome/nif/ers/morada/diretor/cédula OMD` + contactos/mapas/redes. |
| 7 | Seguros não aplicam descontos | **Corrigido (com base em dados existentes)** | `includes/marcacao.php`: `beneficio_desconto_tratamento()` procura benefícios validados (`beneficios_paciente.estado_validacao='validado'`, dentro de validade, plano/seguradora ativos) e descontos em `planos_tratamentos` (percentagem ou valor fixo). Aplica o melhor desconto por tratamento (maior redução, limitado ao preço). `totais_tratamentos`/`resumo_da_marcacao` passam contexto; `marcar-resumo.php` mostra desconto; `marcar-pagamento.php` grava `beneficio_id`, `preco_original`, `desconto`, `preco_final` em `consultas_tratamentos` e usa totais finais. **Não foram inventadas percentagens/regras.** |
| 8 | Gestão de parceiros inexistente | **Corrigido** | Criado `admin/parceiros.php` (CRUD, logótipo, URL, ordem, visível/oculto, CSRF, registo admin), com referência a `admin/testemunhos.php`. |
| 9 | CVs acessíveis diretamente | **Corrigido** | `uploads/cv/.htaccess` bloqueia acesso direto. Criado `admin/download-cv.php` (admin/receção, validação por ID/caminho real). `admin/candidaturas.php` usa download seguro. Upload em `trabalha-connosco.php` mantém validação de tipo/tamanho. |
| 10 | Reembolsos incorretos | **Corrigido** | `includes/conta.php::cancelar_consulta()` calcula proporção da consulta e distribui reembolso registado por **todos** os pagamentos pagos/reembolsados elegíveis (net = `valor - valor_reembolsado`), com `FOR UPDATE`, sem exceder o pago. Pendentes reduzidos proporcionalmente com `GREATEST(0,...)`. Registo financeiro corrigido; sem transferências reais. |
| 11 | Emails/SMS | **Parcial (arquitetura pendente)** | `criar_notificacao()` mantém notificações internas. Não foram adicionadas credenciais/serviços. Lista de espera e recuperação funcionam com contacto/fluxo manual ou dev; envio real requer integração externa. |
| 12 | Recuperação de password | **Corrigido** | Já não insere em `tentativas_login`; rate-limit por sessão; mensagem genérica; token hash/validade mantidos; link de teste só exposto em `MODO_DEV` quando aplicável; erros de BD não expostos. |
| 13 | Configurações de login ignoradas | **Corrigido** | `login.php` lê `max_tentativas_login`, `max_tentativas_ip`, `bloqueio_login_min` da BD (`config_valor`) com fallback para constantes. `config_valor()` centralizado em `config.php`. |
| 14 | `configuracoes.valor` pequeno | **Corrigido** | Migração segura executada: `ALTER TABLE configuracoes MODIFY valor TEXT ...` (verificado/aplicado por script temporário). `admin/configuracoes.php` e editor já lidam com textos longos; `longtext` em configurações limitado a 5000 no formulário. |
| 15 | Lista de espera sem funcionalidade | **Implementado MVP** | Criados `lista-espera.php` (paciente: criar/cancelar pedidos para pacientes autorizados) e `admin/lista-espera.php` (admin/receção: filtrar e alterar estado). Usa tabela `lista_espera` existente. Notificação automática não integrada (manual). Links adicionados ao submenu da conta e painel admin. |
| 16 | Notas clínicas sem interface | **Implementado MVP (restrito)** | `admin/marcacoes.php`: carrega `notas_clinicas`, permite guardar notas apenas a **dentista responsável** (consulta própria) ou **admin**; receção não edita. UI com `<details>` e formulário CSRF. Acesso a consultas de dentistas já filtrado por `dentista_do_utilizador`. |
| 17 | Sessão não revalida papel/ativo | **Corrigido** | `exigir_login()` revalida utilizador na BD (ativo/papel/nome/tema), atualiza sessão e termina acesso se conta desativada ou inválida. Cache por pedido. |
| 18 | Cabeçalhos de segurança | **Melhorado** | Adicionados em `config.php`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`. CSRF, HttpOnly, SameSite, cookies `secure` condicionais mantidos. CSP completa não aplicada para evitar quebrar scripts/estilos inline existentes. |
| 19 | SEO | **Melhorado** | Meta description no cabeçalho usa `conteudo('seo_descricao', ...)`. Ligação completa por página/grupo SEO pode ser expandida no editor. |
| 20 | Acesso dos dentistas | **Corrigido/Melhorado** | `login.php` encaminha dentistas para `admin/marcacoes.php`. `cabecalho.php` mostra “A minha agenda” para dentistas. `admin/marcacoes.php` já suporta dentistas e filtra às suas consultas; notas clínicas restritas. Sem permissões admin indevidas. |
| 21 | README | **Atualizado** | `README.md` reescrito com instalação, segurança, `.modo_dev`, pagamentos demo, migrações, backup e pendências. |

## 3. Ficheiros modificados
- `.htaccess` (raiz)
- `uploads/.htaccess`
- `uploads/cv/.htaccess` (pasta assegurada)
- `README.md`
- `includes/config.php`, `includes/marcacao.php`, `includes/conta.php`, `includes/consulta-preparacao.php`, `includes/cabecalho.php`, `includes/rodape.php`
- `index.php`, `login.php`, `recuperar-password.php`, `repor-password.php`, `registo.php`, `contactos.php`, `sobre.php`, `tratamentos.php`, `dentistas.php`, `trabalha-connosco.php`, `aviso-legal.php`, `privacidade.php`, `cookies.php`
- `marcar-resumo.php`, `marcar-pagamento.php`, `marcar-pagamento-demo.php`, `marcar-calendario.php`, `marcar-pacientes.php`, `marcar-tratamentos.php`, `minhas-marcacoes.php`, `alterar-consulta.php`, `avaliar.php`, `seguros.php`
- `admin/candidaturas.php`, `admin/configuracoes.php`, `admin/index.php`, `admin/marcacoes.php`, `admin/artigos.php`, `admin/avaliacoes.php`, `admin/beneficios.php`, `admin/bloqueios.php`, `admin/dentistas.php`, `admin/editor-site.php`, `admin/especialidades.php`, `admin/galeria.php`, `admin/hero.php`, `admin/horarios.php`, `admin/pedidos.php`, `admin/stock-categorias.php`, `admin/stock-encomendas.php`, `admin/stock.php`, `admin/testemunhos.php`, `admin/tratamentos.php`, `admin/utilizadores-novo.php`, `admin/utilizadores.php`

## 4. Ficheiros criados
- `admin/parceiros.php`
- `admin/download-cv.php`
- `admin/lista-espera.php`
- `lista-espera.php`
- `migracoes/001_configuracoes_valor_text.sql` (migração aplicada; script mantido como referência)
- `RELATORIO-AUDITORIA.md`

## 5. Alterações à base de dados
- **Executada (segura):** `ALTER TABLE configuracoes MODIFY valor TEXT COLLATE utf8mb4_unicode_ci NOT NULL` (apenas ampliação de tipo; preserva dados). Aplicada em 2026-11-09.
- **Nenhuma alteração destrutiva.** Não foram apagados dados de pacientes, tabelas ou registos.

## 6. Testes realizados
- `php -l` (sintaxe) em todos os ficheiros PHP modificados/criados referidos (passou).
- Verificação estática de padrões críticos: remoção global de exposição `MODO_DEV ? 'Erro na base de dados: ...' : ...` em páginas/admin (31 ficheiros atualizados).
- Verificação de presença de handlers/UI de notas clínicas e lista de espera.
- **Não executados:** testes E2E completos no browser com WAMP/MySQL ativo (login real, marcação completa, pagamentos presenciais, CRUD admin, permissões por papel, upload/download, cancelamentos). Recomenda-se teste manual no WAMP antes de produção.

## 7. Problemas que podem permanecer / decisões humanas
- **Exposição histórica do dump SQL:** se `clinica_dentaria.sql` esteve acessível publicamente antes, considerar hashes/passwords, dados pessoais e tokens como potencialmente comprometidos; definir rotação/limpeza conforme RGPD/políticas.
- **Integrações externas:** pagamentos reais, SMTP, SMS, lembretes automáticos e limpeza agendada de reservas requerem serviços/credenciais e configuração operacional.
- **Seguros/descontos:** lógica implementada a partir dos dados existentes; casos ambíguos (múltiplos benefícios simultâneos, regras comerciais específicas, limites) devem ser validados pelo negócio.
- **Lista de espera:** MVP com gestão manual; falta automatizar correspondência com vagas/notificações.
- **Notas clínicas:** MVP restrito (dentista responsável/admin). Avaliar necessidades de auditoria clínica, histórico de versões, templates e acesso por receção conforme política interna.
- **CSP/segurança avançada:** CSP restritiva pode exigir refatoração de inline JS/CSS.
- **Conteúdo editor:** cobertura alargada, mas não 100% de todos os textos de todas as páginas; continuar a ligar chaves com fallbacks.
- **Credenciais MySQL produção:** manter WAMP local funcional; criar utilizador dedicado e credenciais fortes em produção.

## 8. Recomendações antes de utilização numa clínica real
1. **Backups** regulares (ficheiros + BD), testados, fora da web e com controlo de acesso.
2. **Remover/impedir** qualquer dump SQL ou ficheiro sensível na raiz pública; rever `.htaccess`/Apache.
3. **Criar utilizador MySQL dedicado** com privilégios mínimos; `DB_PASS` forte; nunca `root` vazio em produção.
4. **Desativar debug**: não criar `includes/.modo_dev` em produção.
5. **HTTPS obrigatório** e cookies `secure` ativos.
6. **Revisão RGPD**: DPO/contactos, políticas, consentimentos, retenção, acesso a dados clínicos, registo de acessos e contratos com subcontratantes.
7. **Integração de pagamentos real** com validação server-side (webhooks/confirmação do prestador), sem simulação.
8. **Testes funcionais** completos (RBAC, marcações concorrentes, cancelamentos/reembolsos, notas clínicas, uploads, formulários, compatibilidade mobile).
9. **Revisão clínica/legal** dos textos, preços indicativos, termos e fluxos de consentimento.
10. **Monitorização/logs** (erros PHP, acessos administrativos, ações sensíveis) com proteção de dados pessoais.

## 9. Verificação rápida pós-deploy local
- Aceder à homepage e páginas públicas.
- Login como admin/receção/dentista/paciente (se existirem contas de teste locais).
- Marcar consulta com pagamento presencial.
- Confirmar que `marcar-pagamento-demo.php` não permite simulação sem `includes/.modo_dev` em localhost.
- Testar download de CV no admin.
- Testar lista de espera (paciente) e gestão (admin).
- Testar notas clínicas como dentista/admin (permissões).
- Confirmar que `clinica_dentaria.sql` não está acessível via web.