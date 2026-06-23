-- SQL version of app/Database/Seeds/FinanceGlSeeder.php
-- Purpose: seed default GL book, chart of accounts, and posting profiles for every active company.
-- Safe to run multiple times.
-- Important: module_code and posting_key use lowercase because PostingProfileService lowercases lookup keys.

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

UPDATE chart_accounts ca
INNER JOIN companies c ON c.id = ca.company_id
SET ca.is_active = 1,
    ca.updated_at = NOW()
WHERE COALESCE(c.is_active, 1) = 1
  AND ca.account_no IN ('1000','1100','1110','1120','1200','1300','1400','2000','2100','2200','2300','3000','3100','4000','4100','5000','6000','6100','6200','7000','8000')
  AND COALESCE(ca.is_active, 0) <> 1;

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
    SELECT 'ap' module_code, 'payable' posting_key, '2100' account_no, 'Accounts Payable' description
    UNION ALL SELECT 'ap', 'grni', '2300', 'Goods Received Not Invoiced'
    UNION ALL SELECT 'ap', 'manual_expense', '6200', 'Manual A/P Expense'
    UNION ALL SELECT 'ap', 'inventory', '1300', 'Purchased Inventory'
    UNION ALL SELECT 'ap', 'input_vat', '1400', 'Input VAT'
    UNION ALL SELECT 'ar', 'receivable', '1200', 'Accounts Receivable'
    UNION ALL SELECT 'ar', 'sales_revenue', '4100', 'Sales Revenue'
    UNION ALL SELECT 'ar', 'output_vat', '2200', 'Output VAT'
    UNION ALL SELECT 'sales', 'cogs', '5000', 'Cost of Goods Sold'
    UNION ALL SELECT 'sales', 'inventory', '1300', 'Inventory'
    UNION ALL SELECT 'inventory', 'inventory', '1300', 'Inventory'
    UNION ALL SELECT 'inventory', 'adjustment_gain', '7000', 'Inventory Adjustment Gain'
    UNION ALL SELECT 'inventory', 'adjustment_loss', '8000', 'Inventory Adjustment Loss'
    UNION ALL SELECT 'cashbank', 'cash_bank', '1100', 'Cash and Bank'
    UNION ALL SELECT 'pos', 'cash_receipt', '1110', 'Default POS cash receipt account'
    UNION ALL SELECT 'pos', 'sales_revenue', '4100', 'Default POS sales revenue account'
    UNION ALL SELECT 'pos', 'output_vat', '2200', 'Default POS output VAT account'
    UNION ALL SELECT 'pos', 'cogs', '5000', 'Default POS COGS account'
    UNION ALL SELECT 'pos', 'inventory', '1300', 'Default POS inventory account'
) p
WHERE COALESCE(c.is_active, 1) = 1
  AND NOT EXISTS (
      SELECT 1
      FROM gl_posting_profiles gpp
      WHERE gpp.company_id = c.id
        AND gpp.module_code = p.module_code
        AND gpp.posting_key = p.posting_key
        AND gpp.deleted_at IS NULL
  );

UPDATE gl_posting_profiles
SET module_code = LOWER(module_code),
    posting_key = LOWER(posting_key),
    is_active = 1,
    updated_by = 'system',
    updated_at = NOW()
WHERE deleted_at IS NULL;

-- =========================================================
-- 4) Verification
-- Expected for every active company:
--   gl_books_MAIN              = active company count
--   chart_accounts_seeded      = active company count * 21
--   gl_posting_profiles_seeded = active company count * 19
--   account_2300_active        = active company count
-- =========================================================
SELECT 'active_companies' AS check_name, COUNT(*) AS total
FROM companies c
WHERE COALESCE(c.is_active, 1) = 1
UNION ALL
SELECT 'gl_books_MAIN', COUNT(*)
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
  AND ca.is_active = 1
UNION ALL
SELECT 'gl_posting_profiles_seeded', COUNT(*)
FROM gl_posting_profiles gpp
INNER JOIN companies c ON c.id = gpp.company_id
WHERE COALESCE(c.is_active, 1) = 1
  AND gpp.deleted_at IS NULL
  AND gpp.is_active = 1
  AND CONCAT(gpp.module_code, '.', gpp.posting_key) IN (
      'ap.payable','ap.grni','ap.manual_expense','ap.inventory','ap.input_vat',
      'ar.receivable','ar.sales_revenue','ar.output_vat',
      'sales.cogs','sales.inventory',
      'inventory.inventory','inventory.adjustment_gain','inventory.adjustment_loss',
      'cashbank.cash_bank',
      'pos.cash_receipt','pos.sales_revenue','pos.output_vat','pos.cogs','pos.inventory'
  )
UNION ALL
SELECT 'account_2300_active', COUNT(*)
FROM chart_accounts ca
INNER JOIN companies c ON c.id = ca.company_id
WHERE COALESCE(c.is_active, 1) = 1
  AND ca.account_no = '2300'
  AND ca.is_active = 1;
