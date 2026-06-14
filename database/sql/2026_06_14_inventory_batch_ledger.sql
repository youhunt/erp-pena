-- Manual MySQL migration for batch-aware inventory ledger
-- Project: PENA ERP
-- Use this file when hosting/cPanel cannot run: php spark migrate --all

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'inventory_stock_balances' AND COLUMN_NAME = 'batch_no');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory_stock_balances` ADD COLUMN `batch_no` VARCHAR(80) NOT NULL DEFAULT '''' AFTER `item_code`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `inventory_stock_balances` SET `batch_no` = '' WHERE `batch_no` IS NULL;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'inventory_stock_balances' AND INDEX_NAME = 'uq_inventory_stock_scope_item');
SET @sql := IF(@idx_exists > 0, 'ALTER TABLE `inventory_stock_balances` DROP INDEX `uq_inventory_stock_scope_item`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'inventory_stock_balances' AND INDEX_NAME = 'uq_inventory_stock_scope_item_batch');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE `inventory_stock_balances` ADD UNIQUE KEY `uq_inventory_stock_scope_item_batch` (`company_id`, `site_id`, `warehouse_id`, `location_id`, `item_code`, `batch_no`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'inventory_stock_movements' AND COLUMN_NAME = 'batch_no');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory_stock_movements` ADD COLUMN `batch_no` VARCHAR(80) NOT NULL DEFAULT '''' AFTER `item_code`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `inventory_stock_movements` SET `batch_no` = '' WHERE `batch_no` IS NULL;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'inventory_stock_movements' AND INDEX_NAME = 'idx_inventory_stock_movements_item_batch');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE `inventory_stock_movements` ADD KEY `idx_inventory_stock_movements_item_batch` (`company_id`, `item_code`, `batch_no`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'inventory_transfer_lines' AND COLUMN_NAME = 'batch_no');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory_transfer_lines` ADD COLUMN `batch_no` VARCHAR(80) NOT NULL DEFAULT '''' AFTER `item_code`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `inventory_transfer_lines` SET `batch_no` = '' WHERE `batch_no` IS NULL;
