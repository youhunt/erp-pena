-- ERP PENA - Operational fixes for PO001 and MRJ/RMJ location
-- 1) Reset PO001 back to draft only if it has no posted receipt.
-- 2) Rename location FGLJ to MRLJ for warehouse MRJ/RMJ.

USE `dberp_pena`;

-- =========================================================
-- A. Inspect PO001 before reset
-- =========================================================
SELECT
    po.id,
    po.po_no,
    po.status,
    po.document_status,
    COUNT(pr.id) AS posted_receipt_count
FROM purchase_orders po
LEFT JOIN purchase_receipts pr
    ON pr.purchase_order_id = po.id
   AND pr.status = 'posted'
WHERE po.po_no = 'PO001'
GROUP BY po.id, po.po_no, po.status, po.document_status;

-- Reset header to draft only when no posted receipt exists.
UPDATE purchase_orders po
LEFT JOIN (
    SELECT purchase_order_id, COUNT(*) AS receipt_count
    FROM purchase_receipts
    WHERE status = 'posted'
    GROUP BY purchase_order_id
) pr ON pr.purchase_order_id = po.id
SET
    po.status = 'draft',
    po.document_status = 'draft',
    po.submitted_at = NULL,
    po.submitted_by = NULL,
    po.approved_at = NULL,
    po.approved_by = NULL,
    po.updated_at = NOW()
WHERE po.po_no = 'PO001'
  AND COALESCE(pr.receipt_count, 0) = 0;

-- Reset lines only when PO001 has no posted receipt.
UPDATE purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
LEFT JOIN (
    SELECT purchase_order_id, COUNT(*) AS receipt_count
    FROM purchase_receipts
    WHERE status = 'posted'
    GROUP BY purchase_order_id
) pr ON pr.purchase_order_id = po.id
SET
    pol.qty_received = 0,
    pol.qty_outstanding = COALESCE(pol.qty_ordered, pol.qty, 0),
    pol.line_status = 'open',
    pol.updated_at = NOW()
WHERE po.po_no = 'PO001'
  AND COALESCE(pr.receipt_count, 0) = 0;

SELECT
    po.id,
    po.po_no,
    po.status,
    po.document_status,
    'PO001_RESET_DONE_IF_NO_RECEIPT' AS result
FROM purchase_orders po
WHERE po.po_no = 'PO001';

-- =========================================================
-- B. Location correction: FGLJ should be MRLJ under MRJ/RMJ warehouse
-- =========================================================
SELECT
    l.id AS location_id,
    w.code AS warehouse_code,
    l.code AS location_code,
    l.name AS location_name
FROM locations l
JOIN warehouses w ON w.id = l.warehouse_id
WHERE w.code IN ('MRJ', 'RMJ')
  AND l.code IN ('FGLJ', 'MRLJ');

UPDATE locations l
JOIN warehouses w ON w.id = l.warehouse_id
SET
    l.code = 'MRLJ',
    l.name = CASE WHEN l.name IS NULL OR l.name = '' OR l.name = 'FGLJ' THEN 'MRLJ' ELSE REPLACE(l.name, 'FGLJ', 'MRLJ') END,
    l.updated_at = NOW()
WHERE w.code IN ('MRJ', 'RMJ')
  AND l.code = 'FGLJ';

SELECT
    l.id AS location_id,
    w.code AS warehouse_code,
    l.code AS location_code,
    l.name AS location_name,
    'LOCATION_MRLJ_READY' AS result
FROM locations l
JOIN warehouses w ON w.id = l.warehouse_id
WHERE w.code IN ('MRJ', 'RMJ')
  AND l.code = 'MRLJ';
