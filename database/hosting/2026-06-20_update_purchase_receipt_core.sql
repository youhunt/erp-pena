-- PENA ERP Hosting SQL Update
-- Date: 2026-06-20
-- Purpose:
--   Add purchase receipt reversal tracking fields used by ERP core receipt posting/reversal.
--
-- How to run on hosting:
--   1. Backup database first from phpMyAdmin.
--   2. Open phpMyAdmin > choose PENA ERP database.
--   3. Open SQL tab.
--   4. Paste and execute this file.
--
-- Safe to re-run:
--   Column additions use INFORMATION_SCHEMA checks before ALTER TABLE.

SET @pena_database_name := DATABASE();

-- Add purchase_receipt_lines.reversed_qty if missing
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `purchase_receipt_lines` ADD COLUMN `reversed_qty` DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER `qty_received`',
    'SELECT ''purchase_receipt_lines.reversed_qty already exists'' AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @pena_database_name
    AND TABLE_NAME = 'purchase_receipt_lines'
    AND COLUMN_NAME = 'reversed_qty'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add purchase_receipt_lines.reversed_at if missing
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `purchase_receipt_lines` ADD COLUMN `reversed_at` DATETIME NULL AFTER `location_id`',
    'SELECT ''purchase_receipt_lines.reversed_at already exists'' AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @pena_database_name
    AND TABLE_NAME = 'purchase_receipt_lines'
    AND COLUMN_NAME = 'reversed_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add purchase_receipt_lines.reversed_by if missing
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `purchase_receipt_lines` ADD COLUMN `reversed_by` INT(11) UNSIGNED NULL AFTER `reversed_at`',
    'SELECT ''purchase_receipt_lines.reversed_by already exists'' AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @pena_database_name
    AND TABLE_NAME = 'purchase_receipt_lines'
    AND COLUMN_NAME = 'reversed_by'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add purchase_receipt_lines.reversal_reason if missing
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `purchase_receipt_lines` ADD COLUMN `reversal_reason` VARCHAR(255) NULL AFTER `reversed_by`',
    'SELECT ''purchase_receipt_lines.reversal_reason already exists'' AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @pena_database_name
    AND TABLE_NAME = 'purchase_receipt_lines'
    AND COLUMN_NAME = 'reversal_reason'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verification queries
SHOW COLUMNS FROM `purchase_receipt_lines` LIKE 'reversed_qty';
SHOW COLUMNS FROM `purchase_receipt_lines` LIKE 'reversed_at';
SHOW COLUMNS FROM `purchase_receipt_lines` LIKE 'reversed_by';
SHOW COLUMNS FROM `purchase_receipt_lines` LIKE 'reversal_reason';

-- ---------------------------------------------------------------------------
-- Fallback manual ALTER TABLE section
-- ---------------------------------------------------------------------------
-- Use only if hosting/phpMyAdmin blocks PREPARE statements.
-- Run each ALTER one-by-one only if the column does not exist yet.
--
-- ALTER TABLE `purchase_receipt_lines` ADD COLUMN `reversed_qty` DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER `qty_received`;
-- ALTER TABLE `purchase_receipt_lines` ADD COLUMN `reversed_at` DATETIME NULL AFTER `location_id`;
-- ALTER TABLE `purchase_receipt_lines` ADD COLUMN `reversed_by` INT(11) UNSIGNED NULL AFTER `reversed_at`;
-- ALTER TABLE `purchase_receipt_lines` ADD COLUMN `reversal_reason` VARCHAR(255) NULL AFTER `reversed_by`;
