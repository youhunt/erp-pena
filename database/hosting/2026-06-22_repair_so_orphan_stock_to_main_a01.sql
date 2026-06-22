-- Repair orphan stock balance/movement for Sales Delivery UAT.
-- Use this after running 2026-06-22_debug_sales_delivery_stock.sql when SO items have stock
-- but warehouse_code/location_code are NULL, so Create Delivery Order shows Available = 0.
--
-- Current observed case:
-- - SO item ITEM-0003 has stock, but warehouse/location is NULL.
-- - A MAIN/A01 balance row for ITEM-0003 may already exist, causing duplicate key if updated directly.
--
-- This script safely merges orphan ITEM-0003 stock into existing MAIN/A01 stock balance,
-- then deletes the orphan balance rows and repairs movement rows into MAIN/A01.
--
-- BACKUP DATABASE BEFORE RUNNING.

/* =========================================================
   A. Preview orphan and target stock for ITEM-0003
   ========================================================= */

SELECT
    b.id,
    b.company_id,
    b.site_id,
    b.item_code,
    COALESCE(b.batch_no, '') AS batch_no,
    b.warehouse_id,
    b.location_id,
    w.code AS warehouse_code,
    l.code AS location_code,
    b.qty_on_hand,
    b.qty_reserved,
    b.qty_available,
    b.stock_value
FROM inventory_stock_balances b
LEFT JOIN warehouses w ON w.id = b.warehouse_id
LEFT JOIN locations l ON l.id = b.location_id
WHERE b.item_code = 'ITEM-0003'
ORDER BY b.item_code, warehouse_code, location_code, b.id;

/* =========================================================
   B. Resolve target MAIN / A01
   ========================================================= */

SET @warehouse_id := (
    SELECT id
    FROM warehouses
    WHERE code = 'MAIN'
    ORDER BY id
    LIMIT 1
);

SET @location_id := (
    SELECT id
    FROM locations
    WHERE warehouse_id = @warehouse_id
      AND code = 'A01'
    ORDER BY id
    LIMIT 1
);

SELECT @warehouse_id AS warehouse_id, @location_id AS location_id;

/* =========================================================
   C. Merge orphan balance rows into existing MAIN / A01 balance row
   ========================================================= */

UPDATE inventory_stock_balances target
JOIN (
    SELECT
        b.company_id,
        b.site_id,
        b.item_code,
        COALESCE(b.batch_no, '') AS batch_no,
        SUM(COALESCE(b.qty_on_hand, 0)) AS orphan_qty_on_hand,
        SUM(COALESCE(b.qty_reserved, 0)) AS orphan_qty_reserved,
        SUM(COALESCE(b.qty_available, 0)) AS orphan_qty_available,
        SUM(COALESCE(b.stock_value, 0)) AS orphan_stock_value
    FROM inventory_stock_balances b
    WHERE b.item_code = 'ITEM-0003'
      AND (b.warehouse_id IS NULL OR b.location_id IS NULL OR b.warehouse_id = 0 OR b.location_id = 0)
    GROUP BY b.company_id, b.site_id, b.item_code, COALESCE(b.batch_no, '')
) orphan
  ON target.company_id = orphan.company_id
 AND (target.site_id <=> orphan.site_id)
 AND target.warehouse_id = @warehouse_id
 AND target.location_id = @location_id
 AND target.item_code = orphan.item_code
 AND COALESCE(target.batch_no, '') = orphan.batch_no
SET
    target.qty_on_hand = COALESCE(target.qty_on_hand, 0) + orphan.orphan_qty_on_hand,
    target.qty_reserved = COALESCE(target.qty_reserved, 0) + orphan.orphan_qty_reserved,
    target.qty_available = COALESCE(target.qty_available, 0) + orphan.orphan_qty_available,
    target.stock_value = COALESCE(target.stock_value, 0) + orphan.orphan_stock_value,
    target.avg_cost = CASE
        WHEN (COALESCE(target.qty_on_hand, 0) + orphan.orphan_qty_on_hand) = 0 THEN 0
        ELSE ROUND((COALESCE(target.stock_value, 0) + orphan.orphan_stock_value) / (COALESCE(target.qty_on_hand, 0) + orphan.orphan_qty_on_hand), 6)
    END,
    target.updated_at = NOW();

/* =========================================================
   D. Move remaining orphan balance rows when target did not exist
   ========================================================= */

UPDATE inventory_stock_balances b
SET
    b.warehouse_id = @warehouse_id,
    b.location_id = @location_id,
    b.updated_at = NOW()
WHERE b.item_code = 'ITEM-0003'
  AND (b.warehouse_id IS NULL OR b.location_id IS NULL OR b.warehouse_id = 0 OR b.location_id = 0)
  AND NOT EXISTS (
      SELECT 1
      FROM (
          SELECT existing_b.id
          FROM inventory_stock_balances existing_b
          WHERE existing_b.company_id = b.company_id
            AND (existing_b.site_id <=> b.site_id)
            AND existing_b.warehouse_id = @warehouse_id
            AND existing_b.location_id = @location_id
            AND existing_b.item_code = b.item_code
            AND COALESCE(existing_b.batch_no, '') = COALESCE(b.batch_no, '')
      ) already_exists
  );

/* =========================================================
   E. Delete orphan balance rows already merged into MAIN / A01
   ========================================================= */

DELETE b
FROM inventory_stock_balances b
WHERE b.item_code = 'ITEM-0003'
  AND (b.warehouse_id IS NULL OR b.location_id IS NULL OR b.warehouse_id = 0 OR b.location_id = 0);

/* =========================================================
   F. Repair orphan movement rows for ITEM-0003 into MAIN / A01
   ========================================================= */

UPDATE inventory_stock_movements m
SET
    m.warehouse_id = @warehouse_id,
    m.location_id = @location_id,
    m.updated_at = NOW()
WHERE m.item_code = 'ITEM-0003'
  AND (m.warehouse_id IS NULL OR m.location_id IS NULL OR m.warehouse_id = 0 OR m.location_id = 0);

/* =========================================================
   G. Verify after repair
   ========================================================= */

SELECT
    b.item_code,
    w.code AS warehouse_code,
    l.code AS location_code,
    l.name AS location_name,
    COALESCE(b.batch_no, '') AS batch_no,
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
