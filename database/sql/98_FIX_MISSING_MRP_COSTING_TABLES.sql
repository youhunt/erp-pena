-- ERP PENA - QUICK FIX MISSING MRP + COSTING TABLES
-- Jalankan di phpMyAdmin hanya jika /system/core-health menampilkan FAIL:
-- CORE_TABLE_MRP_RUNS, CORE_TABLE_MRP_LINES, CORE_TABLE_MRP_PLANNED_ORDERS,
-- CORE_TABLE_COST_TYPES, CORE_TABLE_ITEM_COSTS, CORE_TABLE_ITEM_COST_LINES.
--
-- File ini juga sudah dimirror ke database/sql/00_RUN_THIS_ON_HOSTING.sql.

SET @db := DATABASE();
SELECT @db AS selected_database;

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

SELECT 'MRP_COSTING_TABLES_READY' AS check_name, COUNT(*) AS ready_count, 6 AS expected_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'production_mrp_runs','production_mrp_lines','production_mrp_planned_orders',
    'costing_cost_types','costing_item_costs','costing_item_cost_lines'
  );

SELECT 'COST_TYPES_SEEDED' AS check_name, COUNT(*) AS ready_count, 4 AS expected_count
FROM costing_cost_types
WHERE type IN ('TK','Listrik','Pisau','Bensin')
  AND COALESCE(is_active, 1) = 1;
