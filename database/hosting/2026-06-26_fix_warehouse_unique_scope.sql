-- ERP PENA - Warehouse unique scope fix
-- Rule: warehouse code may be repeated when department is different.
-- New scope: company_id + site_id + department_id + code.

USE `dberp_pena`;

-- Drop common old unique indexes if they exist.
SET @db := DATABASE();

SELECT INDEX_NAME INTO @idx_name
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @db
  AND TABLE_NAME = 'warehouses'
  AND NON_UNIQUE = 0
  AND INDEX_NAME <> 'PRIMARY'
GROUP BY INDEX_NAME
HAVING SUM(CASE WHEN COLUMN_NAME = 'company_id' THEN 1 ELSE 0 END) > 0
   AND SUM(CASE WHEN COLUMN_NAME = 'code' THEN 1 ELSE 0 END) > 0
   AND SUM(CASE WHEN COLUMN_NAME = 'department_id' THEN 1 ELSE 0 END) = 0
LIMIT 1;

SET @sql := IF(@idx_name IS NOT NULL, CONCAT('ALTER TABLE warehouses DROP INDEX `', @idx_name, '`'), 'SELECT ''No old warehouse unique index to drop'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_name := NULL;

-- Add new unique key only if it does not exist yet.
SET @has_new_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'warehouses'
      AND INDEX_NAME = 'uq_warehouses_company_site_dept_code'
);

SET @sql := IF(
    @has_new_idx = 0,
    'ALTER TABLE warehouses ADD UNIQUE KEY uq_warehouses_company_site_dept_code (company_id, site_id, department_id, code)',
    'SELECT ''Warehouse unique scope already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'WAREHOUSE_UNIQUE_SCOPE_READY' AS check_name, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_scope
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'warehouses'
  AND INDEX_NAME = 'uq_warehouses_company_site_dept_code'
GROUP BY INDEX_NAME;
