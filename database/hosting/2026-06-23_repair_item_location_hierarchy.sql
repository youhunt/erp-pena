-- Repair item_locations hierarchy after warehouse/location/site hardening.
-- Run after:
--   1) database/hosting/2026-06-23_harden_department_site_scope.sql
--   2) database/hosting/2026-06-23_repair_warehouse_department_scope.sql
--
-- Purpose:
--   Ensure item_locations follows the hierarchy:
--   company/site -> warehouse -> location -> item
--
-- Safe to run multiple times on MySQL/MariaDB.
-- Run after backup database.

-- =========================================================
-- 1) Backfill item_locations.warehouse_id from location when missing
-- =========================================================
UPDATE item_locations il
INNER JOIN locations l ON l.id = il.location_id
SET il.warehouse_id = l.warehouse_id
WHERE (il.warehouse_id IS NULL OR il.warehouse_id = 0)
  AND l.warehouse_id IS NOT NULL
  AND l.warehouse_id > 0;

-- =========================================================
-- 2) Backfill item_locations company/site from warehouse when missing
-- =========================================================
UPDATE item_locations il
INNER JOIN warehouses w ON w.id = il.warehouse_id
SET il.company_id = COALESCE(il.company_id, w.company_id),
    il.site_id = COALESCE(il.site_id, w.site_id)
WHERE (il.company_id IS NULL OR il.site_id IS NULL)
  AND w.company_id IS NOT NULL
  AND w.site_id IS NOT NULL;

-- =========================================================
-- 3) Fix item_locations.warehouse_id if location has a warehouse_id
--    Location is source of truth for warehouse/location relationship.
-- =========================================================
UPDATE item_locations il
INNER JOIN locations l ON l.id = il.location_id
SET il.warehouse_id = l.warehouse_id
WHERE l.warehouse_id IS NOT NULL
  AND l.warehouse_id > 0
  AND il.warehouse_id IS NOT NULL
  AND il.warehouse_id <> l.warehouse_id;

-- =========================================================
-- 4) Backfill item_code from items when missing
-- =========================================================
UPDATE item_locations il
INNER JOIN items i ON i.id = il.item_id
SET il.item_code = i.item_code
WHERE (il.item_code IS NULL OR il.item_code = '')
  AND i.item_code IS NOT NULL
  AND i.item_code <> '';

-- =========================================================
-- 5) Diagnostic AFTER repair
-- Expected result: zero rows.
-- =========================================================
SELECT
    'ITEM_LOCATION_LOCATION_WAREHOUSE_MISMATCH' AS issue_type,
    il.id AS item_location_id,
    il.company_id,
    il.site_id,
    il.warehouse_id AS item_location_warehouse_id,
    il.location_id,
    l.warehouse_id AS location_warehouse_id,
    il.item_id,
    il.item_code
FROM item_locations il
LEFT JOIN locations l ON l.id = il.location_id
WHERE il.location_id IS NOT NULL
  AND il.warehouse_id IS NOT NULL
  AND l.id IS NOT NULL
  AND l.warehouse_id IS NOT NULL
  AND il.warehouse_id <> l.warehouse_id
UNION ALL
SELECT
    'ITEM_LOCATION_ITEM_SCOPE_MISMATCH' AS issue_type,
    il.id AS item_location_id,
    il.company_id,
    il.site_id,
    il.warehouse_id AS item_location_warehouse_id,
    il.location_id,
    NULL AS location_warehouse_id,
    il.item_id,
    il.item_code
FROM item_locations il
LEFT JOIN items i ON i.id = il.item_id
WHERE il.item_id IS NOT NULL
  AND (
      i.id IS NULL
      OR NOT (i.company_id <=> il.company_id)
      OR NOT (i.site_id <=> il.site_id)
  );
