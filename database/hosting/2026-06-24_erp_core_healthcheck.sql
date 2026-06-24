-- ERP PENA - ERP Core Health Check
-- Purpose: validate previous ERP core development before continuing MRP/advanced planning.
-- Expected: all FAIL_COUNT checks should be 0, and required table checks should be 1.

SELECT 'CORE_TABLE_COMPANIES' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'companies';

SELECT 'CORE_TABLE_SITES' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sites';

SELECT 'CORE_TABLE_ITEMS' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'items';

SELECT 'CORE_TABLE_BOMS' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'production_boms';

SELECT 'CORE_TABLE_BOM_LINES' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'production_bom_lines';

SELECT 'CORE_TABLE_TRANSACTION_CODES' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'transaction_codes';

SELECT 'CORE_TABLE_DOCUMENT_SEQUENCES' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'document_number_sequences';

SELECT 'FAIL_ITEM_TYPE_NULL_OR_BLANK' AS check_name, COUNT(*) AS total
FROM items
WHERE item_type IS NULL OR TRIM(item_type) = '';

SELECT 'FAIL_ITEM_DUPLICATE_PER_COMPANY_SITE_CODE' AS check_name, COUNT(*) AS total
FROM (
    SELECT company_id, site_id, item_code
    FROM items
    WHERE deleted_at IS NULL
      AND COALESCE(item_code, '') <> ''
    GROUP BY company_id, site_id, item_code
    HAVING COUNT(*) > 1
) x;

SELECT 'FAIL_ITEM_WITHOUT_STOCK_UOM' AS check_name, COUNT(*) AS total
FROM items
WHERE deleted_at IS NULL
  AND COALESCE(item_code, '') <> ''
  AND (stockuom IS NULL OR TRIM(stockuom) = '');

SELECT 'FAIL_DOCUMENT_NUMBERING_REQUIRED_CODES_MISSING_OR_INACTIVE' AS check_name, COUNT(*) AS total
FROM (
    SELECT 'PO' AS code UNION ALL SELECT 'PR' UNION ALL SELECT 'SO' UNION ALL SELECT 'SD' UNION ALL SELECT 'SI' UNION ALL SELECT 'PI' UNION ALL SELECT 'JV'
) req
LEFT JOIN transaction_codes tc ON tc.code = req.code AND COALESCE(tc.is_active, 1) = 1
WHERE tc.id IS NULL;

SELECT 'FAIL_BOM_LINES_WITH_ZERO_OR_NEGATIVE_QTY' AS check_name, COUNT(*) AS total
FROM production_bom_lines
WHERE COALESCE(qty_used, 0) <= 0;

SELECT 'FAIL_BOM_CHILD_ITEM_WITHOUT_ITEM_MASTER' AS check_name, COUNT(*) AS total
FROM production_bom_lines l
JOIN production_boms b ON b.id = l.production_bom_id
LEFT JOIN items i
    ON i.company_id = b.company_id
   AND i.item_code = l.child_item_code
   AND (i.site_id = b.site_id OR i.site_id IS NULL OR i.site_id = 0)
   AND i.deleted_at IS NULL
WHERE COALESCE(l.child_item_code, '') <> ''
  AND i.id IS NULL;

SELECT 'FAIL_STOCK_BALANCE_NEGATIVE_AVAILABLE' AS check_name, COUNT(*) AS total
FROM inventory_stock_balances
WHERE COALESCE(qty_available, 0) < 0;

-- Detail duplicate item, if any.
SELECT company_id, site_id, item_code, COUNT(*) AS total
FROM items
WHERE deleted_at IS NULL
  AND COALESCE(item_code, '') <> ''
GROUP BY company_id, site_id, item_code
HAVING COUNT(*) > 1
ORDER BY company_id, site_id, item_code
LIMIT 100;

-- Detail BOM children that still do not exist in item master.
SELECT b.company_id, b.site_id, b.parent_item_code, l.child_no, l.child_item_code, l.child_item_name, l.qty_used, l.uom_code
FROM production_bom_lines l
JOIN production_boms b ON b.id = l.production_bom_id
LEFT JOIN items i
    ON i.company_id = b.company_id
   AND i.item_code = l.child_item_code
   AND (i.site_id = b.site_id OR i.site_id IS NULL OR i.site_id = 0)
   AND i.deleted_at IS NULL
WHERE COALESCE(l.child_item_code, '') <> ''
  AND i.id IS NULL
ORDER BY b.parent_item_code, l.child_no
LIMIT 100;
