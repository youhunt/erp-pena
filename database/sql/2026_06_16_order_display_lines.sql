-- Add user-facing line numbers for SO/PO lines.
-- Safe to run from phpMyAdmin.
SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sales_order_lines' AND COLUMN_NAME = 'so_line'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `sales_order_lines` ADD COLUMN `so_line` INT NULL AFTER `line_no`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @row_no := 0;
SET @parent_id := 0;
UPDATE `sales_order_lines` target
JOIN (
    SELECT
        id,
        @row_no := IF(@parent_id = sales_order_id, @row_no + 1, 1) AS display_line,
        @parent_id := sales_order_id
    FROM `sales_order_lines`
    ORDER BY sales_order_id, line_no, id
) sorted ON sorted.id = target.id
SET target.so_line = COALESCE(target.so_line, sorted.display_line),
    target.line_no = sorted.display_line;

SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sales_order_lines' AND INDEX_NAME = 'idx_sales_order_lines_so_line'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE `sales_order_lines` ADD INDEX `idx_sales_order_lines_so_line` (`sales_order_id`, `so_line`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_order_lines' AND COLUMN_NAME = 'po_line'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `purchase_order_lines` ADD COLUMN `po_line` INT NULL AFTER `line_no`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @row_no := 0;
SET @parent_id := 0;
UPDATE `purchase_order_lines` target
JOIN (
    SELECT
        id,
        @row_no := IF(@parent_id = purchase_order_id, @row_no + 1, 1) AS display_line,
        @parent_id := purchase_order_id
    FROM `purchase_order_lines`
    ORDER BY purchase_order_id, line_no, id
) sorted ON sorted.id = target.id
SET target.po_line = COALESCE(target.po_line, sorted.display_line),
    target.line_no = sorted.display_line;

SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_order_lines' AND INDEX_NAME = 'idx_purchase_order_lines_po_line'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE `purchase_order_lines` ADD INDEX `idx_purchase_order_lines_po_line` (`purchase_order_id`, `po_line`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verification:
-- SELECT sales_order_id, line_no, so_line, item_code FROM sales_order_lines ORDER BY sales_order_id, so_line;
-- SELECT purchase_order_id, line_no, po_line, item_code FROM purchase_order_lines ORDER BY purchase_order_id, po_line;
