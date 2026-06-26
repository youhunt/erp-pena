-- ERP PENA - VERIFY E2E PURCHASE TO GL
-- Jalankan setelah melakukan test PO -> Receipt -> Stock -> GL.
-- Pilih database ERP terlebih dahulu di phpMyAdmin.
-- Ganti nilai @po_no sesuai nomor PO hasil testing.

SET @po_no := 'PO_NO_HASIL_TEST';
SET @item_code := 'ITEM-E2E-001';
SET @db := DATABASE();

SELECT @db AS selected_database, @po_no AS tested_po_no, @item_code AS tested_item_code;

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
SELECT 'COA_REQUIRED_ACCOUNTS' AS check_name, account_no, account_name, is_active
FROM chart_accounts
WHERE account_no IN ('1100','1300','2100','2300')
ORDER BY account_no;

-- =========================================================
-- 3. GL Posting Profile check
-- =========================================================
SELECT 'GL_POSTING_PROFILE_REQUIRED' AS check_name,
       gp.company_id, gp.module_code, gp.posting_key, gp.account_no, ca.account_name, gp.is_active
FROM gl_posting_profiles gp
LEFT JOIN chart_accounts ca ON ca.account_no = gp.account_no
WHERE gp.module_code IN ('ap','cashbank')
  AND gp.posting_key IN ('inventory','grni','payable','cash_bank')
ORDER BY gp.module_code, gp.posting_key;

-- =========================================================
-- 4. Item Master check
-- =========================================================
SELECT 'ITEM_MASTER' AS check_name,
       id, company_id, site_id, item_code, item_name, item_type, stockuom, is_active
FROM items
WHERE item_code = @item_code;

-- =========================================================
-- 5. Warehouse / Location check
-- =========================================================
SELECT 'WAREHOUSE_WH_E2E' AS check_name,
       w.id, w.code, w.name, w.company_id, w.site_id, w.department_id, w.is_active
FROM warehouses w
WHERE w.code = 'WH-E2E';

SELECT 'LOCATION_LOC_E2E' AS check_name,
       l.id, l.code, l.name, l.warehouse_id, w.code AS warehouse_code, l.is_active
FROM locations l
JOIN warehouses w ON w.id = l.warehouse_id
WHERE w.code = 'WH-E2E'
  AND l.code = 'LOC-E2E';

-- =========================================================
-- 6. PO header / lines check
-- =========================================================
SELECT 'PO_HEADER' AS check_name,
       id, po_no, document_status, status, total_amount, submitted_at, approved_at
FROM purchase_orders
WHERE po_no = @po_no;

SELECT 'PO_LINES' AS check_name,
       pol.item_code, pol.item_name, pol.qty_ordered, pol.qty_received, pol.qty_outstanding,
       pol.unit_price, pol.line_total, pol.line_status
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE po.po_no = @po_no;

-- =========================================================
-- 7. Receipt check
-- =========================================================
SELECT 'RECEIPT_HEADER' AS check_name,
       pr.id, pr.receipt_no, pr.purchase_order_id, pr.status, pr.receipt_date
FROM purchase_receipts pr
JOIN purchase_orders po ON po.id = pr.purchase_order_id
WHERE po.po_no = @po_no
ORDER BY pr.id DESC;

-- =========================================================
-- 8. Stock balance / movement check
-- =========================================================
SELECT 'STOCK_BALANCE' AS check_name,
       company_id, site_id, warehouse_id, location_id, item_code, batch_no,
       qty_on_hand, qty_reserved, qty_available, avg_cost, stock_value
FROM inventory_stock_balances
WHERE item_code = @item_code;

SELECT 'STOCK_MOVEMENT' AS check_name,
       movement_date, movement_type, direction, item_code, qty, unit_cost, stock_value,
       reference_type, reference_no, gl_entry_id
FROM inventory_stock_movements
WHERE item_code = @item_code
ORDER BY id DESC;

-- =========================================================
-- 9. GL header / lines check
-- =========================================================
SELECT 'GL_HEADER' AS check_name,
       ge.id, ge.journal_no, ge.journal_date, ge.source_module, ge.source_type, ge.source_no, ge.description
FROM gl_entries ge
WHERE ge.source_no IN (
      SELECT pr.receipt_no
      FROM purchase_receipts pr
      JOIN purchase_orders po ON po.id = pr.purchase_order_id
      WHERE po.po_no = @po_no
)
ORDER BY ge.id DESC;

SELECT 'GL_LINES' AS check_name,
       ge.journal_no, ge.source_no, gel.account_no, ca.account_name,
       gel.debit, gel.credit, gel.description
FROM gl_entry_lines gel
JOIN gl_entries ge ON ge.id = gel.gl_entry_id
LEFT JOIN chart_accounts ca ON ca.account_no = gel.account_no
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

-- =========================================================
-- 10. Summary indicators
-- =========================================================
SELECT 'SUMMARY_PO_RECEIVED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 and qty_outstanding = 0 for full receipt' AS expected
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE po.po_no = @po_no
  AND pol.item_code = @item_code
  AND COALESCE(pol.qty_received, 0) > 0;

SELECT 'SUMMARY_STOCK_UPDATED' AS check_name,
       COUNT(*) AS ok_count,
       'Expected >= 1 stock balance row with qty_on_hand > 0' AS expected
FROM inventory_stock_balances
WHERE item_code = @item_code
  AND COALESCE(qty_on_hand, 0) > 0;

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
