-- Harden department scope for warehouse import and master data validation.
-- Problem fixed:
--   department_code from a different site can pass validation when the existing departments
--   table was created before site_id existed.
--
-- Safe to run multiple times on MySQL/MariaDB.
-- Run after backup database.

-- =========================================================
-- 1) Ensure departments has company_id and site_id columns
-- =========================================================
SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
      AND COLUMN_NAME = 'company_id'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE departments ADD COLUMN company_id INT NULL AFTER id',
    'SELECT ''departments.company_id already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
      AND COLUMN_NAME = 'site_id'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE departments ADD COLUMN site_id INT NULL AFTER company_id',
    'SELECT ''departments.site_id already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================================================
-- 2) Ensure indexes exist for strict lookup company + site + code
-- =========================================================
SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'departments'
      AND INDEX_NAME = 'idx_departments_company_site_code'
);
SET @sql := IF(
    @index_exists = 0,
    'CREATE INDEX idx_departments_company_site_code ON departments (company_id, site_id, code)',
    'SELECT ''idx_departments_company_site_code already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================================================
-- 3) Backfill known JKT department only to PENA/JKT when still unscoped
-- =========================================================
UPDATE departments d
INNER JOIN companies c ON c.code = 'PENA'
INNER JOIN sites s ON s.company_id = c.id AND s.code = 'JKT'
SET d.company_id = COALESCE(d.company_id, c.id),
    d.site_id = COALESCE(d.site_id, s.id),
    d.updated_at = NOW()
WHERE d.code = 'JKT'
  AND (d.company_id IS NULL OR d.site_id IS NULL)
  AND (d.deleted_at IS NULL OR d.deleted_at = '');

-- =========================================================
-- 4) Create GENERAL department per company/site for old warehouse data
-- =========================================================
INSERT INTO departments (company_id, site_id, code, name, description, is_active, created_at, updated_at)
SELECT DISTINCT w.company_id, w.site_id, 'GENERAL', 'General Department', 'Auto-created default department for warehouse hierarchy', 1, NOW(), NOW()
FROM warehouses w
WHERE w.company_id IS NOT NULL
  AND w.site_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM departments d
      WHERE d.company_id <=> w.company_id
        AND d.site_id <=> w.site_id
        AND d.code = 'GENERAL'
        AND (d.deleted_at IS NULL OR d.deleted_at = '')
  );

-- Assign old warehouses without department to GENERAL in the same site only.
UPDATE warehouses w
INNER JOIN departments d
    ON d.company_id <=> w.company_id
   AND d.site_id <=> w.site_id
   AND d.code = 'GENERAL'
SET w.department_id = d.id
WHERE w.department_id IS NULL;

-- =========================================================
-- 5) Diagnostic query: departments still not scoped
-- =========================================================
SELECT
    id,
    company_id,
    site_id,
    code,
    name
FROM departments
WHERE (company_id IS NULL OR site_id IS NULL)
  AND (deleted_at IS NULL OR deleted_at = '')
ORDER BY code, id;
