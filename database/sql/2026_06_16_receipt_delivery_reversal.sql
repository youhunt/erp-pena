-- PENA ERP manual SQL: receipt and delivery reversal columns
-- Safe to run multiple times in phpMyAdmin.

SET @db_name := DATABASE();

SET @table_name := 'purchase_receipts';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversed_at');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_receipts` ADD COLUMN `reversed_at` DATETIME NULL DEFAULT NULL AFTER `posted_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversed_by');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_receipts` ADD COLUMN `reversed_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `reversed_at`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_reason');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_receipts` ADD COLUMN `reversal_reason` TEXT NULL AFTER `reversed_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'sales_deliveries';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversed_at');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `sales_deliveries` ADD COLUMN `reversed_at` DATETIME NULL DEFAULT NULL AFTER `posted_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversed_by');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `sales_deliveries` ADD COLUMN `reversed_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `reversed_at`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_reason');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `sales_deliveries` ADD COLUMN `reversal_reason` TEXT NULL AFTER `reversed_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'purchase_receipt_lines';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='stock_movement_id');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_receipt_lines` ADD COLUMN `stock_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `purchase_order_line_id`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_movement_id');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_receipt_lines` ADD COLUMN `reversal_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `stock_movement_id`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'sales_delivery_lines';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='stock_movement_id');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `sales_delivery_lines` ADD COLUMN `stock_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `sales_order_line_id`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_movement_id');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `sales_delivery_lines` ADD COLUMN `reversal_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `stock_movement_id`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
