-- PENA ERP manual SQL: Purchase Order header commercial/date/remarks + line description
-- Safe to run multiple times in phpMyAdmin/cPanel.

SET @db_name := DATABASE();

SET @table_name := 'purchase_orders';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='delivery_date');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_orders` ADD COLUMN `delivery_date` DATE NULL DEFAULT NULL AFTER `po_date`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='arrive_date');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_orders` ADD COLUMN `arrive_date` DATE NULL DEFAULT NULL AFTER `delivery_date`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='discount_percent');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_orders` ADD COLUMN `discount_percent` DECIMAL(10,4) NOT NULL DEFAULT 0 AFTER `subtotal_amount`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='freight_amount');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_orders` ADD COLUMN `freight_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `discount_amount`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='other_amount');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_orders` ADD COLUMN `other_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `freight_amount`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='special_charge_amount');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_orders` ADD COLUMN `special_charge_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `other_amount`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='vat_amount');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_orders` ADD COLUMN `vat_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `special_charge_amount`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='wht_amount');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_orders` ADD COLUMN `wht_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `vat_amount`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='remarks');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_orders` ADD COLUMN `remarks` TEXT NULL AFTER `notes`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'purchase_order_lines';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='description');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `purchase_order_lines` ADD COLUMN `description` TEXT NULL AFTER `item_name`', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DESCRIBE `purchase_orders`;
DESCRIBE `purchase_order_lines`;
