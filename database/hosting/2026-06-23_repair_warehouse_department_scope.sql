-- Repair warehouses that already point to a department from a different company/site.
-- Run after:
--   1) database/hosting/2026-06-23_harden_department_site_scope.sql
--
-- Purpose:
--   Existing warehouse rows may have department_id filled from a department with the same code
--   but belonging to another site. This SQL remaps them to the same department code in the
--   warehouse's own company/site when available. If not available, it assigns GENERAL department
--   in the warehouse's own company/site.
--
-- Safe to run multiple times on MySQL/MariaDB.
-- Run after backup database.

-- =========================================================
-- 1) Diagnostic BEFORE repair
-- =========================================================
SELECT
    w.id AS warehouse_id,
    w.code AS warehouse_code,
    w.company_id AS warehouse_company_id,
    w.site_id AS warehouse_site_id,
    w.department_id AS current_department_id,
    d.code AS current_department_code,
    d.company_id AS department_company_id,
    d.site_id AS department_site_id
FROM warehouses w
LEFT JOIN departments d ON d.id = w.department_id
WHERE w.department_id IS NOT NULL
  AND (
      d.id IS NULL
      OR NOT (d.company_id <=> w.company_id)
      OR NOT (d.site_id <=> w.site_id)
  )
ORDER BY w.id;

-- =========================================================
-- 2) Ensure GENERAL department exists for every warehouse company/site
-- =========================================================
INSERT INTO departments (company_id, site_id, code, name, description, is_active, created_at, updated_at)
SELECT DISTINCT w.company_id, w.site_id, 'GENERAL', 'General Department', 'Auto-created default department for warehouse hierarchy repair', 1, NOW(), NOW()
FROM warehouses w
WHERE w.company_id IS NOT NULL
  AND w.site_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM departments d
      WHERE d.company_id <=> w.company_id
        AND d.site_id <=> w.site_id
        AND d.code = 'GENERAL'
        AND d.deleted_at IS NULL
  );

-- =========================================================
-- 3) Remap wrong-site department to same code in warehouse site when possible
-- =========================================================
UPDATE warehouses w
INNER JOIN departments old_d ON old_d.id = w.department_id
INNER JOIN departments target_d
    ON target_d.company_id <=> w.company_id
   AND target_d.site_id <=> w.site_id
   AND target_d.code = old_d.code
   AND target_d.deleted_at IS NULL
SET w.department_id = target_d.id
WHERE w.department_id IS NOT NULL
  AND (
      NOT (old_d.company_id <=> w.company_id)
      OR NOT (old_d.site_id <=> w.site_id)
  );

-- =========================================================
-- 4) Assign remaining invalid/missing departments to GENERAL in warehouse site
-- =========================================================
UPDATE warehouses w
LEFT JOIN departments current_d ON current_d.id = w.department_id
INNER JOIN departments general_d
    ON general_d.company_id <=> w.company_id
   AND general_d.site_id <=> w.site_id
   AND general_d.code = 'GENERAL'
   AND general_d.deleted_at IS NULL
SET w.department_id = general_d.id
WHERE w.company_id IS NOT NULL
  AND w.site_id IS NOT NULL
  AND (
      w.department_id IS NULL
      OR current_d.id IS NULL
      OR NOT (current_d.company_id <=> w.company_id)
      OR NOT (current_d.site_id <=> w.site_id)
  );

-- =========================================================
-- 5) Diagnostic AFTER repair. This should return zero rows.
-- =========================================================
SELECT
    w.id AS warehouse_id,
    w.code AS warehouse_code,
    w.company_id AS warehouse_company_id,
    w.site_id AS warehouse_site_id,
    w.department_id AS current_department_id,
    d.code AS current_department_code,
    d.company_id AS department_company_id,
    d.site_id AS department_site_id
FROM warehouses w
LEFT JOIN departments d ON d.id = w.department_id
WHERE w.department_id IS NOT NULL
  AND (
      d.id IS NULL
      OR NOT (d.company_id <=> w.company_id)
      OR NOT (d.site_id <=> w.site_id)
  )
ORDER BY w.id;
