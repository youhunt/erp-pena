-- PENA ERP Emergency Hosting SQL Fix
-- Date: 2026-06-21
-- Purpose:
--   Fix #1054 Unknown column 'gl_entry_id' in purchase_receipts/sales_deliveries.
--
-- Run after database backup.
-- Run this before retrying receipt/delivery reversal GL SQL.

ALTER TABLE `purchase_receipts`
  ADD COLUMN IF NOT EXISTS `gl_entry_id` BIGINT UNSIGNED NULL AFTER `status`;

ALTER TABLE `purchase_receipts`
  ADD COLUMN IF NOT EXISTS `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;

ALTER TABLE `sales_deliveries`
  ADD COLUMN IF NOT EXISTS `gl_entry_id` BIGINT UNSIGNED NULL AFTER `status`;

ALTER TABLE `sales_deliveries`
  ADD COLUMN IF NOT EXISTS `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;

-- Verification
SHOW COLUMNS FROM `purchase_receipts` LIKE 'gl_entry_id';
SHOW COLUMNS FROM `purchase_receipts` LIKE 'reversal_gl_entry_id';
SHOW COLUMNS FROM `sales_deliveries` LIKE 'gl_entry_id';
SHOW COLUMNS FROM `sales_deliveries` LIKE 'reversal_gl_entry_id';

-- Manual fallback if ADD COLUMN IF NOT EXISTS is not supported:
-- Run one by one only for missing columns.
--
-- ALTER TABLE `purchase_receipts` ADD COLUMN `gl_entry_id` BIGINT UNSIGNED NULL AFTER `status`;
-- ALTER TABLE `purchase_receipts` ADD COLUMN `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;
-- ALTER TABLE `sales_deliveries` ADD COLUMN `gl_entry_id` BIGINT UNSIGNED NULL AFTER `status`;
-- ALTER TABLE `sales_deliveries` ADD COLUMN `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;
