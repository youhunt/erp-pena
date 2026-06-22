-- Rebuild inventory_stock_balances from inventory_stock_movements.
-- Purpose:
-- - Fix stock balance mismatch after import/manual repair.
-- - Make Delivery Order available stock consistent with Stock Card.
-- - Keep movement ledger as the source of truth.
--
-- IMPORTANT:
-- 1) Backup database before running.
-- 2) Run diagnostic SELECT first.
-- 3) Run rebuild only after confirming movements are correct.
-- 4) This script rebuilds qty/value by company/site/warehouse/location/item.

/* =========================================================
   A. DIAGNOSTIC - compare balance vs movement ledger
   ========================================================= */

SELECT
    COALESCE(b.company_id, mv.company_id) AS company_id,
    COALESCE(b.site_id, mv.site_id) AS site_id,
    COALESCE(wb.code, wm.code) AS warehouse_code,
    COALESCE(lb.code, lm.code) AS location_code,
    COALESCE(b.item_code, mv.item_code) AS item_code,
    COALESCE(b.qty_on_hand, 0) AS balance_qty_on_hand,
    COALESCE(b.qty_available, 0) AS balance_qty_available,
    COALESCE(mv.qty_on_hand, 0) AS movement_qty_on_hand,
    COALESCE(b.qty_on_hand, 0) - COALESCE(mv.qty_on_hand, 0) AS qty_difference,
    COALESCE(b.stock_value, 0) AS balance_stock_value,
    COALESCE(mv.stock_value, 0) AS movement_stock_value,
    COALESCE(b.stock_value, 0) - COALESCE(mv.stock_value, 0) AS value_difference
FROM inventory_stock_balances b
LEFT JOIN warehouses wb ON wb.id = b.warehouse_id
LEFT JOIN locations lb ON lb.id = b.location_id
LEFT JOIN (
    SELECT
        company_id,
        site_id,
        warehouse_id,
        location_id,
        item_code,
        MAX(item_name) AS item_name,
        MAX(uom_code) AS uom_code,
        SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END) AS qty_on_hand,
        SUM(CASE WHEN direction = 'in' THEN value_amount ELSE -value_amount END) AS stock_value
    FROM inventory_stock_movements
    WHERE warehouse_id IS NOT NULL
      AND location_id IS NOT NULL
      AND item_code IS NOT NULL
      AND item_code <> ''
    GROUP BY company_id, site_id, warehouse_id, location_id, item_code
) mv ON mv.company_id = b.company_id
    AND (mv.site_id <=> b.site_id)
    AND mv.warehouse_id = b.warehouse_id
    AND mv.location_id = b.location_id
    AND mv.item_code = b.item_code
LEFT JOIN warehouses wm ON wm.id = mv.warehouse_id
LEFT JOIN locations lm ON lm.id = mv.location_id
WHERE ABS(COALESCE(b.qty_on_hand, 0) - COALESCE(mv.qty_on_hand, 0)) > 0.0001
   OR ABS(COALESCE(b.stock_value, 0) - COALESCE(mv.stock_value, 0)) > 0.01
ORDER BY item_code, warehouse_code, location_code;

/* =========================================================
   B. REBUILD - delete current balances and rebuild from movements
   ========================================================= */

-- B1. Optional scoped delete.
-- For safer UAT first run, uncomment WHERE filters as needed.
-- Example filters:
-- WHERE company_id = 1 AND site_id = 1

DELETE FROM inventory_stock_balances;

-- B2. Recreate balances from valid movement rows.
INSERT INTO inventory_stock_balances (
    company_id,
    site_id,
    warehouse_id,
    location_id,
    item_id,
    item_code,
    item_name,
    uom_code,
    qty_on_hand,
    qty_reserved,
    qty_available,
    avg_cost,
    stock_value,
    last_movement_date,
    created_at,
    updated_at
)
SELECT
    m.company_id,
    m.site_id,
    m.warehouse_id,
    m.location_id,
    MAX(m.item_id) AS item_id,
    m.item_code,
    MAX(m.item_name) AS item_name,
    MAX(m.uom_code) AS uom_code,
    SUM(CASE WHEN m.direction = 'in' THEN m.qty ELSE -m.qty END) AS qty_on_hand,
    0 AS qty_reserved,
    SUM(CASE WHEN m.direction = 'in' THEN m.qty ELSE -m.qty END) AS qty_available,
    CASE
        WHEN SUM(CASE WHEN m.direction = 'in' THEN m.qty ELSE -m.qty END) = 0 THEN 0
        ELSE ROUND(SUM(CASE WHEN m.direction = 'in' THEN m.value_amount ELSE -m.value_amount END) / SUM(CASE WHEN m.direction = 'in' THEN m.qty ELSE -m.qty END), 6)
    END AS avg_cost,
    SUM(CASE WHEN m.direction = 'in' THEN m.value_amount ELSE -m.value_amount END) AS stock_value,
    MAX(m.movement_date) AS last_movement_date,
    NOW() AS created_at,
    NOW() AS updated_at
FROM inventory_stock_movements m
WHERE m.warehouse_id IS NOT NULL
  AND m.location_id IS NOT NULL
  AND m.item_code IS NOT NULL
  AND m.item_code <> ''
GROUP BY
    m.company_id,
    m.site_id,
    m.warehouse_id,
    m.location_id,
    m.item_code
HAVING ABS(qty_on_hand) > 0.0001
    OR ABS(stock_value) > 0.01;

/* =========================================================
   C. VERIFY - delivery stock candidates after rebuild
   ========================================================= */

SELECT
    b.item_code,
    w.code AS warehouse_code,
    l.code AS location_code,
    l.name AS location_name,
    b.qty_on_hand,
    b.qty_reserved,
    b.qty_available,
    b.avg_cost,
    b.stock_value,
    CASE
        WHEN b.qty_available > 0 THEN 'CAN_DELIVER_FROM_THIS_LOCATION'
        ELSE 'NO_AVAILABLE_STOCK'
    END AS delivery_hint
FROM inventory_stock_balances b
JOIN warehouses w ON w.id = b.warehouse_id
JOIN locations l ON l.id = b.location_id
ORDER BY b.item_code, w.code, l.code;
