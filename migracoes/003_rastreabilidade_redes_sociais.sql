-- Migração 003: rastreabilidade (auditoria reforçada) + chaves de redes sociais
-- Aditiva. Executar após backup. IDEMPOTENTE: pode correr múltiplas vezes sem erro.

SET @db := DATABASE();

-- Colunas de rastreabilidade (só adiciona se não existirem)
SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE registo_admin ADD COLUMN motivo VARCHAR(255) NULL",
  "SELECT 'registo_admin.motivo exists'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='registo_admin' AND COLUMN_NAME='motivo');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE registo_admin ADD COLUMN antes_json MEDIUMTEXT NULL",
  "SELECT 'registo_admin.antes_json exists'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='registo_admin' AND COLUMN_NAME='antes_json');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE registo_admin ADD COLUMN depois_json MEDIUMTEXT NULL",
  "SELECT 'registo_admin.depois_json exists'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='registo_admin' AND COLUMN_NAME='depois_json');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE registo_admin ADD COLUMN user_agent VARCHAR(255) NULL",
  "SELECT 'registo_admin.user_agent exists'") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='registo_admin' AND COLUMN_NAME='user_agent');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Configurações de redes sociais (INSERT IGNORE já é idempotente)
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
('telegram_url','','URL do Telegram (canal/grupo/perfil).'),
('youtube_url','','URL do YouTube.'),
('linkedin_url','','URL do LinkedIn.'),
('whatsapp_url','','Link WhatsApp opcional (se vazio, usa número).'),
('rede_instagram_ativa','1','Mostrar Instagram (1=sim,0=não).'),
('rede_facebook_ativa','1','Mostrar Facebook (1=sim,0=não).'),
('rede_tiktok_ativa','1','Mostrar TikTok (1=sim,0=não).'),
('rede_whatsapp_ativa','1','Mostrar WhatsApp nas redes sociais (1=sim,0=não).'),
('rede_telegram_ativa','1','Mostrar Telegram (1=sim,0=não).'),
('rede_youtube_ativa','1','Mostrar YouTube (1=sim,0=não).'),
('rede_linkedin_ativa','1','Mostrar LinkedIn (1=sim,0=não).');