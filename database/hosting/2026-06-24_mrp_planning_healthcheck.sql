-- ERP PENA - MRP/Forecast planning health checks.
-- Run after Forecast + MRP SQL.
-- These checks help identify why MRP output may be incomplete.

SELECT 'MRP_FORECAST_WITHOUT_ACTIVE_BOM' AS check_name, COUNT(*) AS total
FROM (
    SELECT f.company_id, f.site_id, f.item_code
    FROM production_forecasts f
    LEFT JOIN production_boms b
        ON b.company_id = f.company_id
       AND b.parent_item_code = f.item_code
       AND COALESCE(b.is_active, 1) = 1
       AND (b.site_id = f.site_id OR b.site_id IS NULL OR b.site_id = 0)
       AND (b.deleted_at IS NULL OR b.deleted_at IS NULL)
    WHERE f.deleted_at IS NULL
      AND f.status IN ('draft', 'confirmed', 'approved')
    GROUP BY f.company_id, f.site_id, f.item_code
    HAVING COUNT(b.id) = 0
) x;

SELECT 'MRP_BOM_LINES_WITHOUT_ITEM_MASTER' AS check_name, COUNT(*) AS total
FROM production_bom_lines l
JOIN production_boms b ON b.id = l.production_bom_id
LEFT JOIN items i
    ON i.company_id = b.company_id
   AND i.item_code = l.child_item_code
   AND (i.site_id = b.site_id OR i.site_id IS NULL OR i.site_id = 0)
   AND i.deleted_at IS NULL
WHERE COALESCE(l.child_item_code, '') <> ''
  AND i.id IS NULL;

SELECT 'MRP_BOM_LINES_WITH_ZERO_QTY' AS check_name, COUNT(*) AS total
FROM production_bom_lines l
WHERE COALESCE(l.qty_used, 0) <= 0;

SELECT 'MRP_FORECAST_ZERO_OR_NEGATIVE_QTY' AS check_name, COUNT(*) AS total
FROM production_forecasts f
WHERE f.deleted_at IS NULL
  AND COALESCE(f.qty, 0) <= 0;

SELECT 'MRP_READY_FORECAST_ITEM_COUNT' AS check_name, COUNT(*) AS total
FROM (
    SELECT f.company_id, f.site_id, f.item_code
    FROM production_forecasts f
    JOIN production_boms b
        ON b.company_id = f.company_id
       AND b.parent_item_code = f.item_code
       AND COALESCE(b.is_active, 1) = 1
       AND (b.site_id = f.site_id OR b.site_id IS NULL OR b.site_id = 0)
    WHERE f.deleted_at IS NULL
      AND f.status IN ('draft', 'confirmed', 'approved')
      AND COALESCE(f.qty, 0) > 0
    GROUP BY f.company_id, f.site_id, f.item_code
) x;

-- Detail helpers.
SELECT f.company_id, f.site_id, f.item_code, MAX(f.item_name) AS item_name, SUM(f.qty) AS forecast_qty
FROM production_forecasts f
LEFT JOIN production_boms b
    ON b.company_id = f.company_id
   AND b.parent_item_code = f.item_code
   AND COALESCE(b.is_active, 1) = 1
   AND (b.site_id = f.site_id OR b.site_id IS NULL OR b.site_id = 0)
WHERE f.deleted_at IS NULL
  AND f.status IN ('draft', 'confirmed', 'approved')
GROUP BY f.company_id, f.site_id, f.item_code
HAVING COUNT(b.id) = 0
ORDER BY f.item_code
LIMIT 100;

SELECT b.company_id, b.site_id, b.parent_item_code, l.child_item_code, l.child_item_name, l.qty_used, l.uom_code
FROM production_bom_lines l
JOIN production_boms b ON b.id = l.production_bom_id
LEFT JOIN items i
    ON i.company_id = b.company_id
   AND i.item_code = l.child_item_code
   AND (i.site_id = b.site_id OR i.site_id IS NULL OR i.site_id = 0)
   AND i.deleted_at IS NULL
WHERE COALESCE(l.child_item_code, '') <> ''
  AND i.id IS NULL
ORDER BY b.parent_item_code, l.child_no
LIMIT 100;
