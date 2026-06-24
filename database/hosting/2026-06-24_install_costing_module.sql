-- ERP PENA - Costing module foundation
-- Cost Type, Item Cost, and calculation result tables.

USE `dberp_pena`;

CREATE TABLE IF NOT EXISTS costing_cost_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    type VARCHAR(10) NOT NULL,
    description VARCHAR(300) NULL,
    cost_group VARCHAR(10) NOT NULL DEFAULT 'Material',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_costing_cost_types_company_type (company_id, type),
    KEY idx_costing_cost_types_group (cost_group),
    KEY idx_costing_cost_types_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS costing_item_costs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    company_code VARCHAR(20) NULL,
    site_code VARCHAR(20) NULL,
    item_code VARCHAR(80) NOT NULL,
    item_name VARCHAR(255) NULL,
    department_code VARCHAR(30) NULL,
    warehouse_code VARCHAR(30) NULL,
    description VARCHAR(500) NULL,
    this_item_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
    bom_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
    work_center_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
    total_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    calculated_at DATETIME NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_costing_item_cost_scope (company_id, site_id, item_code, department_code, warehouse_code),
    KEY idx_costing_item_cost_item (item_code),
    KEY idx_costing_item_cost_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS costing_item_cost_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_cost_id BIGINT UNSIGNED NOT NULL,
    bom_no VARCHAR(80) NULL,
    child_item_code VARCHAR(80) NOT NULL,
    child_item_name VARCHAR(255) NULL,
    qty_batch DECIMAL(18,6) NULL,
    qty_used DECIMAL(18,6) NULL,
    uom_code VARCHAR(20) NULL,
    ratio_percent DECIMAL(18,6) NULL,
    factor DECIMAL(18,6) NULL,
    this_item_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
    bom_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
    work_center_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
    total_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_costing_item_cost_lines_header (item_cost_id),
    KEY idx_costing_item_cost_lines_child (child_item_code),
    CONSTRAINT fk_costing_item_cost_lines_header FOREIGN KEY (item_cost_id) REFERENCES costing_item_costs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'TK', 'Tenaga Kerja', 'Labor', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'TK');

INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'Listrik', 'Listrik', 'Overhead', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'Listrik');

INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'Pisau', 'Pisau mesin', 'Overhead', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'Pisau');

INSERT INTO costing_cost_types (company_id, type, description, cost_group, is_active, created_at, updated_at)
SELECT NULL, 'Bensin', 'Bensin', 'Overhead', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM costing_cost_types WHERE company_id IS NULL AND type = 'Bensin');

SELECT 'COSTING_COST_TYPES_TABLE' AS check_name, COUNT(*) AS ready FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'costing_cost_types';
SELECT 'COSTING_ITEM_COSTS_TABLE' AS check_name, COUNT(*) AS ready FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'costing_item_costs';
SELECT 'COSTING_ITEM_COST_LINES_TABLE' AS check_name, COUNT(*) AS ready FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'costing_item_cost_lines';
