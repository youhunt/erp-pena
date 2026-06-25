-- ERP PENA - Map imported PO lines to item master when item_code is missing.
-- This does not use item_name as item_code. It resolves the real item_code from items.

USE `dberp_pena`;

UPDATE purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
JOIN items i ON i.item_name = pol.item_name
SET
    pol.item_id = i.id,
    pol.item_code = i.item_code,
    pol.item_name = i.item_name,
    pol.uom_code = COALESCE(NULLIF(pol.uom_code, ''), NULLIF(i.stockuom, ''), 'PCS')
WHERE (pol.item_code IS NULL OR TRIM(pol.item_code) = '')
  AND pol.item_name IS NOT NULL
  AND TRIM(pol.item_name) <> ''
  AND (i.company_id = po.company_id OR i.company_id IS NULL)
  AND (i.site_id = po.site_id OR i.site_id IS NULL OR i.site_id = 0);

SELECT
    po.po_no,
    pol.id,
    pol.line_no,
    pol.po_line,
    pol.item_id,
    pol.item_code,
    pol.item_name,
    pol.uom_code,
    pol.qty_ordered,
    pol.qty_outstanding
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE po.po_no = 'PO001'
ORDER BY pol.line_no, pol.po_line, pol.id;
