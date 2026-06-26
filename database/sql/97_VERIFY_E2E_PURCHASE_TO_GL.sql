-- ERP PENA - VERIFY E2E PURCHASE TO GL
-- Jalankan setelah melakukan test PO -> Receipt -> Stock -> GL.
-- Pilih database ERP terlebih dahulu di phpMyAdmin.
--
-- Catatan hosting/phpMyAdmin:
-- - Script ini sengaja tidak memakai SET @po_no := COALESCE(... subquery ...)
--   karena beberapa phpMyAdmin/MySQL hosting mem-parsing statement itu dengan error.
-- - Semua filter string penting memakai CONVERT(... USING utf8mb4) COLLATE utf8mb4_unicode_ci
--   untuk menghindari error illegal mix of collations.

SET @po_no = 'PO/202606/00001';
SET @item_code = 'ITEM-E2E-001';
SET @supplier_code = 'SUP-E2E';
SET @expected_qty = 10.0000;
SET @expected_stock_value = 100000.00;
SET @db = DATABASE();

SELECT
    @db AS selected_database,
    @po_no AS tested_po_no,
    @item_code AS tested_item_code,
    @supplier_code AS tested_supplier_code,
    @expected_qty AS expected_qty,
    @expected_stock_value AS expected_stock_value;

-- =========================================================
-- 1. Master setup check
-- =========================================================
SELECT 'COMPANY_TST' AS check_name, id, code, name, base_currency, is_active
FROM companies
WHERE CONVERT(code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST' COLLATE utf8mb4_unicode_ci;

SELECT 'SITE_TST01' AS check_name, s.id, s.code, s.name, s.company_id, s.is_active
FROM sites s
JOIN companies c ON c.id = s.company_id
WHERE CONVERT(c.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST' COLLATE utf8mb4_unicode_ci
  AND CONVERT(s.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST01' COLLATE utf8mb4_unicode_ci;

SELECT 'COA_REQUIRED_ACCOUNTS' AS check_name, c.code AS company_code, ca.account_no, ca.account_name, ca.is_active
FROM chart_accounts ca
JOIN companies c ON c.id = ca.company_id
WHERE CONVERT(c.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST' COLLATE utf8mb4_unicode_ci
  AND CONVERT(ca.account_no USING utf8mb4) COLLATE utf8mb4_unicode_ci IN ('1100','1300','2100','2300')
ORDER BY ca.account_no;

SELECT 'GL_POSTING_PROFILE_REQUIRED' AS check_name,
       gp.company_id, c.code AS company_code, gp.module_code, gp.posting_key,
       gp.account_no, ca.account_name, gp.is_active
FROM gl_posting_profiles gp
JOIN companies c ON c.id = gp.company_id
LEFT JOIN chart_accounts ca ON ca.company_id = gp.company_id
    AND CONVERT(ca.account_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(gp.account_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
WHERE CONVERT(c.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST' COLLATE utf8mb4_unicode_ci
  AND CONVERT(gp.module_code USING utf8mb4) COLLATE utf8mb4_unicode_ci IN ('ap','cashbank')
  AND CONVERT(gp.posting_key USING utf8mb4) COLLATE utf8mb4_unicode_ci IN ('inventory','grni','payable','cash_bank')
ORDER BY gp.module_code, gp.posting_key;

SELECT 'UOM_REQUIRED' AS check_name, u.company_id, c.code AS company_code, u.code, u.name, u.is_active
FROM uoms u
JOIN companies c ON c.id = u.company_id
WHERE CONVERT(c.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST' COLLATE utf8mb4_unicode_ci
  AND CONVERT(u.code USING utf8mb4) COLLATE utf8mb4_unicode_ci IN ('PCS','KG','MTR')
ORDER BY u.code;

SELECT 'DEPARTMENT_DPT_E2E' AS check_name, d.id, d.code, d.name, d.company_id, d.site_id, d.is_active
FROM departments d
JOIN companies c ON c.id = d.company_id
WHERE CONVERT(c.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST' COLLATE utf8mb4_unicode_ci
  AND CONVERT(d.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'DPT-E2E' COLLATE utf8mb4_unicode_ci;

SELECT 'WAREHOUSE_WH_E2E' AS check_name,
       w.id, w.code, w.name, w.company_id, w.site_id, w.department_id, d.code AS department_code, w.is_active
FROM warehouses w
LEFT JOIN departments d ON d.id = w.department_id
WHERE CONVERT(w.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'WH-E2E' COLLATE utf8mb4_unicode_ci;

SELECT 'LOCATION_LOC_E2E' AS check_name,
       l.id, l.code, l.name, l.warehouse_id, w.code AS warehouse_code, l.is_active
FROM locations l
JOIN warehouses w ON w.id = l.warehouse_id
WHERE CONVERT(w.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'WH-E2E' COLLATE utf8mb4_unicode_ci
  AND CONVERT(l.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'LOC-E2E' COLLATE utf8mb4_unicode_ci;

SELECT 'ITEM_MASTER' AS check_name,
       id, company_id, site_id, item_code, item_name, item_type, stockuom, is_active
FROM items
WHERE CONVERT(item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci;

SELECT 'SUPPLIER_MASTER' AS check_name,
       id, company_id, site_id, supplier, supplierna, is_active
FROM suppliers
WHERE CONVERT(supplier USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@supplier_code USING utf8mb4) COLLATE utf8mb4_unicode_ci;

-- =========================================================
-- 2. Purchase Order check
-- =========================================================
SELECT 'PO_HEADER' AS check_name,
       id, po_no, supplier, supplier_code, supplier_name, document_status, status, total_amount, submitted_at, approved_at
FROM purchase_orders
WHERE CONVERT(po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci;

SELECT 'PO_LINES' AS check_name,
       pol.id AS purchase_order_line_id,
       pol.item_code, pol.item_name, pol.qty_ordered, pol.qty_received, pol.qty_outstanding,
       pol.unit_price, pol.line_total, pol.line_status
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci;

-- =========================================================
-- 3. Receipt check
-- =========================================================
SELECT 'RECEIPT_HEADER' AS check_name,
       pr.id, pr.receipt_no, pr.purchase_order_id, pr.status, pr.receipt_date, pr.gl_entry_id
FROM purchase_receipts pr
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
ORDER BY pr.id DESC;

SELECT 'RECEIPT_LINES' AS check_name,
       pr.receipt_no, prl.purchase_order_line_id, prl.item_code, prl.item_name,
       prl.qty_received, prl.uom_code, prl.unit_cost, prl.warehouse_id, prl.location_id,
       prl.stock_movement_id
FROM purchase_receipt_lines prl
JOIN purchase_receipts pr ON pr.id = prl.purchase_receipt_id
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
ORDER BY prl.id;

-- =========================================================
-- 4. Stock balance / movement check
-- =========================================================
SELECT 'STOCK_BALANCE' AS check_name,
       sb.company_id, sb.site_id, sb.warehouse_id, sb.location_id, sb.item_code, sb.batch_no,
       sb.qty_on_hand, sb.qty_reserved, sb.qty_available, sb.avg_cost, sb.stock_value
FROM inventory_stock_balances sb
WHERE CONVERT(sb.item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci
ORDER BY sb.id DESC;

SELECT 'STOCK_MOVEMENT_FOR_RECEIPT' AS check_name,
       sm.id, sm.movement_date, sm.movement_type, sm.direction, sm.item_code, sm.qty,
       sm.unit_cost, sm.stock_value, sm.reference_type, sm.reference_no, sm.gl_entry_id
FROM inventory_stock_movements sm
WHERE CONVERT(sm.item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND CONVERT(sm.reference_type USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'purchase_receipt' COLLATE utf8mb4_unicode_ci
  AND CONVERT(sm.reference_no USING utf8mb4) COLLATE utf8mb4_unicode_ci IN (
      SELECT CONVERT(pr.receipt_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
  )
ORDER BY sm.id DESC;

-- =========================================================
-- 5. GL header / lines check
-- =========================================================
SELECT 'GL_HEADER' AS check_name,
       ge.id, ge.journal_no, ge.journal_date, ge.source_module, ge.source_type, ge.source_no,
       ge.total_debit, ge.total_credit, ge.status, ge.description
FROM gl_entries ge
WHERE CONVERT(ge.source_no USING utf8mb4) COLLATE utf8mb4_unicode_ci IN (
      SELECT CONVERT(pr.receipt_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
)
ORDER BY ge.id DESC;

SELECT 'GL_LINES' AS check_name,
       ge.journal_no, ge.source_no, gel.account_no, gel.account_name,
       gel.debit, gel.credit, gel.description
FROM gl_entry_lines gel
JOIN gl_entries ge ON ge.id = gel.gl_entry_id
WHERE CONVERT(ge.source_no USING utf8mb4) COLLATE utf8mb4_unicode_ci IN (
      SELECT CONVERT(pr.receipt_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
)
ORDER BY gel.id;

SELECT 'GL_BALANCE' AS check_name,
       ge.journal_no,
       SUM(COALESCE(gel.debit, 0)) AS total_debit,
       SUM(COALESCE(gel.credit, 0)) AS total_credit,
       SUM(COALESCE(gel.debit, 0)) - SUM(COALESCE(gel.credit, 0)) AS diff
FROM gl_entries ge
JOIN gl_entry_lines gel ON gel.gl_entry_id = ge.id
WHERE CONVERT(ge.source_no USING utf8mb4) COLLATE utf8mb4_unicode_ci IN (
      SELECT CONVERT(pr.receipt_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
)
GROUP BY ge.journal_no;

SELECT 'GL_EXPECTED_INVENTORY_GRNI' AS check_name,
       SUM(CASE WHEN CONVERT(gel.account_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = '1300' COLLATE utf8mb4_unicode_ci
                 AND ROUND(COALESCE(gel.debit, 0), 2) = @expected_stock_value
                 AND ROUND(COALESCE(gel.credit, 0), 2) = 0 THEN 1 ELSE 0 END) AS inventory_debit_ok,
       SUM(CASE WHEN CONVERT(gel.account_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = '2300' COLLATE utf8mb4_unicode_ci
                 AND ROUND(COALESCE(gel.credit, 0), 2) = @expected_stock_value
                 AND ROUND(COALESCE(gel.debit, 0), 2) = 0 THEN 1 ELSE 0 END) AS grni_credit_ok,
       'Expected: Inventory Dr 100000, GRNI Cr 100000' AS expected
FROM gl_entry_lines gel
JOIN gl_entries ge ON ge.id = gel.gl_entry_id
WHERE CONVERT(ge.source_no USING utf8mb4) COLLATE utf8mb4_unicode_ci IN (
      SELECT CONVERT(pr.receipt_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
);

-- =========================================================
-- 6. Summary indicators
-- =========================================================
SELECT 'SUMMARY_MASTER_READY' AS check_name,
       (
           (SELECT COUNT(*) FROM companies WHERE CONVERT(code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST' COLLATE utf8mb4_unicode_ci) +
           (SELECT COUNT(*) FROM sites s JOIN companies c ON c.id = s.company_id WHERE CONVERT(c.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST' COLLATE utf8mb4_unicode_ci AND CONVERT(s.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST01' COLLATE utf8mb4_unicode_ci) +
           (SELECT COUNT(*) FROM departments d JOIN companies c ON c.id = d.company_id WHERE CONVERT(c.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'TST' COLLATE utf8mb4_unicode_ci AND CONVERT(d.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'DPT-E2E' COLLATE utf8mb4_unicode_ci) +
           (SELECT COUNT(*) FROM warehouses WHERE CONVERT(code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'WH-E2E' COLLATE utf8mb4_unicode_ci) +
           (SELECT COUNT(*) FROM locations l JOIN warehouses w ON w.id = l.warehouse_id WHERE CONVERT(w.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'WH-E2E' COLLATE utf8mb4_unicode_ci AND CONVERT(l.code USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'LOC-E2E' COLLATE utf8mb4_unicode_ci) +
           (SELECT COUNT(*) FROM items WHERE CONVERT(item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci) +
           (SELECT COUNT(*) FROM suppliers WHERE CONVERT(supplier USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@supplier_code USING utf8mb4) COLLATE utf8mb4_unicode_ci)
       ) AS ok_count,
       'Expected >= 7 master records ready' AS expected;

SELECT 'SUMMARY_PO_HAS_SUPPLIER' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 PO header with supplier SUP-E2E' AS expected
FROM purchase_orders
WHERE CONVERT(po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND (
      CONVERT(supplier USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@supplier_code USING utf8mb4) COLLATE utf8mb4_unicode_ci
      OR CONVERT(supplier_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@supplier_code USING utf8mb4) COLLATE utf8mb4_unicode_ci
  );

SELECT 'SUMMARY_PO_SUBMITTED_APPROVED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 PO has passed submit/approve; final status may be received after receipt' AS expected
FROM purchase_orders
WHERE CONVERT(po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND submitted_at IS NOT NULL
  AND approved_at IS NOT NULL
  AND CONVERT(document_status USING utf8mb4) COLLATE utf8mb4_unicode_ci IN ('approved','partial_received','received','closed');

SELECT 'SUMMARY_RECEIPT_POSTED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 posted purchase receipt' AS expected
FROM purchase_receipts pr
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND CONVERT(pr.status USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'posted' COLLATE utf8mb4_unicode_ci;

SELECT 'SUMMARY_RECEIPT_LINE_POSTED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 receipt line for ITEM-E2E-001 qty 10' AS expected
FROM purchase_receipt_lines prl
JOIN purchase_receipts pr ON pr.id = prl.purchase_receipt_id
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND CONVERT(pr.status USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'posted' COLLATE utf8mb4_unicode_ci
  AND CONVERT(prl.item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND ROUND(COALESCE(prl.qty_received, 0), 4) = @expected_qty;

SELECT 'SUMMARY_PO_RECEIVED_FULL' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1, qty_received = 10 and qty_outstanding = 0 for full receipt' AS expected
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND CONVERT(pol.item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND ROUND(COALESCE(pol.qty_received, 0), 4) >= @expected_qty
  AND ROUND(COALESCE(pol.qty_outstanding, 0), 4) = 0;

SELECT 'SUMMARY_STOCK_UPDATED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 stock balance with qty_on_hand >= 10 and stock_value >= 100000' AS expected
FROM inventory_stock_balances
WHERE CONVERT(item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND ROUND(COALESCE(qty_on_hand, 0), 4) >= @expected_qty
  AND ROUND(COALESCE(stock_value, 0), 2) >= @expected_stock_value;

SELECT 'SUMMARY_STOCK_MOVEMENT_CREATED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 stock movement from purchase receipt qty 10 value 100000' AS expected
FROM inventory_stock_movements sm
WHERE CONVERT(sm.item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@item_code USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND CONVERT(sm.reference_type USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'purchase_receipt' COLLATE utf8mb4_unicode_ci
  AND ROUND(COALESCE(sm.qty, 0), 4) = @expected_qty
  AND ROUND(COALESCE(sm.stock_value, 0), 2) = @expected_stock_value
  AND CONVERT(sm.reference_no USING utf8mb4) COLLATE utf8mb4_unicode_ci IN (
      SELECT CONVERT(pr.receipt_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
  );

SELECT 'SUMMARY_GL_BALANCED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 balanced GL journal diff = 0' AS expected
FROM (
    SELECT ge.id,
           ROUND(SUM(COALESCE(gel.debit, 0)) - SUM(COALESCE(gel.credit, 0)), 2) AS diff
    FROM gl_entries ge
    JOIN gl_entry_lines gel ON gel.gl_entry_id = ge.id
    WHERE CONVERT(ge.source_no USING utf8mb4) COLLATE utf8mb4_unicode_ci IN (
        SELECT CONVERT(pr.receipt_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
        FROM purchase_receipts pr
        JOIN purchase_orders po ON po.id = pr.purchase_order_id
        WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
    )
    GROUP BY ge.id
    HAVING diff = 0
) x;

SELECT 'SUMMARY_GL_EXPECTED_AMOUNT' AS check_name,
       CASE
           WHEN
               SUM(CASE WHEN CONVERT(gel.account_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = '1300' COLLATE utf8mb4_unicode_ci
                         AND ROUND(COALESCE(gel.debit, 0), 2) = @expected_stock_value
                         AND ROUND(COALESCE(gel.credit, 0), 2) = 0 THEN 1 ELSE 0 END) >= 1
               AND
               SUM(CASE WHEN CONVERT(gel.account_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = '2300' COLLATE utf8mb4_unicode_ci
                         AND ROUND(COALESCE(gel.credit, 0), 2) = @expected_stock_value
                         AND ROUND(COALESCE(gel.debit, 0), 2) = 0 THEN 1 ELSE 0 END) >= 1
           THEN 1 ELSE 0
       END AS ok_count,
       'Expected Inventory Dr 100000 and GRNI Cr 100000' AS expected
FROM gl_entry_lines gel
JOIN gl_entries ge ON ge.id = gel.gl_entry_id
WHERE CONVERT(ge.source_no USING utf8mb4) COLLATE utf8mb4_unicode_ci IN (
    SELECT CONVERT(pr.receipt_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
    FROM purchase_receipts pr
    JOIN purchase_orders po ON po.id = pr.purchase_order_id
    WHERE CONVERT(po.po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci
);
