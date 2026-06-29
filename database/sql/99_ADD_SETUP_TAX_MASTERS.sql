-- ERP PENA - Setup Tax Masters
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.

SET @db := DATABASE();
SELECT @db AS selected_database;

CREATE TABLE IF NOT EXISTS item_vat_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    company VARCHAR(12) NULL,
    site VARCHAR(12) NULL,
    vat VARCHAR(12) NOT NULL,
    description VARCHAR(500) NULL,
    vatpctg DECIMAL(5,2) NOT NULL DEFAULT 0,
    scpctg DECIMAL(5,2) NOT NULL DEFAULT 0,
    whtpctg DECIMAL(5,2) NOT NULL DEFAULT 0,
    otherpctg DECIMAL(5,2) NOT NULL DEFAULT 0,
    optionalpctg DECIMAL(5,2) NOT NULL DEFAULT 0,
    gl VARCHAR(30) NULL,
    item_id BIGINT UNSIGNED NULL,
    vat_rate_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_item_vat_rates_key (company_id, site_id, vat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS charge_vat_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    company VARCHAR(12) NULL,
    site VARCHAR(12) NULL,
    vat VARCHAR(12) NOT NULL,
    description VARCHAR(500) NULL,
    vatpctg1 DECIMAL(5,2) NOT NULL DEFAULT 0,
    vatpctg2 DECIMAL(5,2) NOT NULL DEFAULT 0,
    vatpctg3 DECIMAL(5,2) NOT NULL DEFAULT 0,
    vatpctg4 DECIMAL(5,2) NOT NULL DEFAULT 0,
    vatpctg5 DECIMAL(5,2) NOT NULL DEFAULT 0,
    gl VARCHAR(30) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_charge_vat_rates_key (company_id, site_id, vat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wht_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    company VARCHAR(12) NULL,
    site VARCHAR(12) NULL,
    vat VARCHAR(12) NOT NULL,
    description VARCHAR(500) NULL,
    vatpctg1 DECIMAL(5,2) NOT NULL DEFAULT 0,
    vatpctg2 DECIMAL(5,2) NOT NULL DEFAULT 0,
    vatpctg3 DECIMAL(5,2) NOT NULL DEFAULT 0,
    vatpctg4 DECIMAL(5,2) NOT NULL DEFAULT 0,
    vatpctg5 DECIMAL(5,2) NOT NULL DEFAULT 0,
    gl VARCHAR(30) NULL,
    code VARCHAR(12) NULL,
    name VARCHAR(500) NULL,
    rate DECIMAL(10,4) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_wht_rates_key (company_id, site_id, vat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Common column safety for existing installations
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='company_id')=0,'ALTER TABLE item_vat_rates ADD COLUMN company_id INT NULL','SELECT ''item_vat_rates.company_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='site_id')=0,'ALTER TABLE item_vat_rates ADD COLUMN site_id INT NULL','SELECT ''item_vat_rates.site_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='company')=0,'ALTER TABLE item_vat_rates ADD COLUMN company VARCHAR(12) NULL','SELECT ''item_vat_rates.company exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='site')=0,'ALTER TABLE item_vat_rates ADD COLUMN site VARCHAR(12) NULL','SELECT ''item_vat_rates.site exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='vat')=0,'ALTER TABLE item_vat_rates ADD COLUMN vat VARCHAR(12) NULL','SELECT ''item_vat_rates.vat exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='description')=0,'ALTER TABLE item_vat_rates ADD COLUMN description VARCHAR(500) NULL','SELECT ''item_vat_rates.description exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='vatpctg')=0,'ALTER TABLE item_vat_rates ADD COLUMN vatpctg DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''item_vat_rates.vatpctg exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='scpctg')=0,'ALTER TABLE item_vat_rates ADD COLUMN scpctg DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''item_vat_rates.scpctg exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='whtpctg')=0,'ALTER TABLE item_vat_rates ADD COLUMN whtpctg DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''item_vat_rates.whtpctg exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='otherpctg')=0,'ALTER TABLE item_vat_rates ADD COLUMN otherpctg DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''item_vat_rates.otherpctg exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='optionalpctg')=0,'ALTER TABLE item_vat_rates ADD COLUMN optionalpctg DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''item_vat_rates.optionalpctg exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='item_vat_rates' AND COLUMN_NAME='gl')=0,'ALTER TABLE item_vat_rates ADD COLUMN gl VARCHAR(30) NULL','SELECT ''item_vat_rates.gl exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='company_id')=0,'ALTER TABLE charge_vat_rates ADD COLUMN company_id INT NULL','SELECT ''charge_vat_rates.company_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='site_id')=0,'ALTER TABLE charge_vat_rates ADD COLUMN site_id INT NULL','SELECT ''charge_vat_rates.site_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='company')=0,'ALTER TABLE charge_vat_rates ADD COLUMN company VARCHAR(12) NULL','SELECT ''charge_vat_rates.company exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='site')=0,'ALTER TABLE charge_vat_rates ADD COLUMN site VARCHAR(12) NULL','SELECT ''charge_vat_rates.site exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='vat')=0,'ALTER TABLE charge_vat_rates ADD COLUMN vat VARCHAR(12) NULL','SELECT ''charge_vat_rates.vat exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='description')=0,'ALTER TABLE charge_vat_rates ADD COLUMN description VARCHAR(500) NULL','SELECT ''charge_vat_rates.description exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='gl')=0,'ALTER TABLE charge_vat_rates ADD COLUMN gl VARCHAR(30) NULL','SELECT ''charge_vat_rates.gl exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='vatpctg1')=0,'ALTER TABLE charge_vat_rates ADD COLUMN vatpctg1 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''charge_vat_rates.vatpctg1 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='vatpctg2')=0,'ALTER TABLE charge_vat_rates ADD COLUMN vatpctg2 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''charge_vat_rates.vatpctg2 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='vatpctg3')=0,'ALTER TABLE charge_vat_rates ADD COLUMN vatpctg3 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''charge_vat_rates.vatpctg3 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='vatpctg4')=0,'ALTER TABLE charge_vat_rates ADD COLUMN vatpctg4 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''charge_vat_rates.vatpctg4 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='charge_vat_rates' AND COLUMN_NAME='vatpctg5')=0,'ALTER TABLE charge_vat_rates ADD COLUMN vatpctg5 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''charge_vat_rates.vatpctg5 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='company_id')=0,'ALTER TABLE wht_rates ADD COLUMN company_id INT NULL','SELECT ''wht_rates.company_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='site_id')=0,'ALTER TABLE wht_rates ADD COLUMN site_id INT NULL','SELECT ''wht_rates.site_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='company')=0,'ALTER TABLE wht_rates ADD COLUMN company VARCHAR(12) NULL','SELECT ''wht_rates.company exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='site')=0,'ALTER TABLE wht_rates ADD COLUMN site VARCHAR(12) NULL','SELECT ''wht_rates.site exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='vat')=0,'ALTER TABLE wht_rates ADD COLUMN vat VARCHAR(12) NULL','SELECT ''wht_rates.vat exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='description')=0,'ALTER TABLE wht_rates ADD COLUMN description VARCHAR(500) NULL','SELECT ''wht_rates.description exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='gl')=0,'ALTER TABLE wht_rates ADD COLUMN gl VARCHAR(30) NULL','SELECT ''wht_rates.gl exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='vatpctg1')=0,'ALTER TABLE wht_rates ADD COLUMN vatpctg1 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''wht_rates.vatpctg1 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='vatpctg2')=0,'ALTER TABLE wht_rates ADD COLUMN vatpctg2 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''wht_rates.vatpctg2 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='vatpctg3')=0,'ALTER TABLE wht_rates ADD COLUMN vatpctg3 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''wht_rates.vatpctg3 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='vatpctg4')=0,'ALTER TABLE wht_rates ADD COLUMN vatpctg4 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''wht_rates.vatpctg4 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='vatpctg5')=0,'ALTER TABLE wht_rates ADD COLUMN vatpctg5 DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''wht_rates.vatpctg5 exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='code')=0,'ALTER TABLE wht_rates ADD COLUMN code VARCHAR(12) NULL','SELECT ''wht_rates.code exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='name')=0,'ALTER TABLE wht_rates ADD COLUMN name VARCHAR(500) NULL','SELECT ''wht_rates.name exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='wht_rates' AND COLUMN_NAME='rate')=0,'ALTER TABLE wht_rates ADD COLUMN rate DECIMAL(10,4) NOT NULL DEFAULT 0','SELECT ''wht_rates.rate exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Sync old WHT fields into new master fields if old data exists
UPDATE wht_rates
SET vat = COALESCE(NULLIF(vat, ''), code),
    description = COALESCE(NULLIF(description, ''), name),
    vatpctg1 = CASE WHEN vatpctg1 IS NULL OR vatpctg1 = 0 THEN COALESCE(rate, 0) ELSE vatpctg1 END,
    code = COALESCE(NULLIF(code, ''), vat),
    name = COALESCE(NULLIF(name, ''), description),
    rate = CASE WHEN rate IS NULL OR rate = 0 THEN COALESCE(vatpctg1, 0) ELSE rate END;

-- Setup menu entries
SET @setup_id := (SELECT id FROM menu_items WHERE label = 'Setup' AND (parent_id IS NULL OR parent_id = 0) ORDER BY id LIMIT 1);
INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT 0, 'Setup', '#', 'bx-slider-alt', 'setup.master.view', 900, 1, NOW(), NOW()
WHERE @setup_id IS NULL;
SET @setup_id := COALESCE(@setup_id, LAST_INSERT_ID());

INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT @setup_id, 'Item VAT Master', 'setup/item-vat', 'bx-receipt', 'setup.master.view', 237, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE route = 'setup/item-vat');

INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT @setup_id, 'Other Charge VAT Master', 'setup/other-charge-vat', 'bx-plus-medical', 'setup.master.view', 238, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE route = 'setup/other-charge-vat');

INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT @setup_id, 'WHT Master', 'setup/wht', 'bx-cut', 'setup.master.view', 239, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE route = 'setup/wht');

UPDATE menu_items SET label = 'Item VAT Master', is_active = 1, updated_at = NOW() WHERE route = 'setup/item-vat';
UPDATE menu_items SET label = 'Other Charge VAT Master', is_active = 1, updated_at = NOW() WHERE route = 'setup/other-charge-vat';
UPDATE menu_items SET label = 'WHT Master', is_active = 1, updated_at = NOW() WHERE route = 'setup/wht';
