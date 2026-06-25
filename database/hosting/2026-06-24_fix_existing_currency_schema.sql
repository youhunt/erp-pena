-- ERP PENA - Fix existing currencies table before running ERP core finalizer
-- Use this when currencies table already exists but does not have company_id / audit columns.

USE `dberp_pena`;

SET @db := DATABASE();

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'currencies' AND COLUMN_NAME = 'company_id') = 0,
    'ALTER TABLE currencies ADD COLUMN company_id INT NULL AFTER id',
    'SELECT ''currencies.company_id already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'currencies' AND COLUMN_NAME = 'rounding') = 0,
    'ALTER TABLE currencies ADD COLUMN rounding DECIMAL(10,4) NOT NULL DEFAULT 0 AFTER name',
    'SELECT ''currencies.rounding already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'currencies' AND COLUMN_NAME = 'is_active') = 0,
    'ALTER TABLE currencies ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER rounding',
    'SELECT ''currencies.is_active already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'currencies' AND COLUMN_NAME = 'created_by') = 0,
    'ALTER TABLE currencies ADD COLUMN created_by INT NULL AFTER is_active',
    'SELECT ''currencies.created_by already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'currencies' AND COLUMN_NAME = 'updated_by') = 0,
    'ALTER TABLE currencies ADD COLUMN updated_by INT NULL AFTER created_by',
    'SELECT ''currencies.updated_by already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'currencies' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE currencies ADD COLUMN created_at DATETIME NULL AFTER updated_by',
    'SELECT ''currencies.created_at already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'currencies' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE currencies ADD COLUMN updated_at DATETIME NULL AFTER created_at',
    'SELECT ''currencies.updated_at already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'currencies' AND COLUMN_NAME = 'deleted_at') = 0,
    'ALTER TABLE currencies ADD COLUMN deleted_at DATETIME NULL AFTER updated_at',
    'SELECT ''currencies.deleted_at already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill common currencies after columns exist.
INSERT INTO currencies (company_id, code, name, rounding, is_active, created_at, updated_at)
SELECT NULL, 'IDR', 'Indonesian Rupiah', 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM currencies WHERE code = 'IDR');

INSERT INTO currencies (company_id, code, name, rounding, is_active, created_at, updated_at)
SELECT NULL, 'USD', 'US Dollar', 0.01, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM currencies WHERE code = 'USD');

UPDATE currencies
SET company_id = NULL,
    is_active = COALESCE(is_active, 1),
    rounding = COALESCE(rounding, 0),
    updated_at = COALESCE(updated_at, NOW())
WHERE code IN ('IDR','USD');

SELECT
    'CURRENCIES_SCHEMA_READY' AS check_name,
    COUNT(*) AS ready_count,
    9 AS expected_count
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'currencies'
  AND COLUMN_NAME IN ('id','company_id','code','name','rounding','is_active','created_at','updated_at','deleted_at');

SELECT id, company_id, code, name, rounding, is_active
FROM currencies
WHERE code IN ('IDR','USD')
ORDER BY code;
