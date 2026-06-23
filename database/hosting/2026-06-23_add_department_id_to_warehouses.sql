-- Add department_id to warehouses when existing hosting database does not have it yet.
-- Fixes: Unknown column 'department_id' in 'INSERT INTO' when saving/importing warehouses.
-- Safe to run multiple times on MySQL/MariaDB.

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'warehouses'
      AND COLUMN_NAME = 'department_id'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE warehouses ADD COLUMN department_id INT NULL AFTER site_id',
    'SELECT ''warehouses.department_id already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'warehouses'
      AND INDEX_NAME = 'idx_warehouses_department_id'
);

SET @sql := IF(
    @index_exists = 0,
    'CREATE INDEX idx_warehouses_department_id ON warehouses (department_id)',
    'SELECT ''idx_warehouses_department_id already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
