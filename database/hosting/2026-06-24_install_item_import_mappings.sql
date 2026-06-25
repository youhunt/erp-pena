-- ERP PENA - Imported Item Name Mapping
-- Purpose: map item names imported from Excel/vendor documents to official Item Master codes.
-- This prevents using item_name as item_code.

USE `dberp_pena`;

CREATE TABLE IF NOT EXISTS item_import_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    source_type VARCHAR(40) NOT NULL DEFAULT 'purchase_order_import',
    imported_item_name VARCHAR(300) NOT NULL,
    normalized_imported_name VARCHAR(300) NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    item_code VARCHAR(80) NOT NULL,
    item_name VARCHAR(255) NULL,
    uom_code VARCHAR(20) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(500) NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_item_import_mapping_scope (company_id, site_id, source_type, normalized_imported_name),
    KEY idx_item_import_mapping_item (item_code),
    KEY idx_item_import_mapping_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helper: show imported PO lines that still do not have official item_code.
SELECT
    po.po_no,
    pol.id AS po_line_id,
    pol.line_no,
    pol.po_line,
    pol.item_id,
    pol.item_code,
    pol.item_name AS imported_item_name,
    LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(pol.item_name), CHAR(13), ''), CHAR(10), ''), ' ', ''), '.', ''), ',', ''), '-', ''), '/', '')) AS normalized_imported_name,
    pol.uom_code,
    pol.qty_ordered,
    pol.qty_outstanding
FROM purchase_order_lines pol
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE (pol.item_code IS NULL OR TRIM(pol.item_code) = '')
  AND pol.item_name IS NOT NULL
  AND TRIM(pol.item_name) <> ''
ORDER BY po.po_no, pol.line_no, pol.po_line, pol.id;

-- Example usage:
-- Replace ITEM_CODE_MASTER with the real item code from master items.
-- Replace imported_item_name with the exact text from purchase_order_lines.item_name.
--
-- INSERT INTO item_import_mappings (
--     company_id, site_id, source_type,
--     imported_item_name, normalized_imported_name,
--     item_id, item_code, item_name, uom_code,
--     is_active, created_at, updated_at
-- )
-- SELECT
--     po.company_id,
--     po.site_id,
--     'purchase_order_import',
--     pol.item_name,
--     LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(pol.item_name), CHAR(13), ''), CHAR(10), ''), ' ', ''), '.', ''), ',', ''), '-', ''), '/', '')),
--     i.id,
--     i.item_code,
--     i.item_name,
--     COALESCE(NULLIF(i.stockuom, ''), 'PCS'),
--     1,
--     NOW(),
--     NOW()
-- FROM purchase_order_lines pol
-- JOIN purchase_orders po ON po.id = pol.purchase_order_id
-- JOIN items i ON i.item_code = 'ITEM_CODE_MASTER'
-- WHERE pol.id = 18;
