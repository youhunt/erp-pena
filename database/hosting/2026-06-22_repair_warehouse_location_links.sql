-- Repair Warehouse - Location relationship for Stock Adjustment filter.
-- Problem symptom:
-- Location dropdown is empty after selecting Warehouse, while stock card shows warehouse/location codes.
-- Main cause:
-- locations.warehouse_id is NULL/wrong or warehouse/location codes from old imported data are not linked.
--
-- HOW TO USE:
-- 1) Backup database first.
-- 2) Run section A to inspect data.
-- 3) Run section B to repair common MAIN/A01 relationship.
-- 4) Refresh Stock Adjustment page.

/* =========================================================
   A. DIAGNOSTIC QUERIES
   ========================================================= */

-- A1. List warehouses in active/demo data.
SELECT
    id,
    company_id,
    site_id,
    code,
    name,
    is_active
FROM warehouses
ORDER BY company_id, site_id, code, id;

-- A2. List locations and their current warehouse link.
SELECT
    l.id,
    l.company_id,
    l.site_id,
    l.warehouse_id,
    w.code AS linked_warehouse_code,
    l.code AS location_code,
    l.name AS location_name,
    l.is_active
FROM locations l
LEFT JOIN warehouses w ON w.id = l.warehouse_id
ORDER BY l.company_id, l.site_id, l.code, l.id;

-- A3. Show locations that cannot appear after warehouse filter.
SELECT
    l.id,
    l.company_id,
    l.site_id,
    l.warehouse_id,
    l.code,
    l.name
FROM locations l
LEFT JOIN warehouses w ON w.id = l.warehouse_id
WHERE l.warehouse_id IS NULL
   OR l.warehouse_id = 0
   OR w.id IS NULL
ORDER BY l.company_id, l.site_id, l.code, l.id;

-- A4. Show warehouse/location codes currently used by stock movements.
SELECT
    company_id,
    site_id,
    warehouse_id,
    location_id,
    warehouse_code,
    location_code,
    COUNT(*) AS movement_count
FROM (
    SELECT
        m.company_id,
        m.site_id,
        m.warehouse_id,
        m.location_id,
        w.code AS warehouse_code,
        l.code AS location_code
    FROM inventory_stock_movements m
    LEFT JOIN warehouses w ON w.id = m.warehouse_id
    LEFT JOIN locations l ON l.id = m.location_id
) x
GROUP BY company_id, site_id, warehouse_id, location_id, warehouse_code, location_code
ORDER BY company_id, site_id, warehouse_code, location_code;

/* =========================================================
   B. REPAIR COMMON MAIN/A01 DATA
   ========================================================= */

-- B1. Make sure MAIN warehouse is active.
UPDATE warehouses
SET is_active = 1,
    updated_at = NOW()
WHERE code = 'MAIN';

-- B2. Link existing A01 location to MAIN warehouse when A01 has no valid warehouse link.
UPDATE locations l
JOIN warehouses w
  ON w.code = 'MAIN'
 AND w.company_id = l.company_id
 AND (w.site_id = l.site_id OR w.site_id IS NULL OR l.site_id IS NULL)
LEFT JOIN warehouses current_w
  ON current_w.id = l.warehouse_id
SET l.warehouse_id = w.id,
    l.is_active = 1,
    l.updated_at = NOW()
WHERE l.code IN ('A01', 'MAIN')
  AND (l.warehouse_id IS NULL OR l.warehouse_id = 0 OR current_w.id IS NULL);

-- B3. If MAIN warehouse exists but no A01/MAIN location exists, create A01.
INSERT INTO locations (company_id, site_id, warehouse_id, code, name, is_active, created_at, updated_at)
SELECT
    w.company_id,
    w.site_id,
    w.id,
    'A01',
    'Area 01',
    1,
    NOW(),
    NOW()
FROM warehouses w
WHERE w.code = 'MAIN'
  AND NOT EXISTS (
      SELECT 1
      FROM locations l
      WHERE l.warehouse_id = w.id
        AND l.code IN ('A01', 'MAIN')
  );

-- B4. For every active warehouse without any location, create MAIN location.
INSERT INTO locations (company_id, site_id, warehouse_id, code, name, is_active, created_at, updated_at)
SELECT
    w.company_id,
    w.site_id,
    w.id,
    'MAIN',
    'Main Location',
    1,
    NOW(),
    NOW()
FROM warehouses w
WHERE COALESCE(w.is_active, 1) = 1
  AND NOT EXISTS (
      SELECT 1
      FROM locations l
      WHERE l.warehouse_id = w.id
  );

/* =========================================================
   C. VERIFY AFTER REPAIR
   ========================================================= */

SELECT
    w.id AS warehouse_id,
    w.code AS warehouse_code,
    w.name AS warehouse_name,
    l.id AS location_id,
    l.code AS location_code,
    l.name AS location_name,
    l.is_active AS location_active
FROM warehouses w
LEFT JOIN locations l ON l.warehouse_id = w.id
WHERE w.code = 'MAIN'
ORDER BY w.id, l.code;
