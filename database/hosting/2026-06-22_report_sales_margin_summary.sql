-- Sales Margin Summary Report
-- Purpose: list sales invoices with linked delivery COGS and gross margin.
-- Update date range before running.

SET @date_from := '2026-06-01';
SET @date_to := '2026-06-30';

/* =========================================================
   A. Sales margin detail by invoice
   ========================================================= */

SELECT
    i.invoice_date,
    i.invoice_no,
    i.status AS invoice_status,
    i.customer_code,
    i.customer_name,
    d.delivery_no,
    d.status AS delivery_status,
    COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) AS invoice_amount,
    COALESCE(cogs.total_debit, 0) AS cogs_amount,
    ROUND(COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) - COALESCE(cogs.total_debit, 0), 2) AS gross_profit_loss,
    CASE
        WHEN COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) = 0 THEN NULL
        ELSE ROUND(((COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) - COALESCE(cogs.total_debit, 0)) / COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0)) * 100, 2)
    END AS gross_margin_pct,
    CASE
        WHEN d.id IS NULL THEN 'MISSING_DELIVERY'
        WHEN d.gl_entry_id IS NULL THEN 'MISSING_COGS_GL'
        WHEN COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) - COALESCE(cogs.total_debit, 0) < 0 THEN 'LOSS_REVIEW_COST_OR_PRICE'
        ELSE 'PROFIT_OK'
    END AS margin_status,
    i.gl_entry_id AS invoice_gl_entry_id,
    d.gl_entry_id AS cogs_gl_entry_id
FROM sales_invoices i
LEFT JOIN sales_deliveries d
       ON d.id = i.sales_delivery_id
       OR d.delivery_no = i.delivery_no
LEFT JOIN gl_entries cogs
       ON cogs.id = d.gl_entry_id
WHERE i.invoice_date >= @date_from
  AND i.invoice_date <= @date_to
  AND COALESCE(i.status, '') <> 'cancelled'
ORDER BY i.invoice_date DESC, i.id DESC;

/* =========================================================
   B. Sales margin summary by status
   ========================================================= */

SELECT
    margin_status,
    COUNT(*) AS invoice_count,
    SUM(invoice_amount) AS total_invoice_amount,
    SUM(cogs_amount) AS total_cogs_amount,
    SUM(gross_profit_loss) AS total_gross_profit_loss,
    CASE
        WHEN SUM(invoice_amount) = 0 THEN NULL
        ELSE ROUND((SUM(gross_profit_loss) / SUM(invoice_amount)) * 100, 2)
    END AS gross_margin_pct
FROM (
    SELECT
        COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) AS invoice_amount,
        COALESCE(cogs.total_debit, 0) AS cogs_amount,
        COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) - COALESCE(cogs.total_debit, 0) AS gross_profit_loss,
        CASE
            WHEN d.id IS NULL THEN 'MISSING_DELIVERY'
            WHEN d.gl_entry_id IS NULL THEN 'MISSING_COGS_GL'
            WHEN COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) - COALESCE(cogs.total_debit, 0) < 0 THEN 'LOSS_REVIEW_COST_OR_PRICE'
            ELSE 'PROFIT_OK'
        END AS margin_status
    FROM sales_invoices i
    LEFT JOIN sales_deliveries d
           ON d.id = i.sales_delivery_id
           OR d.delivery_no = i.delivery_no
    LEFT JOIN gl_entries cogs
           ON cogs.id = d.gl_entry_id
    WHERE i.invoice_date >= @date_from
      AND i.invoice_date <= @date_to
      AND COALESCE(i.status, '') <> 'cancelled'
) x
GROUP BY margin_status
ORDER BY margin_status;

/* =========================================================
   C. Top loss invoices
   ========================================================= */

SELECT
    i.invoice_date,
    i.invoice_no,
    i.customer_code,
    i.customer_name,
    d.delivery_no,
    COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) AS invoice_amount,
    COALESCE(cogs.total_debit, 0) AS cogs_amount,
    ROUND(COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) - COALESCE(cogs.total_debit, 0), 2) AS gross_profit_loss
FROM sales_invoices i
LEFT JOIN sales_deliveries d
       ON d.id = i.sales_delivery_id
       OR d.delivery_no = i.delivery_no
LEFT JOIN gl_entries cogs
       ON cogs.id = d.gl_entry_id
WHERE i.invoice_date >= @date_from
  AND i.invoice_date <= @date_to
  AND COALESCE(i.status, '') <> 'cancelled'
  AND COALESCE(i.total_amount, i.grand_total, i.subtotal_amount, 0) - COALESCE(cogs.total_debit, 0) < 0
ORDER BY gross_profit_loss ASC
LIMIT 20;
