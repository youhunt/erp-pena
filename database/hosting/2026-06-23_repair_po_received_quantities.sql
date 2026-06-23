-- Repair PO received quantities from posted purchase receipts
-- Jalankan setelah backup database.
-- Tujuan: memperbaiki PO lama yang sudah punya Purchase Receipt tetapi qty_received / qty_outstanding di purchase_order_lines belum ikut terupdate.

UPDATE purchase_order_lines pol
LEFT JOIN (
    SELECT
        prl.purchase_order_line_id,
        prl.purchase_order_id,
        prl.line_no,
        SUM(COALESCE(prl.qty_received, 0) - COALESCE(prl.reversed_qty, 0)) AS received_qty
    FROM purchase_receipt_lines prl
    INNER JOIN purchase_receipts pr ON pr.id = prl.purchase_receipt_id
    WHERE pr.status = 'posted'
      AND (pr.reversed_at IS NULL OR pr.reversed_at = '')
    GROUP BY prl.purchase_order_line_id, prl.purchase_order_id, prl.line_no
) r ON r.purchase_order_line_id = pol.id
   OR (
        r.purchase_order_line_id IS NULL
        AND r.purchase_order_id = pol.purchase_order_id
        AND r.line_no = pol.line_no
   )
SET
    pol.qty_received = ROUND(COALESCE(r.received_qty, 0), 4),
    pol.qty_outstanding = GREATEST(0, ROUND(COALESCE(pol.qty_ordered, pol.qty, 0) - COALESCE(r.received_qty, 0), 4)),
    pol.line_status = CASE
        WHEN COALESCE(r.received_qty, 0) <= 0 THEN 'open'
        WHEN GREATEST(0, ROUND(COALESCE(pol.qty_ordered, pol.qty, 0) - COALESCE(r.received_qty, 0), 4)) <= 0 THEN 'received'
        ELSE 'partial_received'
    END,
    pol.updated_at = NOW();

UPDATE purchase_orders po
LEFT JOIN (
    SELECT
        purchase_order_id,
        SUM(COALESCE(qty_received, 0)) AS total_received,
        SUM(COALESCE(qty_outstanding, 0)) AS total_outstanding
    FROM purchase_order_lines
    GROUP BY purchase_order_id
) s ON s.purchase_order_id = po.id
SET
    po.status = CASE
        WHEN COALESCE(s.total_received, 0) <= 0 THEN po.status
        WHEN COALESCE(s.total_outstanding, 0) <= 0 THEN 'received'
        ELSE 'partial_received'
    END,
    po.document_status = CASE
        WHEN COALESCE(s.total_received, 0) <= 0 THEN po.document_status
        WHEN COALESCE(s.total_outstanding, 0) <= 0 THEN 'received'
        ELSE 'partial_received'
    END,
    po.updated_at = NOW()
WHERE COALESCE(s.total_received, 0) > 0
  AND po.status NOT IN ('cancelled', 'closed');
