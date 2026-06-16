-- PENA ERP manual SQL: AP/AR invoice cancel fields
-- Safe to run multiple times in phpMyAdmin.

SET @db_name := DATABASE();

SET @table_name := 'purchase_invoices';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='cancelled_at');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_invoices` ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL AFTER `posted_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='cancelled_by');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_invoices` ADD COLUMN `cancelled_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `cancelled_at`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='cancel_reason');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_invoices` ADD COLUMN `cancel_reason` TEXT NULL AFTER `cancelled_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_gl_entry_id');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_invoices` ADD COLUMN `reversal_gl_entry_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gl_entry_id`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'sales_invoices';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='cancelled_at');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `sales_invoices` ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL AFTER `posted_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='cancelled_by');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `sales_invoices` ADD COLUMN `cancelled_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `cancelled_at`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='cancel_reason');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `sales_invoices` ADD COLUMN `cancel_reason` TEXT NULL AFTER `cancelled_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_gl_entry_id');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `sales_invoices` ADD COLUMN `reversal_gl_entry_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gl_entry_id`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
