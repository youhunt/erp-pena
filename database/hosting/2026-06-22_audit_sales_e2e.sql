-- Sales E2E Audit Pack
-- Purpose: audit Sales Order -> Delivery -> AR Invoice -> AR Receipt -> GL.
-- Update variables below before running.

SET @so_no := 'SO-20260605-020255';
SET @do_no := 'DO/202606/0001';
SET @invoice_no := 'SI/202606/0001';

/* =========================================================
   A. Sales Order
   ========================================================= */

SELECT
    id,
    so_no,
    document_status,
    status,
    customer_code,
    customer_name,
    total_amount
FROM sales_orders
WHERE so_no = @so_no;

/* =========================================================
   B. Delivery Order + COGS GL link
   ========================================================= */

SELECT
    id,
    delivery_no,
    delivery_date,
    status,
    so_no,
    customer_code,
    customer_name,
    gl_entry_id AS cogs_gl_entry_id,
    reversal_gl_entry_id,
    posted_at
FROM sales_deliveries
WHERE delivery_no = @do_no
   OR so_no = @so_no
ORDER BY id DESC;

/* =========================================================
   C. Delivery Lines + Stock Movement
   ========================================================= */

SELECT
    dl.sales_delivery_id,
    d.delivery_no,
    dl.line_no,
    dl.item_code,
    dl.item_name,
    dl.qty_delivered,
    dl.uom_code,
    dl.stock_movement_id,
    dl.reversal_movement_id
FROM sales_delivery_lines dl
JOIN sales_deliveries d ON d.id = dl.sales_delivery_id
WHERE d.delivery_no = @do_no
   OR d.so_no = @so_no
ORDER BY dl.sales_delivery_id, dl.line_no;

/* =========================================================
   D. AR Invoice
   ========================================================= */

SELECT
    id,
    invoice_no,
    invoice_date,
    status,
    source_type,
    source,
    so_no,
    delivery_no,
    total_amount,
    received_amount,
    outstanding_amount,
    gl_entry_id
FROM sales_invoices
WHERE invoice_no = @invoice_no
   OR so_no = @so_no
   OR delivery_no = @do_no
ORDER BY id DESC;

/* =========================================================
   E. AR Receipt
   ========================================================= */

SELECT
    r.id,
    r.receipt_no,
    r.receipt_date,
    r.status,
    r.method,
    r.cash_bank_code,
    r.amount,
    r.gl_entry_id,
    r.cash_bank_entry_id
FROM ar_receipts r
LEFT JOIN sales_invoices i ON i.id = r.sales_invoice_id
WHERE i.invoice_no = @invoice_no
   OR i.so_no = @so_no
   OR i.delivery_no = @do_no
ORDER BY r.id DESC;

/* =========================================================
   F. GL entries linked to Delivery / Invoice / Receipt
   ========================================================= */

SELECT
    ge.id,
    ge.journal_no,
    ge.journal_date,
    ge.source_module,
    ge.source_type,
    ge.source_no,
    ge.status,
    ge.total_debit,
    ge.total_credit,
    ROUND(ge.total_debit - ge.total_credit, 2) AS difference,
    CASE
        WHEN ABS(ROUND(ge.total_debit - ge.total_credit, 2)) <= 0.01 THEN 'BALANCED'
        ELSE 'UNBALANCED'
    END AS balance_status
FROM gl_entries ge
WHERE ge.id IN (
    SELECT gl_entry_id FROM sales_deliveries WHERE (delivery_no = @do_no OR so_no = @so_no) AND gl_entry_id IS NOT NULL
    UNION
    SELECT gl_entry_id FROM sales_invoices WHERE (invoice_no = @invoice_no OR so_no = @so_no OR delivery_no = @do_no) AND gl_entry_id IS NOT NULL
    UNION
    SELECT r.gl_entry_id
    FROM ar_receipts r
    JOIN sales_invoices i ON i.id = r.sales_invoice_id
    WHERE (i.invoice_no = @invoice_no OR i.so_no = @so_no OR i.delivery_no = @do_no)
      AND r.gl_entry_id IS NOT NULL
)
ORDER BY ge.journal_date, ge.id;

/* =========================================================
   G. Summary check
   ========================================================= */

SELECT
    @so_no AS so_no,
    @do_no AS delivery_no,
    @invoice_no AS invoice_no,
    (SELECT COUNT(*) FROM sales_deliveries WHERE delivery_no = @do_no OR so_no = @so_no) AS delivery_count,
    (SELECT COUNT(*) FROM sales_delivery_lines dl JOIN sales_deliveries d ON d.id = dl.sales_delivery_id WHERE d.delivery_no = @do_no OR d.so_no = @so_no) AS delivery_line_count,
    (SELECT COUNT(*) FROM sales_invoices WHERE invoice_no = @invoice_no OR so_no = @so_no OR delivery_no = @do_no) AS invoice_count,
    (SELECT COUNT(*) FROM ar_receipts r JOIN sales_invoices i ON i.id = r.sales_invoice_id WHERE i.invoice_no = @invoice_no OR i.so_no = @so_no OR i.delivery_no = @do_no) AS receipt_count,
    (SELECT COUNT(*) FROM gl_entries ge WHERE ge.id IN (
        SELECT gl_entry_id FROM sales_deliveries WHERE (delivery_no = @do_no OR so_no = @so_no) AND gl_entry_id IS NOT NULL
        UNION
        SELECT gl_entry_id FROM sales_invoices WHERE (invoice_no = @invoice_no OR so_no = @so_no OR delivery_no = @do_no) AND gl_entry_id IS NOT NULL
        UNION
        SELECT r.gl_entry_id FROM ar_receipts r JOIN sales_invoices i ON i.id = r.sales_invoice_id WHERE (i.invoice_no = @invoice_no OR i.so_no = @so_no OR i.delivery_no = @do_no) AND r.gl_entry_id IS NOT NULL
    )) AS linked_gl_count;
