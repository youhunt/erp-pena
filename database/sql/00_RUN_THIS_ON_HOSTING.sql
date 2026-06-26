-- ERP PENA - ONE FILE HOSTING SETUP
-- Jalankan file ini di phpMyAdmin/cPanel hanya kalau server tidak bisa menjalankan php spark migrate + seeder.
--
-- PENTING UNTUK PHPMYADMIN / CPANEL:
-- 1. Pilih database ERP dari sidebar kiri terlebih dahulu.
-- 2. Baru jalankan file ini.
-- 3. File ini sengaja TIDAK memakai USE `nama_database` karena nama DB hosting bisa berbeda-beda.
--
-- File ini adalah fallback hosting dari:
-- app/Database/Migrations/*
-- app/Database/Seeds/CoreFinanceSeeder.php

SET @db := DATABASE();
SELECT @db AS selected_database;

-- =========================================================
-- 1. Transaction codes required by document numbering
-- Mirrors CoreFinanceSeeder::seedTransactionCodes()
-- =========================================================
CREATE TABLE IF NOT EXISTS transaction_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transaction_codes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'PO', 'Purchase Order', 'Purchase order document numbering', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'PO');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'PR', 'Purchase Receipt', 'Purchase receipt document numbering', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'PR');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'SO', 'Sales Order', 'Sales order document numbering', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'SO');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'SD', 'Sales Delivery', 'Sales delivery document numbering', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'SD');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'SI', 'Sales Invoice', 'Sales invoice document numbering', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'SI');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'PI', 'Purchase Invoice', 'Purchase invoice document numbering', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'PI');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'JV', 'Journal Voucher', 'General ledger journal voucher numbering', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'JV');
UPDATE transaction_codes SET is_active = 1, updated_at = NOW() WHERE code IN ('PO','PR','SO','SD','SI','PI','JV');

-- =========================================================
-- 2. Currency master compatibility
-- Mirrors CoreFinanceCashBankMigration + CoreFinanceSeeder::seedCurrencies()
-- =========================================================
CREATE TABLE IF NOT EXISTS currencies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    code VARCHAR(6) NOT NULL,
    name VARCHAR(500) NOT NULL,
    rounding DECIMAL(10,4) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_currencies_code (code),
    KEY idx_currencies_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='currencies' AND COLUMN_NAME='company_id')=0,'ALTER TABLE currencies ADD COLUMN company_id INT NULL','SELECT ''currencies.company_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='currencies' AND COLUMN_NAME='rounding')=0,'ALTER TABLE currencies ADD COLUMN rounding DECIMAL(10,4) NOT NULL DEFAULT 0','SELECT ''currencies.rounding exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='currencies' AND COLUMN_NAME='is_active')=0,'ALTER TABLE currencies ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1','SELECT ''currencies.is_active exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='currencies' AND COLUMN_NAME='created_by')=0,'ALTER TABLE currencies ADD COLUMN created_by INT NULL','SELECT ''currencies.created_by exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='currencies' AND COLUMN_NAME='updated_by')=0,'ALTER TABLE currencies ADD COLUMN updated_by INT NULL','SELECT ''currencies.updated_by exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='currencies' AND COLUMN_NAME='created_at')=0,'ALTER TABLE currencies ADD COLUMN created_at DATETIME NULL','SELECT ''currencies.created_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='currencies' AND COLUMN_NAME='updated_at')=0,'ALTER TABLE currencies ADD COLUMN updated_at DATETIME NULL','SELECT ''currencies.updated_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='currencies' AND COLUMN_NAME='deleted_at')=0,'ALTER TABLE currencies ADD COLUMN deleted_at DATETIME NULL','SELECT ''currencies.deleted_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO currencies (company_id, code, name, rounding, is_active, created_at, updated_at)
SELECT NULL, 'IDR', 'Indonesian Rupiah', 0, 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM currencies WHERE code = 'IDR');
INSERT INTO currencies (company_id, code, name, rounding, is_active, created_at, updated_at)
SELECT NULL, 'USD', 'US Dollar', 0.01, 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM currencies WHERE code = 'USD');

-- =========================================================
-- 3. Cash Bank master, employee, and rate foundation
-- Mirrors CoreFinanceCashBankMigration
-- =========================================================
SET @has_table := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_accounts');
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_accounts table missing - skipped bank_branch'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_accounts' AND COLUMN_NAME='bank_branch')=0,'ALTER TABLE cash_bank_accounts ADD COLUMN bank_branch VARCHAR(50) NULL','SELECT ''cash_bank_accounts.bank_branch exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_accounts table missing - skipped bank_code'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_accounts' AND COLUMN_NAME='bank_code')=0,'ALTER TABLE cash_bank_accounts ADD COLUMN bank_code VARCHAR(50) NULL','SELECT ''cash_bank_accounts.bank_code exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_accounts table missing - skipped bank_account'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_accounts' AND COLUMN_NAME='bank_account')=0,'ALTER TABLE cash_bank_accounts ADD COLUMN bank_account VARCHAR(50) NULL','SELECT ''cash_bank_accounts.bank_account exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_accounts table missing - skipped pic'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_accounts' AND COLUMN_NAME='pic')=0,'ALTER TABLE cash_bank_accounts ADD COLUMN pic VARCHAR(100) NULL','SELECT ''cash_bank_accounts.pic exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_accounts table missing - skipped phone'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_accounts' AND COLUMN_NAME='phone')=0,'ALTER TABLE cash_bank_accounts ADD COLUMN phone VARCHAR(20) NULL','SELECT ''cash_bank_accounts.phone exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_accounts table missing - skipped address'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_accounts' AND COLUMN_NAME='address')=0,'ALTER TABLE cash_bank_accounts ADD COLUMN address VARCHAR(100) NULL','SELECT ''cash_bank_accounts.address exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    employee_code VARCHAR(12) NOT NULL,
    site_code VARCHAR(12) NULL,
    department_code VARCHAR(12) NULL,
    name VARCHAR(500) NOT NULL,
    description VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_employees_company_code (company_id, employee_code),
    KEY idx_employees_site_dept (site_code, department_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS currency_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    rate_type VARCHAR(12) NOT NULL,
    from_currency VARCHAR(6) NOT NULL,
    to_currency VARCHAR(6) NOT NULL,
    rate_date DATE NOT NULL,
    amount DECIMAL(20,12) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_currency_rates_pair_date (from_currency, to_currency, rate_date),
    KEY idx_currency_rates_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_table := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_entries');
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_entries table missing - skipped rate_type'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_entries' AND COLUMN_NAME='rate_type')=0,'ALTER TABLE cash_bank_entries ADD COLUMN rate_type VARCHAR(12) NULL','SELECT ''cash_bank_entries.rate_type exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_entries table missing - skipped exchange_rate'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_entries' AND COLUMN_NAME='exchange_rate')=0,'ALTER TABLE cash_bank_entries ADD COLUMN exchange_rate DECIMAL(20,12) NOT NULL DEFAULT 1','SELECT ''cash_bank_entries.exchange_rate exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_entries table missing - skipped base_currency'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_entries' AND COLUMN_NAME='base_currency')=0,'ALTER TABLE cash_bank_entries ADD COLUMN base_currency VARCHAR(6) NOT NULL DEFAULT ''IDR''','SELECT ''cash_bank_entries.base_currency exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_table=0,'SELECT ''cash_bank_entries table missing - skipped base_amount'' AS info',IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='cash_bank_entries' AND COLUMN_NAME='base_amount')=0,'ALTER TABLE cash_bank_entries ADD COLUMN base_amount DECIMAL(20,2) NOT NULL DEFAULT 0','SELECT ''cash_bank_entries.base_amount exists'' AS info')); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_table=0,'SELECT ''cash_bank_entries table missing - skipped rate data update'' AS info','UPDATE cash_bank_entries SET exchange_rate = CASE WHEN exchange_rate IS NULL OR exchange_rate = 0 THEN 1 ELSE exchange_rate END, base_currency = COALESCE(NULLIF(base_currency, ''''), ''IDR''), base_amount = CASE WHEN base_amount IS NULL OR base_amount = 0 THEN amount ELSE base_amount END');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================================================
-- 4. Item import mapping foundation
-- Mirrors CoreFinanceCashBankMigration
-- =========================================================
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
    KEY idx_item_import_mapping_item (item_code),
    KEY idx_item_import_mapping_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. Planning / MRP foundation
-- Mirrors CoreProductionCostingFoundation migration
-- =========================================================
CREATE TABLE IF NOT EXISTS production_forecasts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    site_id INT NULL,
    forecast_no VARCHAR(60) NOT NULL,
    forecast_date DATE NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    item_code VARCHAR(80) NOT NULL,
    item_name VARCHAR(255) NULL,
    qty_forecast DECIMAL(20,6) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    notes VARCHAR(500) NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_production_forecasts_item_period (company_id, site_id, item_code, period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_mrp_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    site_id INT NULL,
    run_no VARCHAR(60) NOT NULL,
    run_date DATE NOT NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    notes VARCHAR(500) NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_production_mrp_runs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_mrp_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_mrp_run_id BIGINT UNSIGNED NOT NULL,
    company_id INT NOT NULL,
    site_id INT NULL,
    item_code VARCHAR(80) NOT NULL,
    item_name VARCHAR(255) NULL,
    source_type VARCHAR(40) NULL,
    demand_qty DECIMAL(20,6) NOT NULL DEFAULT 0,
    onhand_qty DECIMAL(20,6) NOT NULL DEFAULT 0,
    supply_qty DECIMAL(20,6) NOT NULL DEFAULT 0,
    planned_qty DECIMAL(20,6) NOT NULL DEFAULT 0,
    action_type VARCHAR(40) NULL,
    action_status VARCHAR(40) NOT NULL DEFAULT 'planned',
    required_date DATE NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_production_mrp_lines_run (production_mrp_run_id),
    KEY idx_production_mrp_lines_item (company_id, site_id, item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_mrp_planned_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    site_id INT NULL,
    production_mrp_run_id BIGINT UNSIGNED NULL,
    production_mrp_line_id BIGINT UNSIGNED NULL,
    planned_order_no VARCHAR(60) NOT NULL,
    planned_order_type VARCHAR(40) NOT NULL,
    item_code VARCHAR(80) NOT NULL,
    item_name VARCHAR(255) NULL,
    qty_planned DECIMAL(20,6) NOT NULL DEFAULT 0,
    required_date DATE NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'planned',
    source_document_type VARCHAR(60) NULL,
    source_document_id BIGINT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_mrp_planned_orders_status (status),
    KEY idx_mrp_planned_orders_item (company_id, site_id, item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 6. Costing foundation
-- Mirrors CoreProductionCostingFoundation + CoreFinanceSeeder::seedCostTypes()
-- =========================================================
CREATE TABLE IF NOT EXISTS costing_cost_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    type VARCHAR(20) NOT NULL,
    description VARCHAR(300) NOT NULL,
    cost_group VARCHAR(30) NOT NULL DEFAULT 'Material',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_costing_cost_types_group (cost_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS costing_item_costs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    item_code VARCHAR(80) NOT NULL,
    item_name VARCHAR(255) NULL,
    department_code VARCHAR(40) NULL,
    warehouse_code VARCHAR(40) NULL,
    description VARCHAR(500) NULL,
    this_item_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
    total_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
    calculated_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_costing_item_costs_item (company_id, site_id, item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS costing_item_cost_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    costing_item_cost_id BIGINT UNSIGNED NOT NULL,
    bom_no VARCHAR(80) NULL,
    child_item_code VARCHAR(80) NULL,
    child_item_name VARCHAR(255) NULL,
    bom_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
    work_center_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
    total_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_costing_item_cost_lines_parent (costing_item_cost_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'TK', 'Tenaga Kerja', 'Labor', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'TK');
INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'Listrik', 'Biaya listrik', 'Overhead', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'Listrik');
INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'Pisau', 'Biaya pisau/cutting tool', 'Overhead', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'Pisau');
INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'Bensin', 'Biaya bensin', 'Overhead', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'Bensin');

-- =========================================================
-- 7. GL Posting Profile defaults
-- Mirrors CoreFinanceSeeder::seedPostingProfiles()
-- =========================================================
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
) d ON d.company_id = gp.company_id AND d.module_code = gp.module_code AND d.posting_key = gp.posting_key
SET gp.account_no = d.account_no,
    gp.description = COALESCE(NULLIF(gp.description, ''), d.description),
    gp.is_active = 1,
    gp.updated_at = NOW()
WHERE gp.account_no IS NULL OR TRIM(gp.account_no) = '';

-- =========================================================
-- 8. Warehouse helper index, safe with existing foreign keys
-- Mirrors FixWarehouseUniqueScope migration
-- =========================================================
SET @has_wh_table := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='warehouses');
SET @has_wh_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='warehouses' AND INDEX_NAME='idx_warehouses_company_site_dept_code');
SET @sql := IF(@has_wh_table=0,'SELECT ''warehouses table missing - skipped helper index'' AS info',IF(@has_wh_idx=0,'ALTER TABLE warehouses ADD INDEX idx_warehouses_company_site_dept_code (company_id, site_id, department_id, code)','SELECT ''warehouse helper index exists'' AS info'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================================================
-- 9. Menu route finalization
-- =========================================================
SET @has_menu_table := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='menu_items');
SET @sql := IF(@has_menu_table=0,'SELECT ''menu_items table missing - skipped route finalization'' AS info','UPDATE menu_items SET route = ''cash-bank/accounts'', updated_at = NOW() WHERE label = ''Cash Bank ID'''); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_menu_table=0,'SELECT ''menu_items table missing - skipped route finalization'' AS info','UPDATE menu_items SET route = ''cash-bank/currencies'', updated_at = NOW() WHERE label = ''Currency'''); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_menu_table=0,'SELECT ''menu_items table missing - skipped route finalization'' AS info','UPDATE menu_items SET route = ''cash-bank/employees'', updated_at = NOW() WHERE label = ''Employee ID'''); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_menu_table=0,'SELECT ''menu_items table missing - skipped route finalization'' AS info','UPDATE menu_items SET route = ''cash-bank/rates'', updated_at = NOW() WHERE label = ''Rate Master'''); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_menu_table=0,'SELECT ''menu_items table missing - skipped route finalization'' AS info','UPDATE menu_items SET route = ''production/forecasts'', updated_at = NOW() WHERE label = ''Forecast'''); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_menu_table=0,'SELECT ''menu_items table missing - skipped route finalization'' AS info','UPDATE menu_items SET route = ''production/mps'', updated_at = NOW() WHERE label = ''MPS'''); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_menu_table=0,'SELECT ''menu_items table missing - skipped route finalization'' AS info','UPDATE menu_items SET route = ''production/mrp'', updated_at = NOW() WHERE label = ''MRP'''); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_menu_table=0,'SELECT ''menu_items table missing - skipped route finalization'' AS info','UPDATE menu_items SET route = ''production/planned-released'', updated_at = NOW() WHERE label = ''Planned Released'''); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================================================
-- 10. Final check
-- =========================================================
SELECT DATABASE() AS selected_database;

SELECT 'ERP_CORE_REQUIRED_TABLES_READY' AS check_name, COUNT(*) AS ready_count, 36 AS expected_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'companies','sites','departments','warehouses','locations','items','uoms',
    'transaction_codes','document_number_sequences','chart_accounts','gl_entries','gl_entry_lines','gl_posting_profiles',
    'inventory_stock_balances','cash_bank_accounts','cash_bank_entries','currencies','currency_rates','employees',
    'purchase_orders','purchase_order_lines','purchase_receipts','sales_orders','sales_order_lines',
    'production_boms','production_bom_lines','production_forecasts','production_mrp_runs','production_mrp_lines','production_mrp_planned_orders',
    'costing_cost_types','costing_item_costs','costing_item_cost_lines','item_import_mappings','menu_items','users'
  );

SELECT 'MRP_COSTING_TABLES_READY' AS check_name, COUNT(*) AS ready_count, 6 AS expected_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'production_mrp_runs','production_mrp_lines','production_mrp_planned_orders',
    'costing_cost_types','costing_item_costs','costing_item_cost_lines'
  );

SELECT 'CASH_BANK_ENTRY_RATE_COLUMNS_READY' AS check_name, COUNT(*) AS ready_count, 4 AS expected_count
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'cash_bank_entries'
  AND COLUMN_NAME IN ('rate_type','exchange_rate','base_currency','base_amount');

SELECT 'DOCUMENT_NUMBERING_REQUIRED_CODES_READY' AS check_name, COUNT(*) AS ready_count, 7 AS expected_count
FROM transaction_codes
WHERE code IN ('PO','PR','SO','SD','SI','PI','JV') AND COALESCE(is_active,1) = 1;

SELECT 'GL_POSTING_PROFILE_DEFAULTS_READY' AS check_name, COUNT(*) AS ready_count, 14 AS expected_count
FROM gl_posting_profiles
WHERE module_code IN ('ap','ar','sales','inventory','cashbank')
  AND posting_key IN ('payable','grni','manual_expense','inventory','input_vat','receivable','sales_revenue','output_vat','cogs','adjustment_gain','adjustment_loss','cash_bank')
  AND COALESCE(is_active,1) = 1;
