-- Diagnostic SQL for Sales Delivery available stock issue.
-- Use this when Create Delivery Order shows Available = 0 even after Stock Adjustment.
-- Replace the SO number and item codes if needed.

/* =========================================================
   1. Check Sales Order lines and outstanding qty
   ========================================================= */

SELECT
    so.id AS sales_order_id,
    so.so_no,
    so.customer_name,
    sol.id AS sales_order_line_id,
    sol.line_no,
    sol.item_code,
    sol.item_name,
    sol.qty_ordered,
    sol.qty_reserved,
    sol.qty_delivered,
    sol.qty_outstanding,
    sol.uom_code
FROM sales_orders so
JOIN sales_order_lines sol ON sol.sales_order_id = so.id
WHERE so.so_no = 'SO-20260605-020255'
ORDER BY sol.line_no;

/* =========================================================
   2. Check current stock balance for SO items by warehouse/location
   ========================================================= */

SELECT
    b.item_code,
    i.name AS item_name,
    w.id AS warehouse_id,
    w.code AS warehouse_code,
    w.name AS warehouse_name,
    l.id AS location_id,
    l.code AS location_code,
    l.name AS location_name,
    b.qty_on_hand,
    b.qty_reserved,
    b.qty_available,
    b.avg_cost,
    b.stock_value,
    b.updated_at
FROM inventory_stock_balances b
LEFT JOIN items i ON i.code = b.item_code AND i.company_id = b.company_id
LEFT JOIN warehouses w ON w.id = b.warehouse_id
LEFT JOIN locations l ON l.id = b.location_id
WHERE b.item_code IN ('ITEM-0003', 'ITEM-0006')
ORDER BY b.item_code, w.code, l.code;

/* =========================================================
   3. Check stock movements for SO items by warehouse/location
   ========================================================= */

SELECT
    m.item_code,
    w.code AS warehouse_code,
    l.code AS location_code,
    SUM(CASE WHEN m.direction = 'in' THEN m.qty ELSE 0 END) AS qty_in,
    SUM(CASE WHEN m.direction = 'out' THEN m.qty ELSE 0 END) AS qty_out,
    SUM(CASE WHEN m.direction = 'in' THEN m.qty ELSE -m.qty END) AS movement_balance,
    COUNT(*) AS movement_count,
    MAX(m.movement_date) AS last_movement_date
FROM inventory_stock_movements m
LEFT JOIN warehouses w ON w.id = m.warehouse_id
LEFT JOIN locations l ON l.id = m.location_id
WHERE m.item_code IN ('ITEM-0003', 'ITEM-0006')
GROUP BY m.item_code, w.code, l.code
ORDER BY m.item_code, w.code, l.code;

/* =========================================================
   4. Find stock available in any location under MAIN warehouse
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
LEFT JOIN locations l ON l.id = b.location_id
WHERE w.code = 'MAIN'
  AND b.item_code IN ('ITEM-0003', 'ITEM-0006')
ORDER BY b.item_code, b.qty_available DESC, l.code;

/* =========================================================
   5. Compare balance table vs movement-derived balance
   ========================================================= */

SELECT
    COALESCE(b.item_code, mv.item_code) AS item_code,
    COALESCE(wb.code, wm.code) AS warehouse_code,
    COALESCE(lb.code, lm.code) AS location_code,
    COALESCE(b.qty_on_hand, 0) AS balance_qty_on_hand,
    COALESCE(b.qty_available, 0) AS balance_qty_available,
    COALESCE(mv.movement_balance, 0) AS movement_balance,
    COALESCE(b.qty_on_hand, 0) - COALESCE(mv.movement_balance, 0) AS difference
FROM inventory_stock_balances b
LEFT JOIN warehouses wb ON wb.id = b.warehouse_id
LEFT JOIN locations lb ON lb.id = b.location_id
LEFT JOIN (
    SELECT
        item_code,
        warehouse_id,
        location_id,
        SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END) AS movement_balance
    FROM inventory_stock_movements
    WHERE item_code IN ('ITEM-0003', 'ITEM-0006')
    GROUP BY item_code, warehouse_id, location_id
) mv ON mv.item_code = b.item_code
    AND mv.warehouse_id = b.warehouse_id
    AND mv.location_id = b.location_id
LEFT JOIN warehouses wm ON wm.id = mv.warehouse_id
LEFT JOIN locations lm ON lm.id = mv.location_id
WHERE b.item_code IN ('ITEM-0003', 'ITEM-0006')
ORDER BY item_code, warehouse_code, location_code;
