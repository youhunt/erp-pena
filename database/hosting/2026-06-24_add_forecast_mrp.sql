-- ERP PENA - Production Forecast + MRP foundation.
-- Run in phpMyAdmin after git pull.

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
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_mrp_lines_run (mrp_run_id),
    KEY idx_mrp_lines_component (company_id, site_id, component_item_code),
    CONSTRAINT fk_mrp_lines_run FOREIGN KEY (mrp_run_id) REFERENCES production_mrp_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add Production menu links if menu_items uses the current label/route structure.
SET @production_parent_id := (
    SELECT id
    FROM menu_items
    WHERE LOWER(COALESCE(label, '')) IN ('production', 'manufacturing')
       OR route IN ('production', '/production')
    ORDER BY id ASC
    LIMIT 1
);

INSERT INTO menu_items (parent_id, label, route, icon, sort_order, permission, is_active, created_at, updated_at, created_by, updated_by)
SELECT @production_parent_id, 'Forecast', 'production/forecasts', 'bx bx-line-chart', 5, 'production.planning.view', 1, NOW(), NOW(), NULL, NULL
WHERE @production_parent_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM menu_items WHERE route IN ('production/forecasts', '/production/forecasts') OR LOWER(COALESCE(label, '')) = 'forecast'
  );

INSERT INTO menu_items (parent_id, label, route, icon, sort_order, permission, is_active, created_at, updated_at, created_by, updated_by)
SELECT @production_parent_id, 'MRP', 'production/mrp', 'bx bx-sitemap', 6, 'production.planning.view', 1, NOW(), NOW(), NULL, NULL
WHERE @production_parent_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM menu_items WHERE route IN ('production/mrp', '/production/mrp') OR LOWER(COALESCE(label, '')) = 'mrp'
  );

SELECT 'PRODUCTION_FORECAST_TABLE' AS check_name, COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'production_forecasts';
SELECT 'PRODUCTION_MRP_RUNS_TABLE' AS check_name, COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'production_mrp_runs';
SELECT 'PRODUCTION_MRP_LINES_TABLE' AS check_name, COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'production_mrp_lines';
