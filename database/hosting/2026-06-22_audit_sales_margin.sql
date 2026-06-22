-- Sales Margin Audit
-- Purpose: compare AR invoice revenue vs delivery COGS GL.
-- Update variables below before running.

SET @do_no := 'DO/202606/0001';
SET @invoice_no := 'SI/202606/0001';

SELECT
    d.delivery_no,
    i.invoice_no,
    i.total_amount AS invoice_amount,
    ge.total_debit AS cogs_amount,
    ROUND(i.total_amount - ge.total_debit, 2) AS gross_profit_loss,
    CASE
        WHEN i.total_amount = 0 THEN NULL
        ELSE ROUND(((i.total_amount - ge.total_debit) / i.total_amount) * 100, 2)
    END AS gross_margin_pct,
    CASE
        WHEN ge.id IS NULL THEN 'MISSING_COGS_GL'
        WHEN i.total_amount - ge.total_debit < 0 THEN 'LOSS_REVIEW_COST_OR_PRICE'
        ELSE 'PROFIT_OK'
    END AS margin_status
FROM sales_deliveries d
LEFT JOIN sales_invoices i
       ON i.sales_delivery_id = d.id
       OR i.delivery_no = d.delivery_no
LEFT JOIN gl_entries ge
       ON ge.id = d.gl_entry_id
WHERE d.delivery_no = @do_no
   OR i.invoice_no = @invoice_no
ORDER BY d.id DESC;
