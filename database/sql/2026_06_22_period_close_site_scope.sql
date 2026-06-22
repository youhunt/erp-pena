-- Make period close unique per company/site/module/period.
-- site_scope_id = 0 represents an All Sites/company-wide close.
SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'period_closes' AND COLUMN_NAME = 'site_scope_id'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE `period_closes` ADD COLUMN `site_scope_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `site_id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE period_closes SET site_scope_id = COALESCE(site_id, 0);

-- This query must return no rows before adding the unique index.
SELECT company_id, site_scope_id, module_code, period, COUNT(*) AS total
FROM period_closes
GROUP BY company_id, site_scope_id, module_code, period
HAVING COUNT(*) > 1;

SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'period_closes'
      AND INDEX_NAME = 'uq_period_closes_scope_module_period'
);
SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE `period_closes` ADD UNIQUE INDEX `uq_period_closes_scope_module_period` (`company_id`, `site_scope_id`, `module_code`, `period`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @old_idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'period_closes'
      AND INDEX_NAME = 'uq_period_closes_company_module_period'
);
SET @sql := IF(
    @old_idx_exists > 0,
    'ALTER TABLE `period_closes` DROP INDEX `uq_period_closes_company_module_period`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verification:
-- SHOW INDEX FROM period_closes;
