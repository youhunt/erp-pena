-- ERP PENA - Costing BOM Cost Roll-up
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.

SET @db := DATABASE();
SELECT @db AS selected_database;

CREATE TABLE IF NOT EXISTS costing_item_costs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    site_code VARCHAR(30) NULL,
    item_code VARCHAR(80) NOT NULL,
    item_name VARCHAR(255) NULL,
    department_code VARCHAR(50) NULL,
    warehouse_code VARCHAR(50) NULL,
    description VARCHAR(500) NULL,
    this_item_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
    bom_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
    work_center_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
    total_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    created_by VARCHAR(50) NULL,
    updated_by VARCHAR(50) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_costing_item_costs_item (company_id, site_id, item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='company_id')=0,'ALTER TABLE costing_item_costs ADD COLUMN company_id INT NULL','SELECT ''costing_item_costs.company_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='site_id')=0,'ALTER TABLE costing_item_costs ADD COLUMN site_id INT NULL','SELECT ''costing_item_costs.site_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='site_code')=0,'ALTER TABLE costing_item_costs ADD COLUMN site_code VARCHAR(30) NULL','SELECT ''costing_item_costs.site_code exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='item_name')=0,'ALTER TABLE costing_item_costs ADD COLUMN item_name VARCHAR(255) NULL','SELECT ''costing_item_costs.item_name exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='department_code')=0,'ALTER TABLE costing_item_costs ADD COLUMN department_code VARCHAR(50) NULL','SELECT ''costing_item_costs.department_code exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='warehouse_code')=0,'ALTER TABLE costing_item_costs ADD COLUMN warehouse_code VARCHAR(50) NULL','SELECT ''costing_item_costs.warehouse_code exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='description')=0,'ALTER TABLE costing_item_costs ADD COLUMN description VARCHAR(500) NULL','SELECT ''costing_item_costs.description exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='this_item_cost')=0,'ALTER TABLE costing_item_costs ADD COLUMN this_item_cost DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''costing_item_costs.this_item_cost exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='bom_cost')=0,'ALTER TABLE costing_item_costs ADD COLUMN bom_cost DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''costing_item_costs.bom_cost exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='work_center_cost')=0,'ALTER TABLE costing_item_costs ADD COLUMN work_center_cost DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''costing_item_costs.work_center_cost exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='wc_cost')=0,'ALTER TABLE costing_item_costs ADD COLUMN wc_cost DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''costing_item_costs.wc_cost exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='total_cost')=0,'ALTER TABLE costing_item_costs ADD COLUMN total_cost DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''costing_item_costs.total_cost exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='status')=0,'ALTER TABLE costing_item_costs ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT ''draft''','SELECT ''costing_item_costs.status exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='created_by')=0,'ALTER TABLE costing_item_costs ADD COLUMN created_by VARCHAR(50) NULL','SELECT ''costing_item_costs.created_by exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='updated_by')=0,'ALTER TABLE costing_item_costs ADD COLUMN updated_by VARCHAR(50) NULL','SELECT ''costing_item_costs.updated_by exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='created_at')=0,'ALTER TABLE costing_item_costs ADD COLUMN created_at DATETIME NULL','SELECT ''costing_item_costs.created_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='updated_at')=0,'ALTER TABLE costing_item_costs ADD COLUMN updated_at DATETIME NULL','SELECT ''costing_item_costs.updated_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='costing_item_costs' AND COLUMN_NAME='deleted_at')=0,'ALTER TABLE costing_item_costs ADD COLUMN deleted_at DATETIME NULL','SELECT ''costing_item_costs.deleted_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optional legacy table compatibility: Item_cost.BomCost / BOMCost.
SET @has_item_cost := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_cost');
SET @sql := IF(@has_item_cost=1 AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_cost' AND COLUMN_NAME='BomCost')=0,'ALTER TABLE item_cost ADD COLUMN BomCost DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''item_cost.BomCost skipped/existing'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_item_cost=1 AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_cost' AND COLUMN_NAME='BOMCost')=0,'ALTER TABLE item_cost ADD COLUMN BOMCost DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''item_cost.BOMCost skipped/existing'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@has_item_cost=1 AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_cost' AND COLUMN_NAME='TotalCost')=0,'ALTER TABLE item_cost ADD COLUMN TotalCost DECIMAL(20,6) NOT NULL DEFAULT 0','SELECT ''item_cost.TotalCost skipped/existing'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
