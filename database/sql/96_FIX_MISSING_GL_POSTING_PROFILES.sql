-- ERP PENA - QUICK FIX MISSING GL POSTING PROFILES
-- Jalankan di phpMyAdmin jika muncul error:
-- #1146 - Table '<database>.gl_posting_profiles' doesn't exist
-- atau /system/core-health FAIL pada CORE_TABLE_GL_POSTING_PROFILES.
--
-- Catatan:
-- - Script ini aman dijalankan berulang.
-- - Jika tabel companies sudah ada, default posting profile dibuat untuk semua company.
-- - Jika companies belum ada, dibuat fallback company_id = 1 supaya aplikasi tidak error saat awal setup.

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
    SELECT 'cashbank', 'cash_bank', '1100', 'Cash and Bank'
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
        SELECT 'cashbank', 'cash_bank', '1100', 'Cash and Bank'
    ) p
) d ON d.company_id = gp.company_id
   AND d.module_code = gp.module_code
   AND d.posting_key = gp.posting_key
SET gp.account_no = d.account_no,
    gp.description = COALESCE(NULLIF(gp.description, ''), d.description),
    gp.is_active = 1,
    gp.updated_at = NOW()
WHERE gp.account_no IS NULL OR TRIM(gp.account_no) = '';

SELECT 'GL_POSTING_PROFILE_DEFAULTS_READY' AS check_name, COUNT(*) AS ready_count, 14 AS expected_count
FROM gl_posting_profiles
WHERE module_code IN ('ap','ar','sales','inventory','cashbank')
  AND posting_key IN ('payable','grni','manual_expense','inventory','input_vat','receivable','sales_revenue','output_vat','cogs','adjustment_gain','adjustment_loss','cash_bank')
  AND COALESCE(is_active,1) = 1;
