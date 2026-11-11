# Estrutura do projeto (clínica dentária)

## Visão geral
Projeto PHP/MySQL (WAMP) para gestão de clínica dentária. A organização privilegia **estabilidade e compatibilidade**: os *entry points* públicos e administrativos mantêm os URLs/ficheiros existentes; os componentes partilhados em `includes/` foram reorganizados internamente por responsabilidade, com **wrappers de compatibilidade** nos caminhos antigos.

## Árvore real (`tree /f /a`)
Ver secção “Árvore completa” no relatório final desta reorganização (ou executar `tree /f /a` na raiz do projeto).

## Organização por categorias (lógica)

### Raiz (páginas/entry points)
- **Páginas principais:** `index.php`, `sobre.php`, `contactos.php`, `dentistas.php`, `tratamentos.php`, `blog.php`, `artigo.php`, `avaliar.php`, `lista-espera.php`, `trabalha-connosco.php`, `seguros-publico.php`, `seguros.php`.
- **Autenticação e área pessoal:** `login.php`, `logout.php`, `registo.php`, `recuperar-password.php`, `repor-password.php`, `area-pessoal.php`, `perfil.php`, `notificacoes.php`, `minhas-marcacoes.php`, `meus-pagamentos.php`.
- **Marcações/consultas:** `marcar.php`, `marcar-calendario.php`, `marcar-pacientes.php`, `marcar-tratamentos.php`, `marcar-resumo.php`, `marcar-confirmacao.php`, `alterar-consulta.php`.
- **Pagamentos:** `marcar-pagamento.php`, `marcar-pagamento-demo.php` (demo/MODO_DEV), `pagamento-retorno.php`, `pagamento-webhook.php`.
- **Políticas/preferências:** `aviso-legal.php`, `privacidade.php`, `cookies.php`, `guardar-cookies.php`, `guardar-tema.php`.

### `admin/`
Painel administrativo (utilizadores, agenda/marcações/horários, dentistas/especialidades, tratamentos, financeiro/pagamentos, stock, conteúdos do site, seguros/parceiros/benefícios, contactos/pedidos/candidaturas/CV, relatórios, configurações, **auditoria/rastreabilidade**). RBAC aplicado por página (`exigir_login`).

### `includes/` (compatibilidade preservada)
Os ficheiros em `includes/*.php` permanecem como **wrappers** (`require_once` para a implementação movida), evitando alterações em todos os consumidores.
- `includes/core/`: base/configuração/conteúdo/consentimento (`config.php`, `conteudo.php`, `cookies.php`).
- `includes/servicos/`: lógica de negócio/integrações internas (`gateway.php`, `pagamentos.php`, `marcacao.php`, `gestao.php`, `stock.php`, `conta.php`, `consulta-preparacao.php`).
- `includes/ui/`: componentes de apresentação (`cabecalho.php`, `rodape.php`, `hero.php`, `banner-cookies.php`, `numeros.php`, `parceiros.php`, `redes-sociais.php`, `testemunhos.php`, `whatsapp.php`).

### `css/`, `js/`
Recursos front-end (`estilo.css`, `animacoes.js`, `tema.js`).

### `migracoes/`
Scripts SQL **aditivos** (executar manualmente conforme necessário; `.sql` bloqueado por `.htaccess`).

### `uploads/`
Conteúdo enviado (hero, galeria, dentistas, CV, identidade). Protegido por `.htaccess` (execução/descarregamento conforme regras existentes; `cv/` tem proteção própria). Não armazenar segredos.

### `docs/`
Documentação interna do projeto. Acesso web negado por `docs/.htaccess`.

### `logs/`
Reservado para registos locais. Acesso web negado por `logs/.htaccess` (conteúdo ignorado por `.gitignore`).

## Proteções relevantes
- Raiz `.htaccess`: bloqueia extensões sensíveis (`sql`, `db`, `bak`, `log`, `env`, etc.), ficheiros de controlo/configuração sensíveis (incl. `RELATORIO-AUDITORIA.md`) e `Options -Indexes`.
- `uploads/.htaccess`, `uploads/cv/.htaccess`, `docs/.htaccess`, `logs/.htaccess`: negação de acesso/execução conforme contexto.

## Decisão de estabilidade (URLs)
Não foram movidos os PHP de entrada da raiz/admin para novas pastas de URL, para evitar quebra de links, formulários, `redirecionar()`, `<base href>`/paths, AJAX e **webhooks/retornos de pagamento**. A reorganização física segura foi aplicada onde tem menor risco (internals de `includes/`) com compatibilidade total.
