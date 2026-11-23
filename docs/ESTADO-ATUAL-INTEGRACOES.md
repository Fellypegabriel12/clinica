# Estado técnico atual — integrações e migração

Levantamento feito em 23/11/2026 sobre o código existente. Este documento descreve o que foi observado no repositório; não confirma integrações externas nem capacidades do software que a clínica venha a utilizar.

## 1. Pagamentos: o que existe

- Existe a interface PHP `GatewayPagamento`, mas atualmente só define `nome()`, `iniciarPagamento()` e `validarWebhook()`.
- Existe uma implementação Stripe, com Checkout/PaymentIntent, Customer, Customer Session, SetupIntent, listagem e remoção de cartões.
- Existe `GatewayEupagoScaffold`, mas não inicia cobranças: devolve explicitamente uma mensagem de integração pendente.
- `gateway_obter()` seleciona o fornecedor através da configuração global `gateway_pagamentos`. O sistema não suporta ainda várias contas/prestadores ativos em simultâneo nem seleção por clínica/unidade.
- `pagamento-webhook.php` escolhe o fornecedor global configurado para validar o webhook. Depois de mudar o fornecedor ativo, um evento tardio do fornecedor anterior pode deixar de ser validado. A próxima arquitetura tem de encaminhar cada webhook para o adaptador correto sem confiar numa query string controlada pelo visitante.
- A tabela `pagamentos` já guarda `gateway`, `prestador`, `referencia_transacao` e `idempotency_key`. É uma base útil para preservar a origem de cada transação e permitir que pagamentos antigos continuem associados ao fornecedor que os criou.
- `admin/configuracoes.php` permite escolher um gateway e guardar credenciais de Stripe/Eupago na tabela de configurações. Os campos de segredo não são apresentados em claro, mas guardar segredos na base de dados sem uma camada de encriptação própria não deve ser a solução final.
- O identificador `stripe_customer_id` está diretamente na tabela `utilizadores`. É específico da Stripe e não representa uma associação genérica a qualquer prestador.
- A disponibilidade online atual está essencialmente condicionada à configuração Stripe. A Eupago é reconhecida mas continua em scaffold.

## 2. Dados locais relevantes para uma migração

A base de dados atual separa conceitos que não devem ser confundidos:

- `utilizadores`: contas de acesso, email, contacto, palavra-passe cifrada e papel (`paciente`, `rececao`, `dentista`, `admin`).
- `pacientes`: ficha administrativa do paciente, podendo estar ligada a uma conta de utilizador.
- `dentistas`: informação profissional associada a uma conta de utilizador.
- `marcacoes`, `consultas` e tabelas de tratamentos: relações operacionais e clínicas.
- `pagamentos`, `pagamentos_reembolsos` e `pagamentos_gateway_eventos`: histórico financeiro e eventos externos.
- Outras tabelas guardam conteúdos, stock, seguros, benefícios, documentos e operações administrativas.

Uma migração não pode importar apenas nomes e emails. Tem de preservar identificadores de origem, relações, estados, datas, documentos e histórico, respeitando a estrutura e os requisitos de acesso aos dados clínicos.

## 3. Limites do levantamento

Ainda não foi identificado nem disponibilizado:

- O nome e a versão do software atualmente utilizado pela clínica.
- O fornecedor, contrato, APIs, documentação ou contacto técnico desse software.
- O motor e versão da base de dados de origem, se existir acesso direto.
- Um backup anonimizado ou exportação de exemplo.
- O fornecedor de pagamentos, terminal físico, adquirente, métodos contratados e documentação de integração.
- O programa de faturação/contabilidade e o modo como comunica com pagamentos.
- Os formatos disponíveis para exportar pacientes, profissionais, consultas, imagens, documentos e histórico.

Sem estes elementos, qualquer conector específico ou promessa de importação integral seria especulação.

## 4. Decisões técnicas recomendadas

1. Separar o registo interno do pagamento da ligação ao prestador externo.
2. Guardar o fornecedor e a conta de origem em cada pagamento; não inferir o fornecedor atual a partir de uma configuração global.
3. Criar um catálogo de adaptadores com capacidades explícitas: criar pagamento, consultar estado, reembolsar, validar webhook, guardar método de pagamento e gerir o destino de recebimentos. Cada capacidade é opcional e tem de ser confirmada por fornecedor.
4. Encaminhar webhooks por endpoints/segredos específicos do fornecedor e validar sempre a assinatura. Manter idempotência e aceitar eventos atrasados de integrações antigas enquanto existirem transações associadas.
5. Guardar tokens e identificadores externos, nunca PAN/CVC. Os cartões guardados são específicos do prestador e não são transferíveis automaticamente.
6. Manter segredos fora do repositório e migrar gradualmente para um cofre/variáveis de ambiente ou armazenamento cifrado com chave fora da base de dados.
7. Construir migração de dados por etapas: origem imutável, área de staging, mapeamento, pré-visualização, importação idempotente, relatório de erros e reconciliação.
8. Integrar faturação e contabilidade quando possível, em vez de presumir que o módulo de pagamentos substitui um programa certificado ou contratado.

## 5. Estado de implementação

Este documento é um levantamento inicial e uma especificação de direção. Não foi acrescentado qualquer adaptador novo, não foram alterados registos financeiros e não foi importado qualquer dado de pacientes. A integração real depende de identificar os sistemas e obter documentação/credenciais de teste fornecidas pela empresa.
