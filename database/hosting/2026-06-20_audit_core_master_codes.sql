-- PENA ERP Hosting SQL Audit
-- Date: 2026-06-20
-- Purpose: Review repeated core master codes. This script does not modify data.

SELECT 'customers' AS table_name, company_id, site_id, code, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
FROM customers
WHERE deleted_at IS NULL AND COALESCE(code, '') <> ''
GROUP BY company_id, site_id, code
HAVING COUNT(*) > 1;

SELECT 'suppliers' AS table_name, company_id, site_id, code, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
FROM suppliers
WHERE deleted_at IS NULL AND COALESCE(code, '') <> ''
GROUP BY company_id, site_id, code
HAVING COUNT(*) > 1;

SELECT 'items' AS table_name, company_id, site_id, code, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
FROM items
WHERE deleted_at IS NULL AND COALESCE(code, '') <> ''
GROUP BY company_id, site_id, code
HAVING COUNT(*) > 1;

SELECT 'warehouses' AS table_name, company_id, site_id, code, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
FROM warehouses
WHERE deleted_at IS NULL AND COALESCE(code, '') <> ''
GROUP BY company_id, site_id, code
HAVING COUNT(*) > 1;

SELECT 'locations' AS table_name, company_id, site_id, warehouse_id, code, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
FROM locations
WHERE deleted_at IS NULL AND COALESCE(code, '') <> ''
GROUP BY company_id, site_id, warehouse_id, code
HAVING COUNT(*) > 1;
