-- Preserve source indexes that were not represented by table constraints.
ALTER TABLE `sessions` ADD INDEX `sessions_user_id_index` (`user_id`);
ALTER TABLE `sessions` ADD INDEX `sessions_last_activity_index` (`last_activity`);
ALTER TABLE `orders` ADD INDEX `orders_status_index` (`status`);
CREATE UNIQUE INDEX `policies_company_terms_unique` ON `policies`
  ((CASE WHEN `type` = 'logistics_company_terms' THEN `company_name` ELSE NULL END));
