-- ERP PENA - Setup VAT Master legacy fields
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.
-- Aman dijalankan berulang kali karena setiap field dicek dulu di information_schema.

SET @db := DATABASE();
SELECT @db AS selected_database;

CREATE TABLE IF NOT EXISTS vat_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    site_id INT NULL,
    company VARCHAR(12) NULL,
    site VARCHAR(12) NULL,
    vat VARCHAR(12) NULL,
    description VARCHAR(500) NULL,
    vatpctg DECIMAL(5,2) NOT NULL DEFAULT 0,
    scpctg DECIMAL(5,2) NOT NULL DEFAULT 0,
    otherpctg DECIMAL(5,2) NOT NULL DEFAULT 0,
    optionalpctg DECIMAL(5,2) NOT NULL DEFAULT 0,
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
    KEY idx_vat_rates_key (company_id, site_id, vat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='company_id')=0,'ALTER TABLE vat_rates ADD COLUMN company_id INT NULL','SELECT ''vat_rates.company_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='site_id')=0,'ALTER TABLE vat_rates ADD COLUMN site_id INT NULL','SELECT ''vat_rates.site_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='company')=0,'ALTER TABLE vat_rates ADD COLUMN company VARCHAR(12) NULL','SELECT ''vat_rates.company exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='site')=0,'ALTER TABLE vat_rates ADD COLUMN site VARCHAR(12) NULL','SELECT ''vat_rates.site exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='vat')=0,'ALTER TABLE vat_rates ADD COLUMN vat VARCHAR(12) NULL','SELECT ''vat_rates.vat exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='description')=0,'ALTER TABLE vat_rates ADD COLUMN description VARCHAR(500) NULL','SELECT ''vat_rates.description exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='vatpctg')=0,'ALTER TABLE vat_rates ADD COLUMN vatpctg DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''vat_rates.vatpctg exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='scpctg')=0,'ALTER TABLE vat_rates ADD COLUMN scpctg DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''vat_rates.scpctg exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='otherpctg')=0,'ALTER TABLE vat_rates ADD COLUMN otherpctg DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''vat_rates.otherpctg exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='optionalpctg')=0,'ALTER TABLE vat_rates ADD COLUMN optionalpctg DECIMAL(5,2) NOT NULL DEFAULT 0','SELECT ''vat_rates.optionalpctg exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='gl')=0,'ALTER TABLE vat_rates ADD COLUMN gl VARCHAR(30) NULL','SELECT ''vat_rates.gl exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='code')=0,'ALTER TABLE vat_rates ADD COLUMN code VARCHAR(12) NULL','SELECT ''vat_rates.code exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='name')=0,'ALTER TABLE vat_rates ADD COLUMN name VARCHAR(500) NULL','SELECT ''vat_rates.name exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='rate')=0,'ALTER TABLE vat_rates ADD COLUMN rate DECIMAL(10,4) NOT NULL DEFAULT 0','SELECT ''vat_rates.rate exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='is_active')=0,'ALTER TABLE vat_rates ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1','SELECT ''vat_rates.is_active exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='created_by')=0,'ALTER TABLE vat_rates ADD COLUMN created_by INT NULL','SELECT ''vat_rates.created_by exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='updated_by')=0,'ALTER TABLE vat_rates ADD COLUMN updated_by INT NULL','SELECT ''vat_rates.updated_by exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='created_at')=0,'ALTER TABLE vat_rates ADD COLUMN created_at DATETIME NULL','SELECT ''vat_rates.created_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='updated_at')=0,'ALTER TABLE vat_rates ADD COLUMN updated_at DATETIME NULL','SELECT ''vat_rates.updated_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vat_rates' AND COLUMN_NAME='deleted_at')=0,'ALTER TABLE vat_rates ADD COLUMN deleted_at DATETIME NULL','SELECT ''vat_rates.deleted_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE vat_rates
SET vat = COALESCE(NULLIF(vat, ''), code),
    description = COALESCE(NULLIF(description, ''), name),
    vatpctg = CASE WHEN vatpctg IS NULL OR vatpctg = 0 THEN COALESCE(rate, 0) ELSE vatpctg END,
    code = COALESCE(NULLIF(code, ''), vat),
    name = COALESCE(NULLIF(name, ''), description),
    rate = CASE WHEN rate IS NULL OR rate = 0 THEN COALESCE(vatpctg, 0) ELSE rate END;

SET @setup_id := (SELECT id FROM menu_items WHERE label = 'Setup' AND (parent_id IS NULL OR parent_id = 0) ORDER BY id LIMIT 1);
INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT 0, 'Setup', '#', 'bx-slider-alt', 'setup.master.view', 900, 1, NOW(), NOW()
WHERE @setup_id IS NULL;
SET @setup_id := COALESCE(@setup_id, LAST_INSERT_ID());

INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT @setup_id, 'VAT Master', 'setup/vat', 'bx-receipt', 'setup.master.view', 236, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE route = 'setup/vat');

UPDATE menu_items SET parent_id = @setup_id, label = 'VAT Master', icon = 'bx-receipt', permission = 'setup.master.view', sort_order = 236, is_active = 1, updated_at = NOW() WHERE route = 'setup/vat';
