-- SQL version of app/Database/Seeds/FinanceGlSeeder.php
-- Purpose: seed default GL book, chart of accounts, and posting profiles for every active company.
-- Safe to run multiple times.

-- =========================================================
-- 1) Default GL Book
-- =========================================================
INSERT INTO gl_books (
    company_id,
    book_code,
    book_name,
    currency_code,
    is_default,
    is_active,
    created_at,
    updated_at
)
SELECT
    c.id,
    'MAIN',
    'Main Ledger Book',
    'IDR',
    1,
    1,
    NOW(),
    NOW()
FROM companies c
WHERE COALESCE(c.is_active, 1) = 1
  AND NOT EXISTS (
      SELECT 1
      FROM gl_books gb
      WHERE gb.company_id = c.id
        AND gb.book_code = 'MAIN'
  );

-- =========================================================
-- 2) Chart of Accounts
-- =========================================================
INSERT INTO chart_accounts (
    company_id,
    account_no,
    account_name,
    account_type,
    normal_balance,
    parent_account_no,
    is_postable,
    is_active,
    created_at,
    updated_at
)
SELECT c.id, a.account_no, a.account_name, a.account_type, a.normal_balance, a.parent_account_no, a.is_postable, 1, NOW(), NOW()
FROM companies c
JOIN (
    SELECT '1000' account_no, 'Asset' account_name, 'asset' account_type, 'debit' normal_balance, NULL parent_account_no, 0 is_postable
    UNION ALL SELECT '1100', 'Cash and Bank', 'asset', 'debit', '1000', 0
    UNION ALL SELECT '1110', 'Cash on Hand', 'asset', 'debit', '1100', 1
    UNION ALL SELECT '1120', 'Bank Account', 'asset', 'debit', '1100', 1
    UNION ALL SELECT '1200', 'Accounts Receivable', 'asset', 'debit', '1000', 1
    UNION ALL SELECT '1300', 'Inventory', 'asset', 'debit', '1000', 1
    UNION ALL SELECT '1400', 'Input VAT', 'asset', 'debit', '1000', 1
    UNION ALL SELECT '2000', 'Liability', 'liability', 'credit', NULL, 0
    UNION ALL SELECT '2100', 'Accounts Payable', 'liability', 'credit', '2000', 1
    UNION ALL SELECT '2200', 'Output VAT', 'liability', 'credit', '2000', 1
    UNION ALL SELECT '2300', 'Goods Received Not Invoiced', 'liability', 'credit', '2000', 1
    UNION ALL SELECT '3000', 'Equity', 'equity', 'credit', NULL, 0
    UNION ALL SELECT '3100', 'Owner Capital', 'equity', 'credit', '3000', 1
    UNION ALL SELECT '4000', 'Revenue', 'revenue', 'credit', NULL, 0
    UNION ALL SELECT '4100', 'Sales Revenue', 'revenue', 'credit', '4000', 1
    UNION ALL SELECT '5000', 'Cost of Goods Sold', 'expense', 'debit', NULL, 1
    UNION ALL SELECT '6000', 'Operating Expense', 'expense', 'debit', NULL, 0
    UNION ALL SELECT '6100', 'Salary Expense', 'expense', 'debit', '6000', 1
    UNION ALL SELECT '6200', 'General Expense', 'expense', 'debit', '6000', 1
    UNION ALL SELECT '7000', 'Other Income', 'revenue', 'credit', NULL, 1
    UNION ALL SELECT '8000', 'Other Expense', 'expense', 'debit', NULL, 1
) a
WHERE COALESCE(c.is_active, 1) = 1
  AND NOT EXISTS (
      SELECT 1
      FROM chart_accounts ca
      WHERE ca.company_id = c.id
        AND ca.account_no = a.account_no
  );

-- =========================================================
-- 3) GL Posting Profiles
-- =========================================================
INSERT INTO gl_posting_profiles (
    company_id,
    module_code,
    posting_key,
    account_no,
    description,
    is_active,
    created_by,
    updated_by,
    created_at,
    updated_at
)
SELECT c.id, p.module_code, p.posting_key, p.account_no, p.description, 1, 'system', 'system', NOW(), NOW()
FROM companies c
JOIN (
    SELECT 'SALES' module_code, 'AR_CONTROL' posting_key, '1200' account_no, 'Default A/R control account' description
    UNION ALL SELECT 'SALES', 'SALES_REVENUE', '4100', 'Default sales revenue account'
    UNION ALL SELECT 'SALES', 'OUTPUT_VAT', '2200', 'Default output VAT account'
    UNION ALL SELECT 'SALES', 'COGS', '5000', 'Default COGS account'
    UNION ALL SELECT 'SALES', 'INVENTORY', '1300', 'Default inventory account'
    UNION ALL SELECT 'PURCHASE', 'AP_CONTROL', '2100', 'Default A/P control account'
    UNION ALL SELECT 'PURCHASE', 'GRNI', '2300', 'Default goods received not invoiced account'
    UNION ALL SELECT 'PURCHASE', 'INVENTORY', '1300', 'Default purchased inventory account'
    UNION ALL SELECT 'PURCHASE', 'INPUT_VAT', '1400', 'Default input VAT account'
    UNION ALL SELECT 'CASHBANK', 'CASH_ON_HAND', '1110', 'Default cash account'
    UNION ALL SELECT 'CASHBANK', 'BANK', '1120', 'Default bank account'
    UNION ALL SELECT 'POS', 'CASH_RECEIPT', '1110', 'Default POS cash receipt account'
    UNION ALL SELECT 'POS', 'SALES_REVENUE', '4100', 'Default POS sales revenue account'
    UNION ALL SELECT 'POS', 'OUTPUT_VAT', '2200', 'Default POS output VAT account'
    UNION ALL SELECT 'POS', 'COGS', '5000', 'Default POS COGS account'
    UNION ALL SELECT 'POS', 'INVENTORY', '1300', 'Default POS inventory account'
) p
WHERE COALESCE(c.is_active, 1) = 1
  AND NOT EXISTS (
      SELECT 1
      FROM gl_posting_profiles gpp
      WHERE gpp.company_id = c.id
        AND gpp.module_code = p.module_code
        AND gpp.posting_key = p.posting_key
  );

-- =========================================================
-- 4) Verification
-- =========================================================
SELECT 'gl_books_MAIN' AS check_name, COUNT(*) AS total
FROM gl_books gb
INNER JOIN companies c ON c.id = gb.company_id
WHERE COALESCE(c.is_active, 1) = 1
  AND gb.book_code = 'MAIN'
UNION ALL
SELECT 'chart_accounts_seeded', COUNT(*)
FROM chart_accounts ca
INNER JOIN companies c ON c.id = ca.company_id
WHERE COALESCE(c.is_active, 1) = 1
  AND ca.account_no IN ('1000','1100','1110','1120','1200','1300','1400','2000','2100','2200','2300','3000','3100','4000','4100','5000','6000','6100','6200','7000','8000')
UNION ALL
SELECT 'gl_posting_profiles_seeded', COUNT(*)
FROM gl_posting_profiles gpp
INNER JOIN companies c ON c.id = gpp.company_id
WHERE COALESCE(c.is_active, 1) = 1
  AND gpp.module_code IN ('SALES','PURCHASE','CASHBANK','POS');
