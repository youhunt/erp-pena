-- PENA ERP manual SQL: cleanup obsolete Purchase Order line commercial/date fields
-- Run only after confirming PO commercial fields are header-only.
-- Safe to run multiple times in MySQL/MariaDB 10.3+ because DROP COLUMN IF EXISTS is supported.

ALTER TABLE `purchase_order_lines`
    DROP COLUMN IF EXISTS `discount_percent`,
    DROP COLUMN IF EXISTS `discount_amount`,
    DROP COLUMN IF EXISTS `freight_amount`,
    DROP COLUMN IF EXISTS `special_charge_amount`,
    DROP COLUMN IF EXISTS `vat_percent`,
    DROP COLUMN IF EXISTS `vat_amount`,
    DROP COLUMN IF EXISTS `wht_percent`,
    DROP COLUMN IF EXISTS `wht_amount`,
    DROP COLUMN IF EXISTS `tax_amount`,
    DROP COLUMN IF EXISTS `delivery_date`,
    DROP COLUMN IF EXISTS `arrive_date`;

DESCRIBE `purchase_order_lines`;
