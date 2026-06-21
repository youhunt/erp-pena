-- PENA ERP Hosting SQL Update
-- Date: 2026-06-20
-- Revised: 2026-06-21
-- Purpose:
--   Add GL and reversal GL reference columns for Purchase Receipt and Sales Delivery.
--
-- Why revised:
--   Some hosting databases did not yet have gl_entry_id on purchase_receipts/sales_deliveries.
--   The previous SQL added reversal_gl_entry_id AFTER gl_entry_id, causing:
--     #1054 - Unknown column 'gl_entry_id'
--
-- Run in phpMyAdmin after database backup.
-- If your MySQL/MariaDB does not support ADD COLUMN IF NOT EXISTS,
-- use the fallback manual ALTER TABLE section below.

ALTER TABLE `purchase_receipts`
  ADD COLUMN IF NOT EXISTS `gl_entry_id` BIGINT UNSIGNED NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;

ALTER TABLE `sales_deliveries`
  ADD COLUMN IF NOT EXISTS `gl_entry_id` BIGINT UNSIGNED NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;

-- Verification
SHOW COLUMNS FROM `purchase_receipts` LIKE 'gl_entry_id';
SHOW COLUMNS FROM `purchase_receipts` LIKE 'reversal_gl_entry_id';
SHOW COLUMNS FROM `sales_deliveries` LIKE 'gl_entry_id';
SHOW COLUMNS FROM `sales_deliveries` LIKE 'reversal_gl_entry_id';

-- ---------------------------------------------------------------------------
-- Fallback manual ALTER TABLE section
-- ---------------------------------------------------------------------------
-- Use only if hosting/phpMyAdmin blocks ADD COLUMN IF NOT EXISTS.
-- Run each ALTER one-by-one only if the column does not exist yet.
--
-- ALTER TABLE `purchase_receipts` ADD COLUMN `gl_entry_id` BIGINT UNSIGNED NULL AFTER `status`;
-- ALTER TABLE `purchase_receipts` ADD COLUMN `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;
-- ALTER TABLE `sales_deliveries` ADD COLUMN `gl_entry_id` BIGINT UNSIGNED NULL AFTER `status`;
-- ALTER TABLE `sales_deliveries` ADD COLUMN `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;
