-- ERP PENA - AP/AR Aging partner group fields
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.

SET @db := DATABASE();
SELECT @db AS selected_database;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='suppliers' AND COLUMN_NAME='supplier_group')=0,'ALTER TABLE suppliers ADD COLUMN supplier_group VARCHAR(50) NULL','SELECT ''suppliers.supplier_group exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='customers' AND COLUMN_NAME='customer_group')=0,'ALTER TABLE customers ADD COLUMN customer_group VARCHAR(50) NULL','SELECT ''customers.customer_group exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='suppliers' AND INDEX_NAME='idx_suppliers_supplier_group')=0,'CREATE INDEX idx_suppliers_supplier_group ON suppliers (supplier_group)','SELECT ''idx_suppliers_supplier_group exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='customers' AND INDEX_NAME='idx_customers_customer_group')=0,'CREATE INDEX idx_customers_customer_group ON customers (customer_group)','SELECT ''idx_customers_customer_group exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
