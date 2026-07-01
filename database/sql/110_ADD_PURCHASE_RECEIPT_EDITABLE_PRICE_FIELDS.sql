-- ERP PENA - Purchase Receipt editable price/freight/special price fields
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.

SET @db := DATABASE();
SELECT @db AS selected_database;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='purchase_receipt_lines' AND COLUMN_NAME='unit_price')=0,'ALTER TABLE purchase_receipt_lines ADD COLUMN unit_price DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''purchase_receipt_lines.unit_price exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='purchase_receipt_lines' AND COLUMN_NAME='freight_amount')=0,'ALTER TABLE purchase_receipt_lines ADD COLUMN freight_amount DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''purchase_receipt_lines.freight_amount exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='purchase_receipt_lines' AND COLUMN_NAME='special_price')=0,'ALTER TABLE purchase_receipt_lines ADD COLUMN special_price DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''purchase_receipt_lines.special_price exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE purchase_receipt_lines
SET unit_price = COALESCE(NULLIF(unit_price, 0), unit_cost, 0)
WHERE unit_price IS NULL OR unit_price = 0;
