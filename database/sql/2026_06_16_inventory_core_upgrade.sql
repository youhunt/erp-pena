-- Manual MySQL upgrade for latest Inventory Core development
-- Project: PENA ERP
-- Purpose: run from phpMyAdmin/cPanel when php spark migrate --all is not available.
-- Safe to run multiple times. Uses INFORMATION_SCHEMA checks for ALTER TABLE operations.
-- Target: MySQL 5.7+/MariaDB 10.x

SET FOREIGN_KEY_CHECKS = 0;
SET @db_name := DATABASE();

/* =========================================================
   1) Inventory Transfer Document Header-Line
   ========================================================= */
CREATE TABLE IF NOT EXISTS `inventory_transfer_headers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) UNSIGNED NOT NULL,
  `site_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `transfer_no` VARCHAR(50) NOT NULL,
  `transfer_date` DATETIME NOT NULL,
  `from_warehouse_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `from_location_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `to_warehouse_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `to_location_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `notes` TEXT NULL,
  `submitted_at` DATETIME NULL DEFAULT NULL,
  `submitted_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `posted_at` DATETIME NULL DEFAULT NULL,
  `posted_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `cancelled_at` DATETIME NULL DEFAULT NULL,
  `cancelled_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `cancel_reason` TEXT NULL,
  `reversed_at` DATETIME NULL DEFAULT NULL,
  `reversed_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `reversal_reason` TEXT NULL,
  `created_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_transfer_no_company` (`company_id`, `transfer_no`),
  KEY `idx_inventory_transfer_tenant_date` (`company_id`, `site_id`, `transfer_date`),
  KEY `idx_inventory_transfer_status` (`status`),
  KEY `idx_inventory_transfer_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_transfer_lines` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `header_id` INT(11) UNSIGNED NOT NULL,
  `line_no` INT(11) NOT NULL DEFAULT 1,
  `item_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `item_code` VARCHAR(80) NOT NULL,
  `batch_no` VARCHAR(80) NOT NULL DEFAULT '',
  `item_name` VARCHAR(255) NULL DEFAULT NULL,
  `uom_code` VARCHAR(30) NOT NULL DEFAULT 'PCS',
  `qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `transfer_out_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `transfer_in_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `reversal_out_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `reversal_in_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_transfer_line_header` (`header_id`),
  KEY `idx_inventory_transfer_line_item` (`item_code`, `uom_code`),
  KEY `idx_inventory_transfer_line_item_batch` (`item_code`, `batch_no`),
  KEY `idx_inventory_transfer_line_out_movement` (`transfer_out_movement_id`),
  KEY `idx_inventory_transfer_line_in_movement` (`transfer_in_movement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `inventory_transfer_headers` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'draft';

/* Add missing workflow/reversal columns to existing transfer header tables */
SET @table_name := 'inventory_transfer_headers';
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='submitted_at');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_headers` ADD COLUMN `submitted_at` DATETIME NULL DEFAULT NULL AFTER `notes`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='submitted_by');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_headers` ADD COLUMN `submitted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `submitted_at`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='cancelled_at');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_headers` ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL AFTER `posted_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='cancelled_by');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_headers` ADD COLUMN `cancelled_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `cancelled_at`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='cancel_reason');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_headers` ADD COLUMN `cancel_reason` TEXT NULL AFTER `cancelled_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversed_at');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_headers` ADD COLUMN `reversed_at` DATETIME NULL DEFAULT NULL AFTER `cancel_reason`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversed_by');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_headers` ADD COLUMN `reversed_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `reversed_at`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_reason');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_headers` ADD COLUMN `reversal_reason` TEXT NULL AFTER `reversed_by`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* Add missing batch/reversal columns to existing transfer line tables */
SET @table_name := 'inventory_transfer_lines';
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='batch_no');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_lines` ADD COLUMN `batch_no` VARCHAR(80) NOT NULL DEFAULT '''' AFTER `item_code`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_out_movement_id');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_lines` ADD COLUMN `reversal_out_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `transfer_in_movement_id`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reversal_in_movement_id');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_transfer_lines` ADD COLUMN `reversal_in_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `reversal_out_movement_id`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* =========================================================
   2) Batch Master
   ========================================================= */
CREATE TABLE IF NOT EXISTS `batch_masters` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `site_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `item_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `item_code` VARCHAR(80) NOT NULL,
  `batch_no` VARCHAR(80) NOT NULL,
  `batch_name` VARCHAR(255) NULL DEFAULT NULL,
  `production_date` DATE NULL DEFAULT NULL,
  `expiry_date` DATE NULL DEFAULT NULL,
  `supplier_lot_no` VARCHAR(80) NULL DEFAULT NULL,
  `manufacturer_lot_no` VARCHAR(80) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch_master_scope_item_batch` (`company_id`, `site_id`, `item_code`, `batch_no`),
  KEY `idx_batch_master_company_site` (`company_id`, `site_id`),
  KEY `idx_batch_master_company_item` (`company_id`, `item_code`),
  KEY `idx_batch_master_expiry` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =========================================================
   3) Batch-aware Inventory Ledger
   ========================================================= */
SET @table_name := 'inventory_stock_balances';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='batch_no');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `inventory_stock_balances` ADD COLUMN `batch_no` VARCHAR(80) NOT NULL DEFAULT '''' AFTER `item_code`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists=1, 'UPDATE `inventory_stock_balances` SET `batch_no` = '''' WHERE `batch_no` IS NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='inventory_stock_balances' AND INDEX_NAME='uq_inventory_stock_scope_item');
SET @sql := IF(@idx_exists>0, 'ALTER TABLE `inventory_stock_balances` DROP INDEX `uq_inventory_stock_scope_item`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='inventory_stock_balances' AND INDEX_NAME='uq_inventory_stock_scope_item_batch');
SET @sql := IF(@table_exists=1 AND @idx_exists=0, 'ALTER TABLE `inventory_stock_balances` ADD UNIQUE KEY `uq_inventory_stock_scope_item_batch` (`company_id`, `site_id`, `warehouse_id`, `location_id`, `item_code`, `batch_no`)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inventory_stock_movements';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='batch_no');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `inventory_stock_movements` ADD COLUMN `batch_no` VARCHAR(80) NOT NULL DEFAULT '''' AFTER `item_code`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='reference_id');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `inventory_stock_movements` ADD COLUMN `reference_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `reference_type`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='inventory_stock_movements' AND INDEX_NAME='idx_inventory_stock_movements_item_batch');
SET @sql := IF(@table_exists=1 AND @idx_exists=0, 'ALTER TABLE `inventory_stock_movements` ADD KEY `idx_inventory_stock_movements_item_batch` (`company_id`, `item_code`, `batch_no`)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* =========================================================
   4) Inventory Movement Document Snapshots
   Used by Inventory In/Out and Stock Opname multi-line posting.
   ========================================================= */
CREATE TABLE IF NOT EXISTS `inventory_movement_documents` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) UNSIGNED NOT NULL,
  `site_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `document_no` VARCHAR(50) NOT NULL,
  `document_date` DATETIME NOT NULL,
  `document_type` VARCHAR(30) NOT NULL,
  `direction` VARCHAR(10) NULL DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'posted',
  `warehouse_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `location_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `total_qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `total_value` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `notes` TEXT NULL,
  `posted_at` DATETIME NULL DEFAULT NULL,
  `posted_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `created_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_movement_document_no_company` (`company_id`, `document_no`),
  KEY `idx_inventory_movement_document_tenant_date` (`company_id`, `site_id`, `document_date`),
  KEY `idx_inventory_movement_document_type_status` (`document_type`, `status`),
  KEY `idx_inventory_movement_document_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_movement_document_lines` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` INT(11) UNSIGNED NOT NULL,
  `stock_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `line_no` INT(11) NOT NULL DEFAULT 1,
  `item_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `item_code` VARCHAR(80) NOT NULL,
  `item_name` VARCHAR(255) NULL DEFAULT NULL,
  `batch_no` VARCHAR(80) NOT NULL DEFAULT '',
  `uom_code` VARCHAR(30) NOT NULL DEFAULT 'PCS',
  `system_qty` DECIMAL(18,4) NULL DEFAULT NULL,
  `counted_qty` DECIMAL(18,4) NULL DEFAULT NULL,
  `qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `stock_value` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_movement_doc_line_document` (`document_id`),
  KEY `idx_inventory_movement_doc_line_movement` (`stock_movement_id`),
  KEY `idx_inventory_movement_doc_line_item` (`item_code`, `batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Add missing line columns for existing document line tables */
SET @table_name := 'inventory_movement_document_lines';
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='batch_no');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_movement_document_lines` ADD COLUMN `batch_no` VARCHAR(80) NOT NULL DEFAULT '''' AFTER `item_name`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='system_qty');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_movement_document_lines` ADD COLUMN `system_qty` DECIMAL(18,4) NULL DEFAULT NULL AFTER `uom_code`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='counted_qty');
SET @sql := IF(@col_exists=0, 'ALTER TABLE `inventory_movement_document_lines` ADD COLUMN `counted_qty` DECIMAL(18,4) NULL DEFAULT NULL AFTER `system_qty`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* Add indexes safely */
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='inventory_movement_document_lines' AND INDEX_NAME='idx_inventory_movement_doc_line_item');
SET @sql := IF(@idx_exists=0, 'ALTER TABLE `inventory_movement_document_lines` ADD KEY `idx_inventory_movement_doc_line_item` (`item_code`, `batch_no`)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

/* =========================================================
   Verification queries
   ========================================================= */
SHOW TABLES LIKE 'batch_masters';
SHOW TABLES LIKE 'inventory_movement_document%';
SHOW TABLES LIKE 'inventory_transfer_%';
DESCRIBE `inventory_movement_documents`;
DESCRIBE `inventory_movement_document_lines`;
