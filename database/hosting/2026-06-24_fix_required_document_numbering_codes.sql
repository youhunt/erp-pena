-- ERP PENA - Fix required document numbering transaction codes.
-- Purpose: clear FAIL_DOCUMENT_NUMBERING_REQUIRED_CODES_MISSING_OR_INACTIVE.
-- Run after selecting the ERP database in phpMyAdmin.

SELECT 'BEFORE_MISSING_OR_INACTIVE_CODES' AS check_name, req.code
FROM (
    SELECT 'PO' AS code UNION ALL SELECT 'PR' UNION ALL SELECT 'SO' UNION ALL SELECT 'SD' UNION ALL SELECT 'SI' UNION ALL SELECT 'PI' UNION ALL SELECT 'JV'
) req
LEFT JOIN transaction_codes tc ON tc.code = req.code AND COALESCE(tc.is_active, 1) = 1
WHERE tc.id IS NULL;

INSERT INTO transaction_codes (
    code,
    name,
    description,
    prefix,
    number_format,
    reset_period,
    padding,
    is_active,
    created_at,
    updated_at
)
SELECT x.code, x.name, x.description, x.prefix, x.number_format, x.reset_period, x.padding, 1, NOW(), NOW()
FROM (
    SELECT 'PO' AS code, 'Purchase Order' AS name, 'Purchase order document number' AS description, 'PO' AS prefix, '{PREFIX}{SEQ}' AS number_format, 'never' AS reset_period, 4 AS padding
    UNION ALL SELECT 'PR', 'Purchase Requisition', 'Purchase requisition document number', 'PR', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'SO', 'Sales Order', 'Sales order document number', 'SO', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'SD', 'Sales Delivery', 'Sales delivery document number', 'SD', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'SI', 'Sales Invoice', 'Sales invoice document number', 'SI', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'PI', 'Purchase Invoice', 'Purchase invoice document number', 'PI', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'JV', 'Journal Voucher', 'Journal voucher document number', 'JV', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
) x
LEFT JOIN transaction_codes tc ON tc.code = x.code
WHERE tc.id IS NULL;

UPDATE transaction_codes tc
JOIN (
    SELECT 'PO' AS code, 'Purchase Order' AS name, 'Purchase order document number' AS description, 'PO' AS prefix, '{PREFIX}{SEQ}' AS number_format, 'never' AS reset_period, 4 AS padding
    UNION ALL SELECT 'PR', 'Purchase Requisition', 'Purchase requisition document number', 'PR', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'SO', 'Sales Order', 'Sales order document number', 'SO', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'SD', 'Sales Delivery', 'Sales delivery document number', 'SD', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'SI', 'Sales Invoice', 'Sales invoice document number', 'SI', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'PI', 'Purchase Invoice', 'Purchase invoice document number', 'PI', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
    UNION ALL SELECT 'JV', 'Journal Voucher', 'Journal voucher document number', 'JV', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4
) x ON x.code = tc.code
SET
    tc.name = COALESCE(NULLIF(tc.name, ''), x.name),
    tc.description = COALESCE(NULLIF(tc.description, ''), x.description),
    tc.prefix = COALESCE(NULLIF(tc.prefix, ''), x.prefix),
    tc.number_format = COALESCE(NULLIF(tc.number_format, ''), x.number_format),
    tc.reset_period = COALESCE(NULLIF(tc.reset_period, ''), x.reset_period),
    tc.padding = CASE WHEN COALESCE(tc.padding, 0) <= 0 THEN x.padding ELSE tc.padding END,
    tc.is_active = 1,
    tc.updated_at = NOW();

SELECT 'AFTER_FAIL_DOCUMENT_NUMBERING_REQUIRED_CODES_MISSING_OR_INACTIVE' AS check_name, COUNT(*) AS total
FROM (
    SELECT 'PO' AS code UNION ALL SELECT 'PR' UNION ALL SELECT 'SO' UNION ALL SELECT 'SD' UNION ALL SELECT 'SI' UNION ALL SELECT 'PI' UNION ALL SELECT 'JV'
) req
LEFT JOIN transaction_codes tc ON tc.code = req.code AND COALESCE(tc.is_active, 1) = 1
WHERE tc.id IS NULL;

SELECT code, name, prefix, number_format, reset_period, padding, is_active
FROM transaction_codes
WHERE code IN ('PO', 'PR', 'SO', 'SD', 'SI', 'PI', 'JV')
ORDER BY FIELD(code, 'PO', 'PR', 'SO', 'SD', 'SI', 'PI', 'JV');
