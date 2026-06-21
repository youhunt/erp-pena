-- PENA ERP Hosting SQL Update
-- Date: 2026-06-21
-- Purpose:
--   Support UAT feedback for Purchase Order screen:
--   - PO activation/reopen keeps cancellation metadata nullable.
--   - PO header stores VAT Code and WHT Code.
--
-- Run after database backup.

ALTER TABLE `purchase_orders`
  ADD COLUMN IF NOT EXISTS `vat_code` VARCHAR(60) NULL AFTER `special_charge_amount`,
  ADD COLUMN IF NOT EXISTS `wht_code` VARCHAR(60) NULL AFTER `vat_code`;

-- Verification
SHOW COLUMNS FROM `purchase_orders` LIKE 'vat_code';
SHOW COLUMNS FROM `purchase_orders` LIKE 'wht_code';
