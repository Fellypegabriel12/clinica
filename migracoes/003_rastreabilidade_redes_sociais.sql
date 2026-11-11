-- Migração 003: rastreabilidade (auditoria reforçada) + chaves de redes sociais
-- Aditiva. Executar após backup. Compatível com o estado aplicado no ambiente em 2026-11-09.

ALTER TABLE registo_admin ADD COLUMN motivo varchar(255) NULL;
ALTER TABLE registo_admin ADD COLUMN antes_json mediumtext NULL;
ALTER TABLE registo_admin ADD COLUMN depois_json mediumtext NULL;
ALTER TABLE registo_admin ADD COLUMN user_agent varchar(255) NULL;

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
