-- PENA ERP Hosting SQL Update
-- Date: 2026-06-21
-- Purpose:
--   Support UAT feedback for Sales Order edit and commercial fields.
--
-- Run after database backup.

ALTER TABLE `sales_orders`
  ADD COLUMN IF NOT EXISTS `discount_percent` DECIMAL(9,4) NOT NULL DEFAULT 0 AFTER `discount_amount`,
  ADD COLUMN IF NOT EXISTS `freight_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `discount_percent`,
  ADD COLUMN IF NOT EXISTS `other_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `freight_amount`,
  ADD COLUMN IF NOT EXISTS `remarks` TEXT NULL AFTER `notes`;

ALTER TABLE `sales_order_lines`
  ADD COLUMN IF NOT EXISTS `description` VARCHAR(255) NULL AFTER `item_name`,
  ADD COLUMN IF NOT EXISTS `discount_percent` DECIMAL(9,4) NOT NULL DEFAULT 0 AFTER `unit_price`,
  ADD COLUMN IF NOT EXISTS `freight_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `discount_amount`,
  ADD COLUMN IF NOT EXISTS `special_charge_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `freight_amount`,
  ADD COLUMN IF NOT EXISTS `other_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `special_charge_amount`;

-- Verification
SHOW COLUMNS FROM `sales_orders` LIKE 'remarks';
SHOW COLUMNS FROM `sales_orders` LIKE 'freight_amount';
SHOW COLUMNS FROM `sales_orders` LIKE 'other_amount';
SHOW COLUMNS FROM `sales_order_lines` LIKE 'description';
SHOW COLUMNS FROM `sales_order_lines` LIKE 'discount_percent';
SHOW COLUMNS FROM `sales_order_lines` LIKE 'freight_amount';
SHOW COLUMNS FROM `sales_order_lines` LIKE 'special_charge_amount';
SHOW COLUMNS FROM `sales_order_lines` LIKE 'other_amount';

-- Manual fallback if ADD COLUMN IF NOT EXISTS is not supported:
-- Run one by one only for missing columns.
-- ALTER TABLE `sales_orders` ADD COLUMN `discount_percent` DECIMAL(9,4) NOT NULL DEFAULT 0 AFTER `discount_amount`;
-- ALTER TABLE `sales_orders` ADD COLUMN `freight_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `discount_percent`;
-- ALTER TABLE `sales_orders` ADD COLUMN `other_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `freight_amount`;
-- ALTER TABLE `sales_orders` ADD COLUMN `remarks` TEXT NULL AFTER `notes`;
-- ALTER TABLE `sales_order_lines` ADD COLUMN `description` VARCHAR(255) NULL AFTER `item_name`;
-- ALTER TABLE `sales_order_lines` ADD COLUMN `discount_percent` DECIMAL(9,4) NOT NULL DEFAULT 0 AFTER `unit_price`;
-- ALTER TABLE `sales_order_lines` ADD COLUMN `freight_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `discount_amount`;
-- ALTER TABLE `sales_order_lines` ADD COLUMN `special_charge_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `freight_amount`;
-- ALTER TABLE `sales_order_lines` ADD COLUMN `other_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `special_charge_amount`;
