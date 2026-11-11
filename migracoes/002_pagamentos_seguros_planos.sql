-- Migração 002: pagamentos, seguros e planos dentários (aditiva, preserva dados)
-- Rever antes de executar em produção. Fazer backup da BD.

-- Pagamentos: metadados operacionais e gateway
SET @db := DATABASE();

-- metodo_detalhe (dinheiro, cartao_debito, cartao_credito, mbway_presencial, transferencia, outro)
SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN metodo_detalhe VARCHAR(40) NULL DEFAULT NULL AFTER metodo",
  "SELECT 'pagamentos.metodo_detalhe exists'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='metodo_detalhe');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN consulta_id INT UNSIGNED NULL DEFAULT NULL AFTER marcacao_id",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='consulta_id');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN registado_por INT UNSIGNED NULL DEFAULT NULL",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='registado_por');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN data_pagamento DATETIME NULL DEFAULT NULL",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='data_pagamento');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN observacoes VARCHAR(255) NULL DEFAULT NULL",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='observacoes');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN gateway VARCHAR(40) NULL DEFAULT NULL",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='gateway');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN gateway_metodo VARCHAR(40) NULL DEFAULT NULL",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='gateway_metodo');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN idempotency_key VARCHAR(120) NULL DEFAULT NULL",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='idempotency_key');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN falhado_em DATETIME NULL DEFAULT NULL",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='falhado_em');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN cancelado_em DATETIME NULL DEFAULT NULL",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='cancelado_em');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Estados: adicionar novos mantendo compatibilidade
ALTER TABLE pagamentos MODIFY COLUMN estado ENUM('pendente','em_processamento','pago','parcialmente_pago','recusado','falhado','cancelado','reembolsado','parcialmente_reembolsado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente';

-- Métodos: adicionar categorias online futuras mantendo compatibilidade
ALTER TABLE pagamentos MODIFY COLUMN metodo ENUM('eletronico_antecipado','presencial','sinal','mbway','multibanco','cartao_online','outro') COLLATE utf8mb4_unicode_ci NOT NULL;

-- Índices/constraints idempotentes
SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD KEY idx_pagamentos_consulta (consulta_id)",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND INDEX_NAME='idx_pagamentos_consulta');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD KEY idx_pagamentos_registado_por (registado_por)",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND INDEX_NAME='idx_pagamentos_registado_por');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD UNIQUE KEY uq_pagamentos_idempotency (gateway,idempotency_key)",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND INDEX_NAME='uq_pagamentos_idempotency');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- FKs opcionais (podem falhar se dados inconsistentes; ignorar manualmente se necessário)
-- ALTER TABLE pagamentos ADD CONSTRAINT fk_pagamentos_consulta FOREIGN KEY (consulta_id) REFERENCES consultas(id) ON DELETE SET NULL;
-- ALTER TABLE pagamentos ADD CONSTRAINT fk_pagamentos_registado_por FOREIGN KEY (registado_por) REFERENCES utilizadores(id) ON DELETE SET NULL;

-- Planos: condições e regras por tratamento
SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE planos_beneficio ADD COLUMN condicoes TEXT NULL DEFAULT NULL AFTER descricao",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='planos_beneficio' AND COLUMN_NAME='condicoes');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE planos_tratamentos ADD COLUMN preco_convencionado DECIMAL(10,2) NULL DEFAULT NULL AFTER valor",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='planos_tratamentos' AND COLUMN_NAME='preco_convencionado');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE planos_tratamentos ADD COLUMN valor_max_comparticipado DECIMAL(10,2) NULL DEFAULT NULL AFTER preco_convencionado",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='planos_tratamentos' AND COLUMN_NAME='valor_max_comparticipado');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE planos_tratamentos ADD COLUMN percentagem_comparticipacao DECIMAL(5,2) NULL DEFAULT NULL AFTER valor_max_comparticipado",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='planos_tratamentos' AND COLUMN_NAME='percentagem_comparticipacao');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE planos_tratamentos ADD COLUMN condicoes_especificas VARCHAR(255) NULL DEFAULT NULL AFTER percentagem_comparticipacao",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='planos_tratamentos' AND COLUMN_NAME='condicoes_especificas');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE planos_tratamentos ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER condicoes_especificas",
  "SELECT 'ok'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='planos_tratamentos' AND COLUMN_NAME='ativo');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Preencher data_pagamento para registos pagos antigos (compatibilidade)
UPDATE pagamentos SET data_pagamento = COALESCE(pago_em, criado_em) WHERE estado IN ('pago','reembolsado','parcialmente_reembolsado') AND data_pagamento IS NULL;