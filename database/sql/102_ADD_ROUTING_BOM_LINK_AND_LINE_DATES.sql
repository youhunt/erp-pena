-- ERP PENA - Routing line active/inactive dates + BOM routing link
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.
-- Aman dijalankan berulang kali karena setiap field/index dicek dulu di information_schema.

SET @db := DATABASE();
SELECT @db AS selected_database;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_routing_lines')=1
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_routing_lines' AND COLUMN_NAME='active_date')=0,
    'ALTER TABLE production_routing_lines ADD COLUMN active_date DATE NULL',
    'SELECT ''production_routing_lines.active_date exists or table missing'' AS info'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_routing_lines')=1
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_routing_lines' AND COLUMN_NAME='inactive_date')=0,
    'ALTER TABLE production_routing_lines ADD COLUMN inactive_date DATE NULL',
    'SELECT ''production_routing_lines.inactive_date exists or table missing'' AS info'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_routing_lines')=1
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_routing_lines' AND COLUMN_NAME='inactive_date')=1,
    'UPDATE production_routing_lines SET inactive_date = ''9999-12-31'' WHERE inactive_date IS NULL',
    'SELECT ''skip production_routing_lines inactive_date default'' AS info'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_boms')=1
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_boms' AND COLUMN_NAME='routing_id')=0,
    'ALTER TABLE production_boms ADD COLUMN routing_id BIGINT UNSIGNED NULL',
    'SELECT ''production_boms.routing_id exists or table missing'' AS info'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_boms')=1
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_boms' AND COLUMN_NAME='routing_id')=1
    AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_boms' AND INDEX_NAME='idx_production_boms_routing_id')=0,
    'ALTER TABLE production_boms ADD INDEX idx_production_boms_routing_id (routing_id)',
    'SELECT ''production_boms.routing_id index exists or table/column missing'' AS info'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
