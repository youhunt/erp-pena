-- ERP PENA - VERIFY E2E PURCHASE TO GL
-- Jalankan setelah melakukan test PO -> Receipt -> Stock -> GL.
-- Pilih database ERP terlebih dahulu di phpMyAdmin.
-- Default mengikuti test aktif: PO/2026/00001, ITEM-E2E-001, SUP-E2E.
-- Jika nomor PO berbeda, ubah nilai @po_no di bawah ini.

SET @po_no := 'PO/2026/00001';
SET @item_code := 'ITEM-E2E-001';
SET @supplier_code := 'SUP-E2E';
SET @expected_qty := 10.0000;
SET @expected_unit_price := 10000.00;
SET @expected_stock_value := 100000.00;
SET @db := DATABASE();

-- Kalau @po_no dikosongkan, script akan coba ambil PO E2E terakhir berdasarkan supplier/item test.
SET @po_no := COALESCE(
    NULLIF(@po_no, ''),
    (
        SELECT po.po_no
        FROM purchase_orders po
        JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
        WHERE (po.supplier = @supplier_code OR po.supplier_code = @supplier_code)
          AND pol.item_code = @item_code
        ORDER BY po.id DESC
        LIMIT 1
    )
);

SELECT
    @db AS selected_database,
    @po_no AS tested_po_no,
    @item_code AS tested_item_code,
    @supplier_code AS tested_supplier_code,
    @expected_qty AS expected_qty,
    @expected_unit_price AS expected_unit_price,
    @expected_stock_value AS expected_stock_value;

-- =========================================================
-- 1. Company / Site basic check
-- =========================================================
SELECT 'COMPANY_TST' AS check_name, id, code, name, base_currency, is_active
FROM companies
WHERE code = 'TST';

SELECT 'SITE_TST01' AS check_name, s.id, s.code, s.name, s.company_id, s.is_active
FROM sites s
JOIN companies c ON c.id = s.company_id
WHERE c.code = 'TST'
  AND s.code = 'TST01';

-- =========================================================
-- 2. COA check
-- =========================================================
SELECT 'COA_REQUIRED_ACCOUNTS' AS check_name, c.code AS company_code, ca.account_no, ca.account_name, ca.is_active
FROM chart_accounts ca
JOIN companies c ON c.id = ca.company_id
WHERE c.code = 'TST'
  AND ca.account_no IN ('1100','1300','2100','2300')
ORDER BY ca.account_no;

-- =========================================================
-- 3. GL Posting Profile check
-- =========================================================
SELECT 'GL_POSTING_PROFILE_REQUIRED' AS check_name,
       gp.company_id, c.code AS company_code, gp.module_code, gp.posting_key,
       gp.account_no, ca.account_name, gp.is_active
FROM gl_posting_profiles gp
JOIN companies c ON c.id = gp.company_id
LEFT JOIN chart_accounts ca ON ca.company_id = gp.company_id AND ca.account_no = gp.account_no
WHERE c.code = 'TST'
  AND gp.module_code IN ('ap','cashbank')
  AND gp.posting_key IN ('inventory','grni','payable','cash_bank')
ORDER BY gp.module_code, gp.posting_key;

-- =========================================================
-- 4. UOM check
-- =========================================================
SELECT 'UOM_REQUIRED' AS check_name,
       u.company_id, c.code AS company_code, u.code, u.name, u.is_active
FROM uoms u
JOIN companies c ON c.id = u.company_id
WHERE c.code = 'TST'
  AND u.code IN ('PCS','KG','MTR')
ORDER BY u.code;

-- =========================================================
-- 5. Department check
-- =========================================================
SELECT 'DEPARTMENT_DPT_E2E' AS check_name,
       d.id, d.code, d.name, d.company_id, d.site_id, d.is_active
FROM departments d
JOIN companies c ON c.id = d.company_id
WHERE c.code = 'TST'
  AND d.code = 'DPT-E2E';

-- =========================================================
-- 6. Warehouse / Location check
-- =========================================================
SELECT 'WAREHOUSE_WH_E2E' AS check_name,
       w.id, w.code, w.name, w.company_id, w.site_id, w.department_id, d.code AS department_code, w.is_active
FROM warehouses w
LEFT JOIN departments d ON d.id = w.department_id
WHERE w.code = 'WH-E2E';

SELECT 'LOCATION_LOC_E2E' AS check_name,
       l.id, l.code, l.name, l.warehouse_id, w.code AS warehouse_code, l.is_active
FROM locations l
JOIN warehouses w ON w.id = l.warehouse_id
WHERE w.code = 'WH-E2E'
  AND l.code = 'LOC-E2E';

-- =========================================================
-- 7. Item Master check
-- =========================================================
SELECT 'ITEM_MASTER' AS check_name,
       id, company_id, site_id, item_code, item_name, item_type, stockuom, is_active
FROM items
WHERE item_code = @item_code;

-- =========================================================
-- 8. Supplier Master check
-- =========================================================
SELECT 'SUPPLIER_MASTER' AS check_name,
       id, company_id, site_id, supplier, supplierna, is_active
FROM suppliers
WHERE supplier = @supplier_code;

-- =========================================================
-- 9. PO header / lines check
-- =========================================================
SELECT 'PO_HEADER' AS check_name,
       id, po_no, supplier, supplier_code, supplier_name, document_status, status, total_amount, submitted_at, approved_at
FROM purchase_orders
WHERE po_no = @po_no;

SELECT 'PO_LINES' AS check_name,
       pol.id AS purchase_order_line_id,
       pol.item_code, pol.item_name, pol.qty_ordered, pol.qty_received, pol.qty_outstanding,
       pol.unit_price, pol.line_total, pol.line_status
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE po.po_no = @po_no;

-- =========================================================
-- 10. Receipt check
-- =========================================================
SELECT 'RECEIPT_HEADER' AS check_name,
       pr.id, pr.receipt_no, pr.purchase_order_id, pr.status, pr.receipt_date, pr.gl_entry_id
FROM purchase_receipts pr
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE po.po_no = @po_no
ORDER BY pr.id DESC;

SELECT 'RECEIPT_LINES' AS check_name,
       pr.receipt_no, prl.purchase_order_line_id, prl.item_code, prl.item_name,
       prl.qty_received, prl.uom_code, prl.unit_cost, prl.warehouse_id, prl.location_id,
       prl.stock_movement_id
FROM purchase_receipt_lines prl
JOIN purchase_receipts pr ON pr.id = prl.purchase_receipt_id
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE po.po_no = @po_no
ORDER BY prl.id;

-- =========================================================
-- 11. Stock balance / movement check
-- =========================================================
SELECT 'STOCK_BALANCE' AS check_name,
       sb.company_id, sb.site_id, sb.warehouse_id, sb.location_id, sb.item_code, sb.batch_no,
       sb.qty_on_hand, sb.qty_reserved, sb.qty_available, sb.avg_cost, sb.stock_value
FROM inventory_stock_balances sb
WHERE sb.item_code = @item_code
ORDER BY sb.id DESC;

SELECT 'STOCK_MOVEMENT_FOR_RECEIPT' AS check_name,
       sm.id, sm.movement_date, sm.movement_type, sm.direction, sm.item_code, sm.qty,
       sm.unit_cost, sm.stock_value, sm.reference_type, sm.reference_no, sm.gl_entry_id
FROM inventory_stock_movements sm
WHERE sm.item_code = @item_code
  AND sm.reference_type = 'purchase_receipt'
  AND sm.reference_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = @po_no
  )
ORDER BY sm.id DESC;

-- =========================================================
-- 12. GL header / lines check
-- =========================================================
SELECT 'GL_HEADER' AS check_name,
       ge.id, ge.journal_no, ge.journal_date, ge.source_module, ge.source_type, ge.source_no,
       ge.total_debit, ge.total_credit, ge.status, ge.description
FROM gl_entries ge
WHERE ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = @po_no
)
ORDER BY ge.id DESC;

SELECT 'GL_LINES' AS check_name,
       ge.journal_no, ge.source_no, gel.account_no, gel.account_name,
       gel.debit, gel.credit, gel.description
FROM gl_entry_lines gel
JOIN gl_entries ge ON ge.id = gel.gl_entry_id
WHERE ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = @po_no
)
ORDER BY gel.id;

SELECT 'GL_BALANCE' AS check_name,
       ge.journal_no,
       SUM(COALESCE(gel.debit, 0)) AS total_debit,
       SUM(COALESCE(gel.credit, 0)) AS total_credit,
       SUM(COALESCE(gel.debit, 0)) - SUM(COALESCE(gel.credit, 0)) AS diff
FROM gl_entries ge
JOIN gl_entry_lines gel ON gel.gl_entry_id = ge.id
WHERE ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = @po_no
)
GROUP BY ge.journal_no;

SELECT 'GL_EXPECTED_INVENTORY_GRNI' AS check_name,
       SUM(CASE WHEN gel.account_no = '1300' AND ROUND(COALESCE(gel.debit, 0), 2) = @expected_stock_value AND ROUND(COALESCE(gel.credit, 0), 2) = 0 THEN 1 ELSE 0 END) AS inventory_debit_ok,
       SUM(CASE WHEN gel.account_no = '2300' AND ROUND(COALESCE(gel.credit, 0), 2) = @expected_stock_value AND ROUND(COALESCE(gel.debit, 0), 2) = 0 THEN 1 ELSE 0 END) AS grni_credit_ok,
       'Expected: Inventory Dr 100000, GRNI Cr 100000' AS expected
FROM gl_entry_lines gel
JOIN gl_entries ge ON ge.id = gel.gl_entry_id
WHERE ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = @po_no
);

-- =========================================================
-- 13. Summary indicators
-- =========================================================
SELECT 'SUMMARY_MASTER_READY' AS check_name,
       (
           (SELECT COUNT(*) FROM companies WHERE code = 'TST') +
           (SELECT COUNT(*) FROM sites s JOIN companies c ON c.id = s.company_id WHERE c.code = 'TST' AND s.code = 'TST01') +
           (SELECT COUNT(*) FROM departments d JOIN companies c ON c.id = d.company_id WHERE c.code = 'TST' AND d.code = 'DPT-E2E') +
           (SELECT COUNT(*) FROM warehouses WHERE code = 'WH-E2E') +
           (SELECT COUNT(*) FROM locations l JOIN warehouses w ON w.id = l.warehouse_id WHERE w.code = 'WH-E2E' AND l.code = 'LOC-E2E') +
           (SELECT COUNT(*) FROM items WHERE item_code = @item_code) +
           (SELECT COUNT(*) FROM suppliers WHERE supplier = @supplier_code)
       ) AS ok_count,
       'Expected >= 7 master records ready' AS expected;

SELECT 'SUMMARY_PO_HAS_SUPPLIER' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 PO header with supplier SUP-E2E' AS expected
FROM purchase_orders
WHERE po_no = @po_no
  AND (supplier = @supplier_code OR supplier_code = @supplier_code);

SELECT 'SUMMARY_PO_SUBMITTED_APPROVED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 PO has passed submit/approve; final status may be received after receipt' AS expected
FROM purchase_orders
WHERE po_no = @po_no
  AND submitted_at IS NOT NULL
  AND approved_at IS NOT NULL
  AND document_status IN ('approved','partial_received','received','closed');

SELECT 'SUMMARY_RECEIPT_POSTED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 posted purchase receipt' AS expected
FROM purchase_receipts pr
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE po.po_no = @po_no
  AND pr.status = 'posted';

SELECT 'SUMMARY_RECEIPT_LINE_POSTED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 receipt line for ITEM-E2E-001 qty 10' AS expected
FROM purchase_receipt_lines prl
JOIN purchase_receipts pr ON pr.id = prl.purchase_receipt_id
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE po.po_no = @po_no
  AND pr.status = 'posted'
  AND prl.item_code = @item_code
  AND ROUND(COALESCE(prl.qty_received, 0), 4) = @expected_qty;

SELECT 'SUMMARY_PO_RECEIVED_FULL' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1, qty_received = 10 and qty_outstanding = 0 for full receipt' AS expected
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE po.po_no = @po_no
  AND pol.item_code = @item_code
  AND ROUND(COALESCE(pol.qty_received, 0), 4) >= @expected_qty
  AND ROUND(COALESCE(pol.qty_outstanding, 0), 4) = 0;

SELECT 'SUMMARY_STOCK_UPDATED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 stock balance row with qty_on_hand >= 10 and stock_value >= 100000' AS expected
FROM inventory_stock_balances
WHERE item_code = @item_code
  AND ROUND(COALESCE(qty_on_hand, 0), 4) >= @expected_qty
  AND ROUND(COALESCE(stock_value, 0), 2) >= @expected_stock_value;

SELECT 'SUMMARY_STOCK_MOVEMENT_CREATED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 purchase_receipt stock movement qty 10 value 100000' AS expected
FROM inventory_stock_movements sm
WHERE sm.item_code = @item_code
  AND sm.reference_type = 'purchase_receipt'
  AND ROUND(COALESCE(sm.qty, 0), 4) = @expected_qty
  AND ROUND(COALESCE(sm.stock_value, 0), 2) = @expected_stock_value
  AND sm.reference_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = @po_no
  );

SELECT 'SUMMARY_GL_BALANCED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 balanced GL journal with diff = 0' AS expected
FROM (
    SELECT ge.journal_no,
           SUM(COALESCE(gel.debit, 0)) - SUM(COALESCE(gel.credit, 0)) AS diff
    FROM gl_entries ge
    JOIN gl_entry_lines gel ON gel.gl_entry_id = ge.id
    WHERE ge.source_no IN (
          SELECT pr.receipt_no
          FROM purchase_receipts pr
          JOIN purchase_orders po ON po.id = pr.purchase_order_id
          WHERE po.po_no = @po_no
    )
    GROUP BY ge.journal_no
) x
WHERE ABS(x.diff) < 0.0001;

SELECT 'SUMMARY_GL_EXPECTED_AMOUNT' AS check_name,
       CASE WHEN
           SUM(CASE WHEN gel.account_no = '1300' AND ROUND(COALESCE(gel.debit, 0), 2) = @expected_stock_value AND ROUND(COALESCE(gel.credit, 0), 2) = 0 THEN 1 ELSE 0 END) >= 1
           AND
           SUM(CASE WHEN gel.account_no = '2300' AND ROUND(COALESCE(gel.credit, 0), 2) = @expected_stock_value AND ROUND(COALESCE(gel.debit, 0), 2) = 0 THEN 1 ELSE 0 END) >= 1
       THEN 1 ELSE 0 END AS ok_count,
       'Expected 1 means Inventory Dr 100000 and GRNI Cr 100000 are present' AS expected
FROM gl_entry_lines gel
JOIN gl_entries ge ON ge.id = gel.gl_entry_id
WHERE ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = @po_no
);

SELECT 'E2E_PASS_HINT' AS check_name,
       'Semua SUMMARY_* ok_count harus sesuai expected. Untuk test ini nilai penting: Receipt posted, Stock Movement, Stock Balance, GL Balanced, GL Expected Amount.' AS notes;
