-- Repair orphan stock balance/movement for Sales Delivery UAT.
-- Use this after running 2026-06-22_debug_sales_delivery_stock.sql when SO items have stock
-- but warehouse_code/location_code are NULL, so Create Delivery Order shows Available = 0.
--
-- Current observed case:
-- - SO item ITEM-0003 has stock, but warehouse/location is NULL.
-- - SO item ITEM-0006 already has stock in MAIN/A01 and can deliver from there.
--
-- This script moves orphan ITEM-0003 stock rows into MAIN / A01 only when the item does not
-- already have a stock balance row in MAIN / A01.
--
-- BACKUP DATABASE BEFORE RUNNING.

/* =========================================================
   A. Preview orphan stock for SO items
   ========================================================= */

SELECT
    b.id,
    b.company_id,
    b.site_id,
    b.item_code,
    b.warehouse_id,
    b.location_id,
    b.qty_on_hand,
    b.qty_reserved,
    b.qty_available,
    b.stock_value
FROM inventory_stock_balances b
WHERE b.item_code IN ('ITEM-0003', 'ITEM-0006')
  AND (b.warehouse_id IS NULL OR b.location_id IS NULL OR b.warehouse_id = 0 OR b.location_id = 0)
ORDER BY b.item_code, b.id;

/* =========================================================
   B. Repair orphan balance rows for ITEM-0003 into MAIN / A01
   ========================================================= */

UPDATE inventory_stock_balances b
JOIN warehouses w
  ON w.code = 'MAIN'
 AND w.company_id = b.company_id
 AND (w.site_id = b.site_id OR w.site_id IS NULL OR b.site_id IS NULL)
JOIN locations l
  ON l.warehouse_id = w.id
 AND l.code = 'A01'
 AND l.company_id = b.company_id
 AND (l.site_id = b.site_id OR l.site_id IS NULL OR b.site_id IS NULL)
SET
    b.warehouse_id = w.id,
    b.location_id = l.id,
    b.updated_at = NOW()
WHERE b.item_code = 'ITEM-0003'
  AND (b.warehouse_id IS NULL OR b.location_id IS NULL OR b.warehouse_id = 0 OR b.location_id = 0)
  AND NOT EXISTS (
      SELECT 1
      FROM (
          SELECT existing_b.id
          FROM inventory_stock_balances existing_b
          JOIN warehouses existing_w ON existing_w.id = existing_b.warehouse_id
          JOIN locations existing_l ON existing_l.id = existing_b.location_id
          WHERE existing_b.item_code = b.item_code
            AND existing_b.company_id = b.company_id
            AND (existing_b.site_id = b.site_id OR existing_b.site_id IS NULL OR b.site_id IS NULL)
            AND existing_w.code = 'MAIN'
            AND existing_l.code = 'A01'
      ) already_exists
  );

/* =========================================================
   C. Repair orphan movement rows for ITEM-0003 into MAIN / A01
   ========================================================= */

UPDATE inventory_stock_movements m
JOIN warehouses w
  ON w.code = 'MAIN'
 AND w.company_id = m.company_id
 AND (w.site_id = m.site_id OR w.site_id IS NULL OR m.site_id IS NULL)
JOIN locations l
  ON l.warehouse_id = w.id
 AND l.code = 'A01'
 AND l.company_id = m.company_id
 AND (l.site_id = m.site_id OR l.site_id IS NULL OR m.site_id IS NULL)
SET
    m.warehouse_id = w.id,
    m.location_id = l.id,
    m.updated_at = NOW()
WHERE m.item_code = 'ITEM-0003'
  AND (m.warehouse_id IS NULL OR m.location_id IS NULL OR m.warehouse_id = 0 OR m.location_id = 0);

/* =========================================================
   D. Verify after repair
   ========================================================= */

SELECT
    b.item_code,
    w.code AS warehouse_code,
    l.code AS location_code,
    l.name AS location_name,
    b.qty_on_hand,
    b.qty_reserved,
    b.qty_available,
    CASE
        WHEN b.qty_available > 0 THEN 'CAN_DELIVER_FROM_THIS_LOCATION'
        ELSE 'NO_AVAILABLE_STOCK'
    END AS delivery_hint
FROM inventory_stock_balances b
JOIN warehouses w ON w.id = b.warehouse_id
JOIN locations l ON l.id = b.location_id
WHERE w.code = 'MAIN'
  AND l.code = 'A01'
  AND b.item_code IN ('ITEM-0003', 'ITEM-0006')
ORDER BY b.item_code;

SELECT
    m.item_code,
    w.code AS warehouse_code,
    l.code AS location_code,
    SUM(CASE WHEN m.direction = 'in' THEN m.qty ELSE 0 END) AS qty_in,
    SUM(CASE WHEN m.direction = 'out' THEN m.qty ELSE 0 END) AS qty_out,
    SUM(CASE WHEN m.direction = 'in' THEN m.qty ELSE -m.qty END) AS movement_balance
FROM inventory_stock_movements m
JOIN warehouses w ON w.id = m.warehouse_id
JOIN locations l ON l.id = m.location_id
WHERE w.code = 'MAIN'
  AND l.code = 'A01'
  AND m.item_code IN ('ITEM-0003', 'ITEM-0006')
GROUP BY m.item_code, w.code, l.code
ORDER BY m.item_code;
