-- ERP PENA - Allocation Order inventory location/batch rule columns
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.

SET @db := DATABASE();
SELECT @db AS selected_database;

-- Sales Order Line compatibility columns
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sales_order_lines' AND COLUMN_NAME='allocation_qty')=0,'ALTER TABLE sales_order_lines ADD COLUMN allocation_qty DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''sales_order_lines.allocation_qty exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sales_order_lines' AND COLUMN_NAME='available_so_qty')=0,'ALTER TABLE sales_order_lines ADD COLUMN available_so_qty DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''sales_order_lines.available_so_qty exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sales_order_lines' AND COLUMN_NAME='so_stock_qty')=0,'ALTER TABLE sales_order_lines ADD COLUMN so_stock_qty DECIMAL(20,6) NULL','SELECT ''sales_order_lines.so_stock_qty exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sales_order_lines' AND COLUMN_NAME='so_stock_uom')=0,'ALTER TABLE sales_order_lines ADD COLUMN so_stock_uom VARCHAR(12) NULL','SELECT ''sales_order_lines.so_stock_uom exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sales_order_lines' AND COLUMN_NAME='trans_code')=0,'ALTER TABLE sales_order_lines ADD COLUMN trans_code VARCHAR(12) NULL','SELECT ''sales_order_lines.trans_code exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sales_order_lines' AND COLUMN_NAME='whs')=0,'ALTER TABLE sales_order_lines ADD COLUMN whs VARCHAR(30) NULL','SELECT ''sales_order_lines.whs exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sales_order_lines' AND COLUMN_NAME='shipto')=0,'ALTER TABLE sales_order_lines ADD COLUMN shipto VARCHAR(30) NULL','SELECT ''sales_order_lines.shipto exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE sales_order_lines
SET allocation_qty = COALESCE(qty_reserved, 0)
WHERE allocation_qty = 0;

UPDATE sales_order_lines
SET available_so_qty = GREATEST(0, COALESCE(qty_ordered, qty, 0) - COALESCE(allocation_qty, qty_reserved, 0))
WHERE available_so_qty = 0;

-- Batch master compatibility columns
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='batch_masters' AND COLUMN_NAME='warehouse_id')=0,'ALTER TABLE batch_masters ADD COLUMN warehouse_id INT NULL','SELECT ''batch_masters.warehouse_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='batch_masters' AND COLUMN_NAME='location_id')=0,'ALTER TABLE batch_masters ADD COLUMN location_id INT NULL','SELECT ''batch_masters.location_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='batch_masters' AND COLUMN_NAME='whs')=0,'ALTER TABLE batch_masters ADD COLUMN whs VARCHAR(30) NULL','SELECT ''batch_masters.whs exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='batch_masters' AND COLUMN_NAME='loc')=0,'ALTER TABLE batch_masters ADD COLUMN loc VARCHAR(30) NULL','SELECT ''batch_masters.loc exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='batch_masters' AND COLUMN_NAME='stock_qty')=0,'ALTER TABLE batch_masters ADD COLUMN stock_qty DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''batch_masters.stock_qty exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='batch_masters' AND COLUMN_NAME='allocation_qty')=0,'ALTER TABLE batch_masters ADD COLUMN allocation_qty DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''batch_masters.allocation_qty exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='batch_masters' AND COLUMN_NAME='available_qty')=0,'ALTER TABLE batch_masters ADD COLUMN available_qty DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''batch_masters.available_qty exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
