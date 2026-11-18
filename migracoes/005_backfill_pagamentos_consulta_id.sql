-- Migração 005: atribuir pagamentos à consulta quando a marcação tem
-- exatamente uma consulta. Pagamentos em marcações partilhadas (duas ou
-- mais consultas) ficam com consulta_id NULL e são geridos ao nível da
-- marcação; não são imputados por inferência.
-- Aplicada em 2026-11-18 após backup de pagamentos.
-- Idempotente: só atualiza registos ainda sem consulta_id.

UPDATE `pagamentos` p
  JOIN (
        SELECT `marcacao_id`, MIN(`id`) AS `consulta_id`
          FROM `consultas`
         GROUP BY `marcacao_id`
        HAVING COUNT(*) = 1
       ) x ON x.`marcacao_id` = p.`marcacao_id`
   SET p.`consulta_id` = x.`consulta_id`
 WHERE p.`consulta_id` IS NULL;
