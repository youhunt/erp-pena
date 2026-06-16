-- Manual MySQL upgrade for Inventory Movement Document Reversal
-- Project: PENA ERP
-- Run this after 2026_06_16_inventory_core_upgrade.sql if the database was already created before reversal workflow.
-- Safe to run multiple times.

SET @db_name := DATABASE();

/* inventory_movement_documents reversal metadata */
SET @table_name := 'inventory_movement_documents';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversed_at');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `inventory_movement_documents` ADD COLUMN `reversed_at` DATETIME NULL DEFAULT NULL AFTER `posted_by`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversed_by');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `inventory_movement_documents` ADD COLUMN `reversed_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `reversed_at`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_reason');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `inventory_movement_documents` ADD COLUMN `reversal_reason` TEXT NULL AFTER `reversed_by`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_document_id');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `inventory_movement_documents` ADD COLUMN `reversal_document_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `reversal_reason`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND INDEX_NAME='idx_inventory_movement_document_reversal');
SET @sql := IF(@table_exists=1 AND @idx_exists=0, 'ALTER TABLE `inventory_movement_documents` ADD KEY `idx_inventory_movement_document_reversal` (`reversal_document_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* inventory_movement_document_lines reversal movement link */
SET @table_name := 'inventory_movement_document_lines';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_movement_id');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `inventory_movement_document_lines` ADD COLUMN `reversal_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `stock_movement_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND INDEX_NAME='idx_inventory_movement_doc_line_reversal');
SET @sql := IF(@table_exists=1 AND @idx_exists=0, 'ALTER TABLE `inventory_movement_document_lines` ADD KEY `idx_inventory_movement_doc_line_reversal` (`reversal_movement_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verification
DESCRIBE `inventory_movement_documents`;
DESCRIBE `inventory_movement_document_lines`;
