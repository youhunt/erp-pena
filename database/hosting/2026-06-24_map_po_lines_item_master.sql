-- ERP PENA - Map imported PO lines to item master when item_code is missing.
-- This does not use item_name as item_code. It resolves the real item_code from items.
-- Matching uses exact match first and normalized-name match for imported Excel text.

USE `dberp_pena`;

-- 1) Exact item_name match.
UPDATE purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
JOIN items i ON TRIM(i.item_name) = TRIM(pol.item_name)
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

-- 2) Normalized item_name match: removes spaces, dots, commas, slashes, dashes, CR/LF.
UPDATE purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
JOIN items i ON
    LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(i.item_name), CHAR(13), ''), CHAR(10), ''), ' ', ''), '.', ''), ',', ''), '-', ''), '/', ''))
    =
    LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(pol.item_name), CHAR(13), ''), CHAR(10), ''), ' ', ''), '.', ''), ',', ''), '-', ''), '/', ''))
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

-- 3) Show current PO001 lines.
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

-- 4) Show unresolved imported PO lines that still need manual item master correction.
SELECT
    po.po_no,
    pol.id,
    pol.line_no,
    pol.po_line,
    pol.item_id,
    pol.item_code,
    pol.item_name,
    pol.description,
    pol.uom_code
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE (pol.item_code IS NULL OR TRIM(pol.item_code) = '')
  AND pol.item_name IS NOT NULL
  AND TRIM(pol.item_name) <> ''
ORDER BY po.po_no, pol.line_no, pol.po_line, pol.id;
