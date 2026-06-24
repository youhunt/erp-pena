-- ERP PENA - MRP Planned Orders
-- Purpose: create draft planning documents from MRP action lines before converting to real PR/WO.
-- Run after production_mrp_runs and production_mrp_lines exist.

CREATE TABLE IF NOT EXISTS production_mrp_planned_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    site_id INT NULL,
    mrp_run_id BIGINT UNSIGNED NOT NULL,
    mrp_line_id BIGINT UNSIGNED NOT NULL,
    plan_no VARCHAR(80) NOT NULL,
    plan_type VARCHAR(40) NOT NULL,
    suggested_action VARCHAR(60) NULL,
    item_code VARCHAR(80) NOT NULL,
    item_name VARCHAR(255) NULL,
    uom_code VARCHAR(20) NULL,
    qty DECIMAL(18,6) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'planned',
    source_parent_item_code TEXT NULL,
    target_doc_type VARCHAR(40) NULL,
    target_doc_no VARCHAR(80) NULL,
    notes VARCHAR(500) NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mrp_planned_order_line (mrp_line_id),
    UNIQUE KEY uq_mrp_planned_order_no (plan_no),
    KEY idx_mrp_planned_orders_run (mrp_run_id),
    KEY idx_mrp_planned_orders_status (company_id, site_id, status, plan_type),
    CONSTRAINT fk_mrp_planned_orders_run FOREIGN KEY (mrp_run_id) REFERENCES production_mrp_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_mrp_planned_orders_line FOREIGN KEY (mrp_line_id) REFERENCES production_mrp_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'MRP_PLANNED_ORDERS_TABLE' AS check_name, COUNT(*) AS total
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'production_mrp_planned_orders';
