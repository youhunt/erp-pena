-- PENA ERP Hosting SQL Update
-- Date: 2026-06-20
-- Purpose:
--   Add reversal GL reference columns for Purchase Receipt and Sales Delivery reversal.
--
-- Required after pulling source that posts reversal GL for:
--   - purchase receipt reversal
--   - sales delivery reversal
--
-- Run in phpMyAdmin after database backup.

ALTER TABLE `purchase_receipts`
  ADD COLUMN IF NOT EXISTS `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;

ALTER TABLE `sales_deliveries`
  ADD COLUMN IF NOT EXISTS `reversal_gl_entry_id` BIGINT UNSIGNED NULL AFTER `gl_entry_id`;

-- Verification
SHOW COLUMNS FROM `purchase_receipts` LIKE 'reversal_gl_entry_id';
SHOW COLUMNS FROM `sales_deliveries` LIKE 'reversal_gl_entry_id';
