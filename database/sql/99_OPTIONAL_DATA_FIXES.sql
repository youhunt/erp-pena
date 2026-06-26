-- ERP PENA - OPTIONAL DATA FIXES
-- Jalankan hanya kalau memang butuh memperbaiki data demo/testing tertentu.
-- Jangan dijalankan sebagai setup awal database.
--
-- PENTING UNTUK PHPMYADMIN / CPANEL:
-- Pilih database ERP terlebih dahulu dari sidebar kiri.
-- File ini sengaja TIDAK memakai USE `nama_database` karena nama DB hosting bisa berbeda.

SET @selected_database := DATABASE();
SELECT @selected_database AS selected_database;

-- =========================================================
-- A. Reset PO001 back to draft, only if it has no posted receipt
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

SELECT po.id, po.po_no, po.status, po.document_status, 'PO001_RESET_DONE_IF_NO_POSTED_RECEIPT' AS result
FROM purchase_orders po
WHERE po.po_no = 'PO001';

-- =========================================================
-- B. Rename location FGLJ to MRLJ under warehouse MRJ/RMJ
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

-- =========================================================
-- C. Ensure every active warehouse has at least one location
-- =========================================================
INSERT INTO locations (
    company_id,
    site_id,
    warehouse_id,
    code,
    name,
    description,
    is_active,
    created_by,
    updated_by,
    created_at,
    updated_at
)
SELECT
    w.company_id,
    w.site_id,
    w.id,
    CASE
        WHEN existing_code.id IS NULL THEN w.code
        ELSE CONCAT('LOC', w.id)
    END,
    CASE
        WHEN existing_code.id IS NULL THEN CONCAT(w.code, ' Location')
        ELSE CONCAT('LOC', w.id, ' Location')
    END,
    CONCAT('Default location for ', COALESCE(w.code, CONCAT('warehouse #', w.id))),
    1,
    1,
    1,
    NOW(),
    NOW()
FROM warehouses w
LEFT JOIN locations l
    ON l.warehouse_id = w.id
   AND l.deleted_at IS NULL
   AND COALESCE(l.is_active, 1) = 1
LEFT JOIN locations existing_code
    ON existing_code.company_id = w.company_id
   AND existing_code.site_id = w.site_id
   AND existing_code.code = w.code
   AND existing_code.deleted_at IS NULL
WHERE w.deleted_at IS NULL
  AND COALESCE(w.is_active, 1) = 1
  AND l.id IS NULL;

SELECT
    w.id AS warehouse_id,
    w.code AS warehouse_code,
    COUNT(l.id) AS active_location_count,
    'WAREHOUSE_LOCATION_READY' AS result
FROM warehouses w
LEFT JOIN locations l
    ON l.warehouse_id = w.id
   AND l.deleted_at IS NULL
   AND COALESCE(l.is_active, 1) = 1
WHERE w.deleted_at IS NULL
  AND COALESCE(w.is_active, 1) = 1
GROUP BY w.id, w.code
ORDER BY w.code;
