-- PENA ERP Hosting SQL Update
-- Date: 2026-06-20
-- Purpose:
--   1. Create document_number_sequences table for DocumentNumberService.
--   2. Add PO line discount/tax fields used by PO import and PO totals.
--
-- How to run on hosting:
--   1. Backup database first from phpMyAdmin.
--   2. Open phpMyAdmin > choose the PENA ERP database.
--   3. Open SQL tab.
--   4. Paste and execute this file.
--   5. If your hosting blocks PREPARE statements, run the fallback ALTER TABLE section at the bottom manually.
--
-- Safe to re-run:
--   CREATE TABLE uses IF NOT EXISTS.
--   Column additions use INFORMATION_SCHEMA checks before ALTER TABLE.

SET @pena_database_name := DATABASE();

CREATE TABLE IF NOT EXISTS `document_number_sequences` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) UNSIGNED NOT NULL,
  `site_id` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 means company-level sequence or no active site',
  `transaction_code` VARCHAR(50) NOT NULL,
  `prefix` VARCHAR(100) NOT NULL,
  `period_key` VARCHAR(20) NOT NULL COMMENT 'Examples: 2026, 202606, 20260619, ALL',
  `last_number` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `padding` TINYINT(3) UNSIGNED NOT NULL DEFAULT 5,
  `reset_period` VARCHAR(20) NOT NULL DEFAULT 'monthly' COMMENT 'daily, monthly, yearly, never',
  `last_document_no` VARCHAR(150) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_no_sequence_scope` (`company_id`, `site_id`, `transaction_code`, `prefix`, `period_key`),
  KEY `idx_document_number_sequences_company_site` (`company_id`, `site_id`),
  KEY `idx_document_number_sequences_transaction_code` (`transaction_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add purchase_order_lines.discount_percent if missing
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `purchase_order_lines` ADD COLUMN `discount_percent` DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER `unit_price`',
    'SELECT ''purchase_order_lines.discount_percent already exists'' AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @pena_database_name
    AND TABLE_NAME = 'purchase_order_lines'
    AND COLUMN_NAME = 'discount_percent'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add purchase_order_lines.discount_amount if missing
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `purchase_order_lines` ADD COLUMN `discount_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `discount_percent`',
    'SELECT ''purchase_order_lines.discount_amount already exists'' AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @pena_database_name
    AND TABLE_NAME = 'purchase_order_lines'
    AND COLUMN_NAME = 'discount_amount'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add purchase_order_lines.vat_amount if missing
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `purchase_order_lines` ADD COLUMN `vat_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `discount_amount`',
    'SELECT ''purchase_order_lines.vat_amount already exists'' AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @pena_database_name
    AND TABLE_NAME = 'purchase_order_lines'
    AND COLUMN_NAME = 'vat_amount'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add purchase_order_lines.wht_amount if missing
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `purchase_order_lines` ADD COLUMN `wht_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `vat_amount`',
    'SELECT ''purchase_order_lines.wht_amount already exists'' AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @pena_database_name
    AND TABLE_NAME = 'purchase_order_lines'
    AND COLUMN_NAME = 'wht_amount'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add purchase_order_lines.tax_amount if missing
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `purchase_order_lines` ADD COLUMN `tax_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `wht_amount`',
    'SELECT ''purchase_order_lines.tax_amount already exists'' AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @pena_database_name
    AND TABLE_NAME = 'purchase_order_lines'
    AND COLUMN_NAME = 'tax_amount'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verification queries
SHOW TABLES LIKE 'document_number_sequences';
SHOW COLUMNS FROM `purchase_order_lines` LIKE 'discount_percent';
SHOW COLUMNS FROM `purchase_order_lines` LIKE 'discount_amount';
SHOW COLUMNS FROM `purchase_order_lines` LIKE 'vat_amount';
SHOW COLUMNS FROM `purchase_order_lines` LIKE 'wht_amount';
SHOW COLUMNS FROM `purchase_order_lines` LIKE 'tax_amount';

-- ---------------------------------------------------------------------------
-- Fallback manual ALTER TABLE section
-- ---------------------------------------------------------------------------
-- Use this section only if your hosting/phpMyAdmin blocks PREPARE statements.
-- Run each ALTER one-by-one only if the column does not exist yet.
--
-- ALTER TABLE `purchase_order_lines` ADD COLUMN `discount_percent` DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER `unit_price`;
-- ALTER TABLE `purchase_order_lines` ADD COLUMN `discount_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `discount_percent`;
-- ALTER TABLE `purchase_order_lines` ADD COLUMN `vat_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `discount_amount`;
-- ALTER TABLE `purchase_order_lines` ADD COLUMN `wht_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `vat_amount`;
-- ALTER TABLE `purchase_order_lines` ADD COLUMN `tax_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `wht_amount`;
