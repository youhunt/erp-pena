-- ERP PENA - QUICK FIX MISSING / INVALID GL POSTING PROFILES
-- Jalankan di phpMyAdmin jika muncul error:
-- #1146 - Table '<database>.gl_posting_profiles' doesn't exist
-- Account is not postable: 1100
-- atau /system/core-health FAIL pada CORE_TABLE_GL_POSTING_PROFILES.
--
-- Catatan:
-- - Script ini aman dijalankan berulang.
-- - 1100 adalah COA header/non-postable.
-- - Cash payment harus posting ke 1110, bank payment harus posting ke 1120.
-- - Default cashbank.cash_bank diarahkan ke 1120 agar tidak error Account is not postable: 1100.

SET @db := DATABASE();
SELECT @db AS selected_database;

CREATE TABLE IF NOT EXISTS gl_posting_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    module_code VARCHAR(40) NOT NULL,
    posting_key VARCHAR(80) NOT NULL,
    account_no VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_gl_posting_profiles_scope (company_id, module_code, posting_key),
    KEY idx_gl_posting_profiles_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'GL_POSTING_PROFILES_TABLE_READY' AS check_name, COUNT(*) AS ready_count, 1 AS expected_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'gl_posting_profiles';

INSERT INTO gl_posting_profiles (company_id, module_code, posting_key, account_no, description, is_active, created_at, updated_at)
SELECT c.company_id, p.module_code, p.posting_key, p.account_no, p.description, 1, NOW(), NOW()
FROM (
    SELECT id AS company_id FROM companies
    UNION
    SELECT 1 AS company_id WHERE NOT EXISTS (SELECT 1 FROM companies)
) c
JOIN (
    SELECT 'ap' AS module_code, 'payable' AS posting_key, '2100' AS account_no, 'Accounts Payable' AS description UNION ALL
    SELECT 'ap', 'grni', '2300', 'Goods Received Not Invoiced' UNION ALL
    SELECT 'ap', 'manual_expense', '6200', 'Manual A/P Expense' UNION ALL
    SELECT 'ap', 'inventory', '1300', 'Purchased Inventory' UNION ALL
    SELECT 'ap', 'input_vat', '1400', 'Input VAT' UNION ALL
    SELECT 'ar', 'receivable', '1200', 'Accounts Receivable' UNION ALL
    SELECT 'ar', 'sales_revenue', '4100', 'Sales Revenue' UNION ALL
    SELECT 'ar', 'output_vat', '2200', 'Output VAT' UNION ALL
    SELECT 'sales', 'cogs', '5000', 'Cost of Goods Sold' UNION ALL
    SELECT 'sales', 'inventory', '1300', 'Inventory' UNION ALL
    SELECT 'inventory', 'inventory', '1300', 'Inventory' UNION ALL
    SELECT 'inventory', 'adjustment_gain', '7000', 'Inventory Adjustment Gain' UNION ALL
    SELECT 'inventory', 'adjustment_loss', '8000', 'Inventory Adjustment Loss' UNION ALL
    SELECT 'cashbank', 'cash_bank', '1120', 'Default bank account'
) p
WHERE NOT EXISTS (
    SELECT 1
    FROM gl_posting_profiles gp
    WHERE gp.company_id = c.company_id
      AND gp.module_code = p.module_code
      AND gp.posting_key = p.posting_key
);

UPDATE gl_posting_profiles gp
JOIN (
    SELECT c.company_id, p.module_code, p.posting_key, p.account_no, p.description
    FROM (
        SELECT id AS company_id FROM companies
        UNION
        SELECT 1 AS company_id WHERE NOT EXISTS (SELECT 1 FROM companies)
    ) c
    JOIN (
        SELECT 'ap' AS module_code, 'payable' AS posting_key, '2100' AS account_no, 'Accounts Payable' AS description UNION ALL
        SELECT 'ap', 'grni', '2300', 'Goods Received Not Invoiced' UNION ALL
        SELECT 'ap', 'manual_expense', '6200', 'Manual A/P Expense' UNION ALL
        SELECT 'ap', 'inventory', '1300', 'Purchased Inventory' UNION ALL
        SELECT 'ap', 'input_vat', '1400', 'Input VAT' UNION ALL
        SELECT 'ar', 'receivable', '1200', 'Accounts Receivable' UNION ALL
        SELECT 'ar', 'sales_revenue', '4100', 'Sales Revenue' UNION ALL
        SELECT 'ar', 'output_vat', '2200', 'Output VAT' UNION ALL
        SELECT 'sales', 'cogs', '5000', 'Cost of Goods Sold' UNION ALL
        SELECT 'sales', 'inventory', '1300', 'Inventory' UNION ALL
        SELECT 'inventory', 'inventory', '1300', 'Inventory' UNION ALL
        SELECT 'inventory', 'adjustment_gain', '7000', 'Inventory Adjustment Gain' UNION ALL
        SELECT 'inventory', 'adjustment_loss', '8000', 'Inventory Adjustment Loss' UNION ALL
        SELECT 'cashbank', 'cash_bank', '1120', 'Default bank account'
    ) p
) d ON d.company_id = gp.company_id
   AND d.module_code = gp.module_code
   AND d.posting_key = gp.posting_key
SET gp.account_no = CASE
        WHEN gp.module_code = 'cashbank' AND gp.posting_key = 'cash_bank' THEN '1120'
        ELSE COALESCE(NULLIF(gp.account_no, ''), d.account_no)
    END,
    gp.description = COALESCE(NULLIF(gp.description, ''), d.description),
    gp.is_active = 1,
    gp.updated_at = NOW();

UPDATE gl_posting_profiles
SET account_no = '1120',
    description = COALESCE(NULLIF(description, ''), 'Default bank account'),
    is_active = 1,
    updated_at = NOW()
WHERE module_code = 'cashbank'
  AND posting_key = 'cash_bank'
  AND (account_no IS NULL OR TRIM(account_no) = '' OR account_no = '1100');

SET @has_cash_bank_accounts := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_accounts');
SET @has_cash_bank_gl_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_accounts' AND COLUMN_NAME='gl_account_no');
SET @sql := IF(@has_cash_bank_accounts=1 AND @has_cash_bank_gl_col=1,
    'UPDATE cash_bank_accounts SET gl_account_no = CASE WHEN account_type = ''cash'' THEN ''1110'' ELSE ''1120'' END WHERE is_active = 1 AND (gl_account_no IS NULL OR TRIM(gl_account_no) = '''' OR gl_account_no = ''1100'')',
    'SELECT ''cash_bank_accounts.gl_account_no not available - skipped'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'CASHBANK_POSTING_PROFILE_POSTABLE' AS check_name, COUNT(*) AS ready_count, 1 AS expected_count
FROM gl_posting_profiles gp
JOIN chart_accounts ca
  ON ca.company_id = gp.company_id
 AND ca.account_no = gp.account_no
WHERE gp.module_code = 'cashbank'
  AND gp.posting_key = 'cash_bank'
  AND gp.account_no <> '1100'
  AND COALESCE(gp.is_active,1) = 1
  AND COALESCE(ca.is_active,1) = 1
  AND COALESCE(ca.is_postable,0) = 1;

SELECT 'GL_POSTING_PROFILE_DEFAULTS_READY' AS check_name, COUNT(*) AS ready_count, 14 AS expected_count
FROM gl_posting_profiles
WHERE module_code IN ('ap','ar','sales','inventory','cashbank')
  AND posting_key IN ('payable','grni','manual_expense','inventory','input_vat','receivable','sales_revenue','output_vat','cogs','adjustment_gain','adjustment_loss','cash_bank')
  AND COALESCE(is_active,1) = 1;
