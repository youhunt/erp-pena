-- ERP PENA - Work Order document numbering
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.

SET @db := DATABASE();
SELECT @db AS selected_database;

CREATE TABLE IF NOT EXISTS transaction_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    prefix VARCHAR(50) NULL,
    format VARCHAR(150) NULL,
    reset_period VARCHAR(20) NULL,
    padding INT NULL,
    rate DECIMAL(18,6) NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(50) NULL,
    updated_by VARCHAR(50) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_transaction_codes_company_code (company_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='company_id')=0,'ALTER TABLE transaction_codes ADD COLUMN company_id INT NULL','SELECT ''transaction_codes.company_id exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='prefix')=0,'ALTER TABLE transaction_codes ADD COLUMN prefix VARCHAR(50) NULL','SELECT ''transaction_codes.prefix exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='format')=0,'ALTER TABLE transaction_codes ADD COLUMN format VARCHAR(150) NULL','SELECT ''transaction_codes.format exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='reset_period')=0,'ALTER TABLE transaction_codes ADD COLUMN reset_period VARCHAR(20) NULL','SELECT ''transaction_codes.reset_period exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='padding')=0,'ALTER TABLE transaction_codes ADD COLUMN padding INT NULL','SELECT ''transaction_codes.padding exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='description')=0,'ALTER TABLE transaction_codes ADD COLUMN description TEXT NULL','SELECT ''transaction_codes.description exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='is_active')=0,'ALTER TABLE transaction_codes ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1','SELECT ''transaction_codes.is_active exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='created_by')=0,'ALTER TABLE transaction_codes ADD COLUMN created_by VARCHAR(50) NULL','SELECT ''transaction_codes.created_by exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='updated_by')=0,'ALTER TABLE transaction_codes ADD COLUMN updated_by VARCHAR(50) NULL','SELECT ''transaction_codes.updated_by exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='created_at')=0,'ALTER TABLE transaction_codes ADD COLUMN created_at DATETIME NULL','SELECT ''transaction_codes.created_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='updated_at')=0,'ALTER TABLE transaction_codes ADD COLUMN updated_at DATETIME NULL','SELECT ''transaction_codes.updated_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='transaction_codes' AND COLUMN_NAME='deleted_at')=0,'ALTER TABLE transaction_codes ADD COLUMN deleted_at DATETIME NULL','SELECT ''transaction_codes.deleted_at exists'' AS info'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS document_number_sequences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    site_id INT NOT NULL DEFAULT 0,
    transaction_code VARCHAR(50) NOT NULL,
    prefix VARCHAR(50) NOT NULL,
    period_key VARCHAR(30) NOT NULL,
    last_number INT NOT NULL DEFAULT 0,
    padding INT NOT NULL DEFAULT 5,
    reset_period VARCHAR(20) NOT NULL DEFAULT 'monthly',
    last_document_no VARCHAR(150) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_number_sequences (company_id, site_id, transaction_code, prefix, period_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO transaction_codes (company_id, code, name, prefix, format, reset_period, padding, description, is_active, created_by, updated_by, created_at, updated_at)
SELECT NULL, 'WO', 'Work Order', 'WO', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Production work order number', 1, 'system', 'system', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM transaction_codes WHERE code = 'WO' AND (company_id IS NULL OR company_id = 0));

UPDATE transaction_codes
SET name = 'Work Order', prefix = 'WO', format = '{PREFIX}/{YYYY}{MM}/{SEQ}', reset_period = 'monthly', padding = 4, description = 'Production work order number', is_active = 1, updated_at = NOW()
WHERE code = 'WO' AND (company_id IS NULL OR company_id = 0);
