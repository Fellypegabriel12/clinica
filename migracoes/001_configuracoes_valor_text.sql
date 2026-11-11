-- Migração segura (não destrutiva): aumenta o tamanho de configuracoes.valor.
-- Execute no phpMyAdmin ou cliente MySQL após cópia de segurança da BD.
-- Verifique dependências antes de executar em produção.

ALTER TABLE `configuracoes`
  MODIFY `valor` TEXT COLLATE utf8mb4_unicode_ci NOT NULL;

-- Opcional: chaves usadas pelo sistema (ajuste conforme necessário)
-- INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
-- ('max_tentativas_login', '5', 'Máximo de tentativas de login por email'),
-- ('max_tentativas_ip', '20', 'Máximo de tentativas de login por IP'),
-- ('bloqueio_login_min', '15', 'Minutos de bloqueio após tentativas falhadas');
