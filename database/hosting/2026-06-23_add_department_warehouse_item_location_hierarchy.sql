-- SQL version of AddDepartmentWarehouseItemLocationHierarchy
-- Purpose:
--   1. Ensure departments table exists.
--   2. Ensure warehouses can reference departments.
--   3. Ensure locations can reference warehouses.
--   4. Ensure item_locations can reference warehouse/location/item hierarchy.
--   5. Seed a default department for existing warehouses when needed.
--
-- Notes:
--   - Safe to run multiple times on MySQL/MariaDB.
--   - Designed for hosting/cPanel where php spark migrate may not be used.
--   - Run after backup database.

-- =========================================================
-- 1) Departments table
-- =========================================================
CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(50) NULL,
    updated_by VARCHAR(50) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_departments_company_site (company_id, site_id),
    KEY idx_departments_code (code),
    KEY idx_departments_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2) warehouses.department_id
-- =========================================================
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

-- =========================================================
-- 3) locations.warehouse_id
-- =========================================================
SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'locations'
      AND COLUMN_NAME = 'warehouse_id'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE locations ADD COLUMN warehouse_id INT NULL AFTER site_id',
    'SELECT ''locations.warehouse_id already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'locations'
      AND INDEX_NAME = 'idx_locations_warehouse_id'
);

SET @sql := IF(
    @index_exists = 0,
    'CREATE INDEX idx_locations_warehouse_id ON locations (warehouse_id)',
    'SELECT ''idx_locations_warehouse_id already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 4) item_locations hierarchy columns
-- =========================================================
CREATE TABLE IF NOT EXISTS item_locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    warehouse_id INT NULL,
    location_id INT NULL,
    item_id INT NULL,
    item_code VARCHAR(100) NULL,
    min_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
    max_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
    reorder_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(50) NULL,
    updated_by VARCHAR(50) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_item_locations_company_site (company_id, site_id),
    KEY idx_item_locations_warehouse_location (warehouse_id, location_id),
    KEY idx_item_locations_item (item_id, item_code),
    KEY idx_item_locations_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND COLUMN_NAME = 'warehouse_id'
);
SET @sql := IF(@column_exists = 0, 'ALTER TABLE item_locations ADD COLUMN warehouse_id INT NULL AFTER site_id', 'SELECT ''item_locations.warehouse_id already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND COLUMN_NAME = 'location_id'
);
SET @sql := IF(@column_exists = 0, 'ALTER TABLE item_locations ADD COLUMN location_id INT NULL AFTER warehouse_id', 'SELECT ''item_locations.location_id already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND COLUMN_NAME = 'item_id'
);
SET @sql := IF(@column_exists = 0, 'ALTER TABLE item_locations ADD COLUMN item_id INT NULL AFTER location_id', 'SELECT ''item_locations.item_id already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND COLUMN_NAME = 'item_code'
);
SET @sql := IF(@column_exists = 0, 'ALTER TABLE item_locations ADD COLUMN item_code VARCHAR(100) NULL AFTER item_id', 'SELECT ''item_locations.item_code already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND COLUMN_NAME = 'min_qty'
);
SET @sql := IF(@column_exists = 0, 'ALTER TABLE item_locations ADD COLUMN min_qty DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER item_code', 'SELECT ''item_locations.min_qty already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND COLUMN_NAME = 'max_qty'
);
SET @sql := IF(@column_exists = 0, 'ALTER TABLE item_locations ADD COLUMN max_qty DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER min_qty', 'SELECT ''item_locations.max_qty already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND COLUMN_NAME = 'reorder_qty'
);
SET @sql := IF(@column_exists = 0, 'ALTER TABLE item_locations ADD COLUMN reorder_qty DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER max_qty', 'SELECT ''item_locations.reorder_qty already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND COLUMN_NAME = 'is_default'
);
SET @sql := IF(@column_exists = 0, 'ALTER TABLE item_locations ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER reorder_qty', 'SELECT ''item_locations.is_default already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================================================
-- 5) Helpful indexes for item_locations
-- =========================================================
SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND INDEX_NAME = 'idx_item_locations_warehouse_location'
);
SET @sql := IF(@index_exists = 0, 'CREATE INDEX idx_item_locations_warehouse_location ON item_locations (warehouse_id, location_id)', 'SELECT ''idx_item_locations_warehouse_location already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_locations'
      AND INDEX_NAME = 'idx_item_locations_item'
);
SET @sql := IF(@index_exists = 0, 'CREATE INDEX idx_item_locations_item ON item_locations (item_id, item_code)', 'SELECT ''idx_item_locations_item already exists'' AS message');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================================================
-- 6) Seed default department and assign old warehouses
-- =========================================================
INSERT INTO departments (company_id, site_id, code, name, description, is_active, created_at, updated_at)
SELECT DISTINCT w.company_id, w.site_id, 'GENERAL', 'General Department', 'Auto-created default department for warehouse hierarchy', 1, NOW(), NOW()
FROM warehouses w
WHERE NOT EXISTS (
    SELECT 1
    FROM departments d
    WHERE d.company_id <=> w.company_id
      AND d.site_id <=> w.site_id
      AND d.code = 'GENERAL'
      AND (d.deleted_at IS NULL OR d.deleted_at = '')
);

UPDATE warehouses w
INNER JOIN departments d
    ON d.company_id <=> w.company_id
   AND d.site_id <=> w.site_id
   AND d.code = 'GENERAL'
SET w.department_id = d.id
WHERE w.department_id IS NULL;
