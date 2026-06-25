-- ERP PENA - ERP Core Completion Finalizer
-- Run this after git pull to complete core database patches used by current ERP modules.
-- Safe to rerun on MariaDB/MySQL versions that support IF NOT EXISTS on ALTER ADD COLUMN.

USE `dberp_pena`;

-- =========================================================
-- 1. Required transaction codes for document numbering
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
SELECT 'PO', 'Purchase Order', 'Purchase order document numbering', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'PO');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'PR', 'Purchase Receipt', 'Purchase receipt document numbering', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'PR');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'SO', 'Sales Order', 'Sales order document numbering', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'SO');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'SD', 'Sales Delivery', 'Sales delivery document numbering', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'SD');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'SI', 'Sales Invoice', 'Sales invoice document numbering', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'SI');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'PI', 'Purchase Invoice', 'Purchase invoice document numbering', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'PI');
INSERT INTO transaction_codes (code, name, description, is_active, created_at, updated_at)
SELECT 'JV', 'Journal Voucher', 'General ledger journal voucher numbering', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'JV');

UPDATE transaction_codes
SET is_active = 1, updated_at = NOW()
WHERE code IN ('PO','PR','SO','SD','SI','PI','JV');

-- =========================================================
-- 2. Cash Bank master and currency/rate/employee foundation
-- =========================================================
ALTER TABLE cash_bank_accounts
    ADD COLUMN IF NOT EXISTS bank_branch VARCHAR(50) NULL AFTER site_id,
    ADD COLUMN IF NOT EXISTS bank_code VARCHAR(50) NULL AFTER bank_branch,
    ADD COLUMN IF NOT EXISTS bank_account VARCHAR(50) NULL AFTER cash_bank_name,
    ADD COLUMN IF NOT EXISTS pic VARCHAR(100) NULL AFTER bank_account,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL AFTER pic,
    ADD COLUMN IF NOT EXISTS address VARCHAR(100) NULL AFTER phone;

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
    UNIQUE KEY uq_currencies_company_code (company_id, code),
    KEY idx_currencies_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    UNIQUE KEY uq_employees_company_code (company_id, employee_code),
    KEY idx_employees_site_dept (site_code, department_code),
    KEY idx_employees_active (is_active)
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
    UNIQUE KEY uq_currency_rates_scope (company_id, rate_type, from_currency, to_currency, rate_date),
    KEY idx_currency_rates_pair_date (from_currency, to_currency, rate_date),
    KEY idx_currency_rates_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO currencies (company_id, code, name, rounding, is_active, created_at, updated_at)
SELECT NULL, 'IDR', 'Indonesian Rupiah', 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM currencies WHERE company_id IS NULL AND code = 'IDR');
INSERT INTO currencies (company_id, code, name, rounding, is_active, created_at, updated_at)
SELECT NULL, 'USD', 'US Dollar', 0.01, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM currencies WHERE company_id IS NULL AND code = 'USD');

-- =========================================================
-- 3. Cash Bank entry rate/base amount fields
-- =========================================================
ALTER TABLE cash_bank_entries
    ADD COLUMN IF NOT EXISTS rate_type VARCHAR(12) NULL AFTER currency_code,
    ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(20,12) NOT NULL DEFAULT 1 AFTER rate_type,
    ADD COLUMN IF NOT EXISTS base_currency VARCHAR(6) NOT NULL DEFAULT 'IDR' AFTER exchange_rate,
    ADD COLUMN IF NOT EXISTS base_amount DECIMAL(20,2) NOT NULL DEFAULT 0 AFTER base_currency;

UPDATE cash_bank_entries
SET
    exchange_rate = CASE WHEN exchange_rate IS NULL OR exchange_rate = 0 THEN 1 ELSE exchange_rate END,
    base_currency = COALESCE(NULLIF(base_currency, ''), 'IDR'),
    base_amount = CASE WHEN base_amount IS NULL OR base_amount = 0 THEN amount ELSE base_amount END;

-- =========================================================
-- 4. Item import mapping foundation for PO imports
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
    UNIQUE KEY uq_item_import_mapping_scope (company_id, site_id, source_type, normalized_imported_name),
    KEY idx_item_import_mapping_item (item_code),
    KEY idx_item_import_mapping_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. Planning/MRP foundation
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
    KEY idx_production_forecasts_item_period (company_id, site_id, item_code, period_start, period_end),
    KEY idx_production_forecasts_status (status)
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
    UNIQUE KEY uq_production_mrp_runs_run_no (company_id, run_no),
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
    KEY idx_production_mrp_lines_item (company_id, site_id, item_code),
    KEY idx_production_mrp_lines_action (action_type, action_status)
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
    UNIQUE KEY uq_mrp_planned_order_no (company_id, planned_order_no),
    KEY idx_mrp_planned_orders_status (status),
    KEY idx_mrp_planned_orders_item (company_id, site_id, item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 6. Costing foundation
-- =========================================================
CREATE TABLE IF NOT EXISTS costing_cost_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    type VARCHAR(10) NOT NULL,
    description VARCHAR(300) NOT NULL,
    cost_group VARCHAR(10) NOT NULL DEFAULT 'Material',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_costing_cost_types_company_type (company_id, type),
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
    KEY idx_costing_item_costs_item (company_id, site_id, item_code),
    KEY idx_costing_item_costs_active (is_active)
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
SELECT NULL, 'TK', 'Tenaga Kerja', 'Labor', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'TK');
INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'Listrik', 'Biaya listrik', 'Overhead', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'Listrik');
INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'Pisau', 'Biaya pisau/cutting tool', 'Overhead', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'Pisau');
INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'Bensin', 'Biaya bensin', 'Overhead', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'Bensin');

-- =========================================================
-- 7. Menu route finalization
-- =========================================================
UPDATE menu_items SET route = 'cash-bank/accounts', updated_at = NOW() WHERE label = 'Cash Bank ID';
UPDATE menu_items SET route = 'cash-bank/currencies', updated_at = NOW() WHERE label = 'Currency';
UPDATE menu_items SET route = 'cash-bank/employees', updated_at = NOW() WHERE label = 'Employee ID';
UPDATE menu_items SET route = 'cash-bank/rates', updated_at = NOW() WHERE label = 'Rate Master';
UPDATE menu_items SET route = 'production/forecasts', updated_at = NOW() WHERE label = 'Forecast';
UPDATE menu_items SET route = 'production/mps', updated_at = NOW() WHERE label = 'MPS';
UPDATE menu_items SET route = 'production/mrp', updated_at = NOW() WHERE label = 'MRP';
UPDATE menu_items SET route = 'production/planned-released', updated_at = NOW() WHERE label = 'Planned Released';

-- =========================================================
-- 8. Final readiness check summary
-- =========================================================
SELECT DATABASE() AS selected_database;

SELECT 'ERP_CORE_REQUIRED_TABLES_READY' AS check_name, COUNT(*) AS ready_count, 35 AS expected_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'companies','sites','departments','warehouses','locations','items','uoms',
    'transaction_codes','document_number_sequences','chart_accounts','gl_entries','gl_entry_lines',
    'inventory_stock_balances','cash_bank_accounts','cash_bank_entries','currencies','currency_rates','employees',
    'purchase_orders','purchase_order_lines','purchase_receipts','sales_orders','sales_order_lines',
    'production_boms','production_bom_lines','production_forecasts','production_mrp_runs','production_mrp_lines','production_mrp_planned_orders',
    'costing_cost_types','costing_item_costs','costing_item_cost_lines','item_import_mappings','menu_items','users'
  );

SELECT 'CASH_BANK_ENTRY_RATE_COLUMNS_READY' AS check_name, COUNT(*) AS ready_count, 4 AS expected_count
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'cash_bank_entries'
  AND COLUMN_NAME IN ('rate_type','exchange_rate','base_currency','base_amount');

SELECT 'DOCUMENT_NUMBERING_REQUIRED_CODES_READY' AS check_name, COUNT(*) AS ready_count, 7 AS expected_count
FROM transaction_codes
WHERE code IN ('PO','PR','SO','SD','SI','PI','JV') AND COALESCE(is_active,1) = 1;
