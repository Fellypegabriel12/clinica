-- Migração 006: dados mascarados do cartão para o checkout Stripe.
-- Aditiva e idempotente. Não guarda número completo, validade ou CVC.

SET @db := DATABASE();

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN cartao_marca VARCHAR(20) NULL DEFAULT NULL AFTER referencia_transacao",
  "SELECT 'pagamentos.cartao_marca exists'")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='cartao_marca');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE pagamentos ADD COLUMN cartao_ultimos4 CHAR(4) NULL DEFAULT NULL AFTER cartao_marca",
  "SELECT 'pagamentos.cartao_ultimos4 exists'")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pagamentos' AND COLUMN_NAME='cartao_ultimos4');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
