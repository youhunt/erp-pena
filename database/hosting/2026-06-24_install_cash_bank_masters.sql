-- ERP PENA - Cash Bank master module foundation
-- Cash Bank ID, Currency, Employee Master, Rate Master

USE `dberp_pena`;

-- Extend existing cash_bank_accounts table for Bank master fields.
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

SELECT 'CURRENCIES_TABLE' AS check_name, COUNT(*) AS ready FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'currencies';
SELECT 'EMPLOYEES_TABLE' AS check_name, COUNT(*) AS ready FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees';
SELECT 'CURRENCY_RATES_TABLE' AS check_name, COUNT(*) AS ready FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'currency_rates';
