-- PENA ERP manual SQL: add optional partner fields to customer/supplier terms
-- Safe to run multiple times in phpMyAdmin/cPanel.

SET @db_name := DATABASE();

SET @table_name := 'customer_terms';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='customer');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `customer_terms` ADD COLUMN `customer` VARCHAR(80) NULL DEFAULT NULL AFTER `site`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='customer_name');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `customer_terms` ADD COLUMN `customer_name` VARCHAR(255) NULL DEFAULT NULL AFTER `customer`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND INDEX_NAME='idx_customer_terms_customer');
SET @sql := IF(@table_exists=1 AND @idx_exists=0, 'ALTER TABLE `customer_terms` ADD KEY `idx_customer_terms_customer` (`company_id`, `site_id`, `customer`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'supplier_terms';
SET @table_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name);
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='supplier');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `supplier_terms` ADD COLUMN `supplier` VARCHAR(80) NULL DEFAULT NULL AFTER `site`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND COLUMN_NAME='supplier_name');
SET @sql := IF(@table_exists=1 AND @col_exists=0, 'ALTER TABLE `supplier_terms` ADD COLUMN `supplier_name` VARCHAR(255) NULL DEFAULT NULL AFTER `supplier`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@table_name AND INDEX_NAME='idx_supplier_terms_supplier');
SET @sql := IF(@table_exists=1 AND @idx_exists=0, 'ALTER TABLE `supplier_terms` ADD KEY `idx_supplier_terms_supplier` (`company_id`, `site_id`, `supplier`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verification
DESCRIBE `customer_terms`;
DESCRIBE `supplier_terms`;
