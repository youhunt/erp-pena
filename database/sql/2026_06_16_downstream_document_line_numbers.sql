-- Normalize downstream document line numbers to 1,2,3 per document.
-- Safe to run from phpMyAdmin after backing up the database.

SET @row_no := 0;
SET @parent_id := 0;
UPDATE `sales_delivery_lines` target
JOIN (
    SELECT
        `id`,
        @row_no := IF(@parent_id = `sales_delivery_id`, @row_no + 1, 1) AS `display_line`,
        @parent_id := `sales_delivery_id`
    FROM `sales_delivery_lines`
    ORDER BY `sales_delivery_id`, `line_no`, `id`
) sorted ON sorted.`id` = target.`id`
SET target.`line_no` = sorted.`display_line`;

SET @row_no := 0;
SET @parent_id := 0;
UPDATE `purchase_receipt_lines` target
JOIN (
    SELECT
        `id`,
        @row_no := IF(@parent_id = `purchase_receipt_id`, @row_no + 1, 1) AS `display_line`,
        @parent_id := `purchase_receipt_id`
    FROM `purchase_receipt_lines`
    ORDER BY `purchase_receipt_id`, `line_no`, `id`
) sorted ON sorted.`id` = target.`id`
SET target.`line_no` = sorted.`display_line`;

SET @row_no := 0;
SET @parent_id := 0;
UPDATE `sales_invoice_lines` target
JOIN (
    SELECT
        `id`,
        @row_no := IF(@parent_id = `sales_invoice_id`, @row_no + 1, 1) AS `display_line`,
        @parent_id := `sales_invoice_id`
    FROM `sales_invoice_lines`
    ORDER BY `sales_invoice_id`, `line_no`, `id`
) sorted ON sorted.`id` = target.`id`
SET target.`line_no` = sorted.`display_line`;

SET @row_no := 0;
SET @parent_id := 0;
UPDATE `purchase_invoice_lines` target
JOIN (
    SELECT
        `id`,
        @row_no := IF(@parent_id = `purchase_invoice_id`, @row_no + 1, 1) AS `display_line`,
        @parent_id := `purchase_invoice_id`
    FROM `purchase_invoice_lines`
    ORDER BY `purchase_invoice_id`, `line_no`, `id`
) sorted ON sorted.`id` = target.`id`
SET target.`line_no` = sorted.`display_line`;
