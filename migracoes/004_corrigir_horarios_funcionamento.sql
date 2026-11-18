-- Migração 004: corrigir horários da clínica fora da grelha de 30 minutos.
-- Aplicada em 2026-11-18 após backup de horarios_funcionamento.
-- Efeito: segunda-feira passa de 09:04–14:00 / 15:02–18:00 para
-- 09:00–14:00 / 15:00–18:00, alinhando os slots com bloco_agenda_min=30.
-- Idempotente: os WHERE só encontram os valores antigos uma vez.

UPDATE `horarios_funcionamento`
   SET `hora_inicio` = '09:00:00'
 WHERE `dia_semana` = 1 AND `hora_inicio` = '09:04:00';

UPDATE `horarios_funcionamento`
   SET `hora_inicio` = '15:00:00'
 WHERE `dia_semana` = 1 AND `hora_inicio` = '15:02:00';
