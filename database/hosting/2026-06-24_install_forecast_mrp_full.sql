-- ERP PENA - FULL Forecast + MRP installer.
-- Use this when production_mrp_lines does not exist yet.
-- Run after selecting the ERP database in phpMyAdmin.

SELECT DATABASE() AS selected_database;

CREATE TABLE IF NOT EXISTS production_forecasts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    site_id INT NULL,
    site_code VARCHAR(20) NULL,
    forecast_no VARCHAR(60) NOT NULL,
    forecast_date DATE NOT NULL,
    item_code VARCHAR(80) NOT NULL,
    item_name VARCHAR(255) NULL,
    uom_code VARCHAR(20) NULL,
    qty DECIMAL(18,6) NOT NULL DEFAULT 0,
    source_type VARCHAR(30) NOT NULL DEFAULT 'manual',
    status VARCHAR(30) NOT NULL DEFAULT 'confirmed',
    notes VARCHAR(500) NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_forecast_scope_date_item (company_id, site_id, forecast_date, item_code),
    KEY idx_forecast_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_mrp_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    site_id INT NULL,
    run_no VARCHAR(60) NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    item_code_filter VARCHAR(80) NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'forecast',
    status VARCHAR(30) NOT NULL DEFAULT 'generated',
    demand_count INT NOT NULL DEFAULT 0,
    line_count INT NOT NULL DEFAULT 0,
    gross_qty DECIMAL(18,6) NOT NULL DEFAULT 0,
    net_qty DECIMAL(18,6) NOT NULL DEFAULT 0,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mrp_run_no (run_no),
    KEY idx_mrp_runs_scope (company_id, site_id, from_date, to_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_mrp_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mrp_run_id BIGINT UNSIGNED NOT NULL,
    company_id INT NOT NULL,
    site_id INT NULL,
    line_no INT NOT NULL DEFAULT 0,
    line_type VARCHAR(30) NOT NULL DEFAULT 'material',
    parent_item_code TEXT NULL,
    component_item_code VARCHAR(80) NOT NULL,
    component_item_name VARCHAR(255) NULL,
    uom_code VARCHAR(20) NULL,
    gross_requirement DECIMAL(18,6) NOT NULL DEFAULT 0,
    stock_available DECIMAL(18,6) NOT NULL DEFAULT 0,
    net_requirement DECIMAL(18,6) NOT NULL DEFAULT 0,
    suggested_action VARCHAR(60) NULL,
    action_status VARCHAR(30) NOT NULL DEFAULT 'open',
    action_owner VARCHAR(100) NULL,
    action_target_date DATE NULL,
    action_notes VARCHAR(500) NULL,
    planned_doc_type VARCHAR(30) NULL,
    planned_doc_no VARCHAR(80) NULL,
    action_updated_by INT NULL,
    action_updated_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_mrp_lines_run (mrp_run_id),
    KEY idx_mrp_lines_component (company_id, site_id, component_item_code),
    KEY idx_mrp_lines_action_status (company_id, site_id, action_status, suggested_action),
    CONSTRAINT fk_mrp_lines_run FOREIGN KEY (mrp_run_id) REFERENCES production_mrp_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing action columns when production_mrp_lines already existed before this full installer.
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines' AND column_name = 'action_status'),
    'SELECT 1',
    'ALTER TABLE production_mrp_lines ADD COLUMN action_status VARCHAR(30) NOT NULL DEFAULT ''open'' AFTER suggested_action'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines' AND column_name = 'action_owner'),
    'SELECT 1',
    'ALTER TABLE production_mrp_lines ADD COLUMN action_owner VARCHAR(100) NULL AFTER action_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines' AND column_name = 'action_target_date'),
    'SELECT 1',
    'ALTER TABLE production_mrp_lines ADD COLUMN action_target_date DATE NULL AFTER action_owner'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines' AND column_name = 'action_notes'),
    'SELECT 1',
    'ALTER TABLE production_mrp_lines ADD COLUMN action_notes VARCHAR(500) NULL AFTER action_target_date'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines' AND column_name = 'planned_doc_type'),
    'SELECT 1',
    'ALTER TABLE production_mrp_lines ADD COLUMN planned_doc_type VARCHAR(30) NULL AFTER action_notes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines' AND column_name = 'planned_doc_no'),
    'SELECT 1',
    'ALTER TABLE production_mrp_lines ADD COLUMN planned_doc_no VARCHAR(80) NULL AFTER planned_doc_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines' AND column_name = 'action_updated_by'),
    'SELECT 1',
    'ALTER TABLE production_mrp_lines ADD COLUMN action_updated_by INT NULL AFTER planned_doc_no'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines' AND column_name = 'action_updated_at'),
    'SELECT 1',
    'ALTER TABLE production_mrp_lines ADD COLUMN action_updated_at DATETIME NULL AFTER action_updated_by'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE production_mrp_lines
SET action_status = 'open'
WHERE action_status IS NULL OR TRIM(action_status) = '';

-- Add index only if missing.
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'production_mrp_lines'
      AND index_name = 'idx_mrp_lines_action_status'
);
SET @sql := IF(@idx_exists > 0, 'SELECT 1', 'CREATE INDEX idx_mrp_lines_action_status ON production_mrp_lines (company_id, site_id, action_status, suggested_action)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'PRODUCTION_FORECAST_TABLE' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'production_forecasts';

SELECT 'PRODUCTION_MRP_RUNS_TABLE' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'production_mrp_runs';

SELECT 'PRODUCTION_MRP_LINES_TABLE' AS check_name, COUNT(*) AS total
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines';

SELECT 'MRP_ACTION_PLAN_COLUMNS_READY' AS check_name, COUNT(*) AS total
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'production_mrp_lines'
  AND column_name IN ('action_status','action_owner','action_target_date','action_notes','planned_doc_type','planned_doc_no','action_updated_by','action_updated_at');
