-- ERP PENA - QUICK FIX PURCHASE RECEIPT REVERSAL COLUMNS
-- Run in phpMyAdmin if Reverse Purchase Receipt shows: Failed to reverse purchase receipt.
-- Safe to run repeatedly on MySQL/MariaDB versions that support ADD COLUMN IF NOT EXISTS.

ALTER TABLE purchase_receipts
    ADD COLUMN IF NOT EXISTS reversal_gl_entry_id BIGINT UNSIGNED NULL AFTER gl_entry_id,
    ADD COLUMN IF NOT EXISTS reversed_at DATETIME NULL AFTER posted_by,
    ADD COLUMN IF NOT EXISTS reversed_by INT NULL AFTER reversed_at,
    ADD COLUMN IF NOT EXISTS reversal_reason VARCHAR(500) NULL AFTER reversed_by;

ALTER TABLE purchase_receipt_lines
    ADD COLUMN IF NOT EXISTS reversed_qty DECIMAL(20,6) NOT NULL DEFAULT 0 AFTER qty_received,
    ADD COLUMN IF NOT EXISTS reversal_movement_id BIGINT UNSIGNED NULL AFTER stock_movement_id,
    ADD COLUMN IF NOT EXISTS reversed_at DATETIME NULL AFTER location_id,
    ADD COLUMN IF NOT EXISTS reversed_by INT NULL AFTER reversed_at,
    ADD COLUMN IF NOT EXISTS reversal_reason VARCHAR(500) NULL AFTER reversed_by;

ALTER TABLE inventory_stock_movements
    ADD COLUMN IF NOT EXISTS gl_entry_id BIGINT UNSIGNED NULL AFTER stock_value,
    ADD COLUMN IF NOT EXISTS reference_type VARCHAR(60) NULL AFTER gl_entry_id,
    ADD COLUMN IF NOT EXISTS reference_id BIGINT UNSIGNED NULL AFTER reference_type,
    ADD COLUMN IF NOT EXISTS reference_no VARCHAR(80) NULL AFTER reference_id;

SELECT 'PURCHASE_RECEIPT_REVERSAL_COLUMNS_READY' AS check_name, COUNT(*) AS ready_count, 4 AS expected_count
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'purchase_receipts'
  AND COLUMN_NAME IN ('reversal_gl_entry_id','reversed_at','reversed_by','reversal_reason');

SELECT 'PURCHASE_RECEIPT_LINE_REVERSAL_COLUMNS_READY' AS check_name, COUNT(*) AS ready_count, 5 AS expected_count
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'purchase_receipt_lines'
  AND COLUMN_NAME IN ('reversed_qty','reversal_movement_id','reversed_at','reversed_by','reversal_reason');

SELECT 'INVENTORY_MOVEMENT_REVERSAL_FIELDS_READY' AS check_name, COUNT(*) AS ready_count, 4 AS expected_count
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'inventory_stock_movements'
  AND COLUMN_NAME IN ('gl_entry_id','reference_type','reference_id','reference_no');
