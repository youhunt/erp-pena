-- Master Data Integrity Audit for ERP PENA hosting database.
-- Purpose:
--   Run this after all master-data hierarchy SQL repairs to ensure no cross-company/site/warehouse
--   references remain in setup master data.
--
-- This file does not change data. It only returns diagnostic rows.
-- Expected result: every SELECT should return zero rows.
-- Note:
--   deleted_at is DATETIME, so audit only compares it with NULL to avoid MySQL #1292 warnings.

-- =========================================================
-- 1) Departments without company/site scope
-- =========================================================
SELECT
    'DEPARTMENT_WITHOUT_SCOPE' AS issue_type,
    d.id AS record_id,
    d.code AS record_code,
    d.company_id,
    d.site_id,
    NULL AS related_id,
    NULL AS related_code,
    'Department must have company_id and site_id.' AS issue_message
FROM departments d
WHERE (d.company_id IS NULL OR d.site_id IS NULL)
  AND d.deleted_at IS NULL;

-- =========================================================
-- 2) Warehouses pointing to department from another company/site
-- =========================================================
SELECT
    'WAREHOUSE_DEPARTMENT_SCOPE_MISMATCH' AS issue_type,
    w.id AS record_id,
    w.code AS record_code,
    w.company_id,
    w.site_id,
    d.id AS related_id,
    d.code AS related_code,
    CONCAT('Warehouse department belongs to company/site ', COALESCE(d.company_id, 'NULL'), '/', COALESCE(d.site_id, 'NULL')) AS issue_message
FROM warehouses w
LEFT JOIN departments d ON d.id = w.department_id
WHERE w.department_id IS NOT NULL
  AND (
      d.id IS NULL
      OR NOT (d.company_id <=> w.company_id)
      OR NOT (d.site_id <=> w.site_id)
  )
  AND w.deleted_at IS NULL;

-- =========================================================
-- 3) Locations pointing to warehouse from another company/site
-- =========================================================
SELECT
    'LOCATION_WAREHOUSE_SCOPE_MISMATCH' AS issue_type,
    l.id AS record_id,
    l.code AS record_code,
    l.company_id,
    l.site_id,
    w.id AS related_id,
    w.code AS related_code,
    CONCAT('Location warehouse belongs to company/site ', COALESCE(w.company_id, 'NULL'), '/', COALESCE(w.site_id, 'NULL')) AS issue_message
FROM locations l
LEFT JOIN warehouses w ON w.id = l.warehouse_id
WHERE l.warehouse_id IS NOT NULL
  AND (
      w.id IS NULL
      OR NOT (w.company_id <=> l.company_id)
      OR NOT (w.site_id <=> l.site_id)
  )
  AND l.deleted_at IS NULL;

-- =========================================================
-- 4) Item locations where location does not belong to selected warehouse
-- =========================================================
SELECT
    'ITEM_LOCATION_WAREHOUSE_LOCATION_MISMATCH' AS issue_type,
    il.id AS record_id,
    il.item_code AS record_code,
    il.company_id,
    il.site_id,
    l.id AS related_id,
    l.code AS related_code,
    CONCAT('item_locations.warehouse_id=', COALESCE(il.warehouse_id, 'NULL'), ' but locations.warehouse_id=', COALESCE(l.warehouse_id, 'NULL')) AS issue_message
FROM item_locations il
LEFT JOIN locations l ON l.id = il.location_id
WHERE il.location_id IS NOT NULL
  AND il.warehouse_id IS NOT NULL
  AND l.id IS NOT NULL
  AND l.warehouse_id IS NOT NULL
  AND l.warehouse_id <> il.warehouse_id;

-- =========================================================
-- 5) Item locations pointing to item from another company/site
-- =========================================================
SELECT
    'ITEM_LOCATION_ITEM_SCOPE_MISMATCH' AS issue_type,
    il.id AS record_id,
    il.item_code AS record_code,
    il.company_id,
    il.site_id,
    i.id AS related_id,
    i.item_code AS related_code,
    CONCAT('Item belongs to company/site ', COALESCE(i.company_id, 'NULL'), '/', COALESCE(i.site_id, 'NULL')) AS issue_message
FROM item_locations il
LEFT JOIN items i ON i.id = il.item_id
WHERE il.item_id IS NOT NULL
  AND (
      i.id IS NULL
      OR NOT (i.company_id <=> il.company_id)
      OR NOT (i.site_id <=> il.site_id)
  );
