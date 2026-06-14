-- Manual MySQL migration for batch on Purchase Receipt and Sales Delivery lines
-- Project: PENA ERP
-- Use this file when hosting/cPanel cannot run: php spark migrate --all

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_receipt_lines' AND COLUMN_NAME = 'batch_no');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `purchase_receipt_lines` ADD COLUMN `batch_no` VARCHAR(80) NOT NULL DEFAULT '''' AFTER `item_code`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `purchase_receipt_lines` SET `batch_no` = '' WHERE `batch_no` IS NULL;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sales_delivery_lines' AND COLUMN_NAME = 'batch_no');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `sales_delivery_lines` ADD COLUMN `batch_no` VARCHAR(80) NOT NULL DEFAULT '''' AFTER `item_code`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `sales_delivery_lines` SET `batch_no` = '' WHERE `batch_no` IS NULL;
