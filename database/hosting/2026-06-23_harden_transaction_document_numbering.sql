-- ERP PENA - harden Document Numbering to use Transaction Codes as the source of truth.
-- Safe hosting/phpMyAdmin version for current menu_items structure.
-- menu_items columns used: label, route, icon, parent_id, permission, sort_order, is_active.

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

SET @schema_name := DATABASE();

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN company_id INT NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'company_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN prefix VARCHAR(50) NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'prefix');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN format VARCHAR(150) NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'format');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN reset_period VARCHAR(20) NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'reset_period');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN padding INT NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'padding');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN rate DECIMAL(18,6) NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'rate');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN description TEXT NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'description');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'is_active');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN created_by VARCHAR(50) NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'created_by');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN updated_by VARCHAR(50) NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'updated_by');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN created_at DATETIME NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'created_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN updated_at DATETIME NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'updated_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE transaction_codes ADD COLUMN deleted_at DATETIME NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'transaction_codes' AND COLUMN_NAME = 'deleted_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
SELECT c.id, x.code, x.name, x.prefix, x.format, x.reset_period, x.padding, x.description, 1, 'system', 'system', NOW(), NOW()
FROM companies c
JOIN (
    SELECT 'PO' code, 'Purchase Order' name, 'PO' prefix, '{PREFIX}{SEQ}' format, 'never' reset_period, 3 padding, 'Purchase order number' description
    UNION ALL SELECT 'PR', 'Purchase Receipt', 'PR', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Purchase receipt number'
    UNION ALL SELECT 'SO', 'Sales Order', 'SO', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Sales order number'
    UNION ALL SELECT 'SD', 'Sales Delivery', 'SD', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Sales delivery number'
    UNION ALL SELECT 'SI', 'Sales Invoice', 'SI', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Sales invoice number'
    UNION ALL SELECT 'PI', 'Purchase Invoice', 'PI', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Purchase invoice number'
    UNION ALL SELECT 'JV', 'Journal Voucher', 'JV', '{PREFIX}/{YYYY}{MM}/{SEQ}', 'monthly', 4, 'Manual journal number'
) x
WHERE COALESCE(c.is_active, 1) = 1
  AND NOT EXISTS (
      SELECT 1
      FROM transaction_codes tc
      WHERE tc.company_id = c.id
        AND tc.code = x.code
        AND tc.deleted_at IS NULL
  );

UPDATE transaction_codes
SET
    prefix = IF(COALESCE(prefix, '') = '', code, prefix),
    reset_period = IF(COALESCE(reset_period, '') NOT IN ('daily', 'monthly', 'yearly', 'never'), 'monthly', reset_period),
    padding = IF(COALESCE(padding, 0) < 1 OR COALESCE(padding, 0) > 12, 4, padding),
    is_active = 1,
    updated_at = NOW()
WHERE code IN ('PO', 'PR', 'SO', 'SD', 'SI', 'PI', 'JV')
  AND deleted_at IS NULL;

UPDATE transaction_codes
SET format = IF(COALESCE(format, '') = '', '{PREFIX}/{YYYY}{MM}/{SEQ}', format), updated_at = NOW()
WHERE code IN ('PR', 'SO', 'SD', 'SI', 'PI', 'JV')
  AND deleted_at IS NULL;

UPDATE transaction_codes
SET prefix = IF(COALESCE(prefix, '') = '', 'PO', prefix),
    format = IF(COALESCE(format, '') = '', '{PREFIX}{SEQ}', format),
    reset_period = IF(COALESCE(reset_period, '') NOT IN ('daily', 'monthly', 'yearly', 'never'), 'never', reset_period),
    padding = IF(COALESCE(padding, 0) < 1 OR COALESCE(padding, 0) > 12, 3, padding),
    is_active = 1,
    updated_at = NOW()
WHERE code = 'PO'
  AND deleted_at IS NULL;

-- Add sidebar/menu link for Setup > Document Numbering using current menu_items structure.
SET @setup_parent_id := (
    SELECT id
    FROM menu_items
    WHERE LOWER(COALESCE(label, '')) IN ('setup', 'master setup', 'master data', 'setup master')
       OR route IN ('setup', '/setup')
    ORDER BY id ASC
    LIMIT 1
);

INSERT INTO menu_items (
    parent_id,
    label,
    route,
    icon,
    sort_order,
    permission,
    is_active,
    created_at,
    updated_at,
    created_by,
    updated_by
)
SELECT
    @setup_parent_id,
    'Document Numbering',
    'setup/document-numbering',
    'bx bx-hash',
    9,
    'setup.master.view',
    1,
    NOW(),
    NOW(),
    NULL,
    NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM menu_items
    WHERE route IN ('setup/document-numbering', '/setup/document-numbering')
       OR LOWER(COALESCE(label, '')) = 'document numbering'
);

-- Validation output: all totals should be 0.
SELECT 'DOCUMENT_NUMBERING_REQUIRED_CODES_MISSING_OR_INACTIVE' AS check_name, COUNT(*) AS total
FROM companies c
JOIN (
    SELECT 'PO' code UNION ALL SELECT 'PR' UNION ALL SELECT 'SO'
    UNION ALL SELECT 'SD' UNION ALL SELECT 'SI' UNION ALL SELECT 'PI'
    UNION ALL SELECT 'JV'
) req
LEFT JOIN transaction_codes tc
    ON tc.company_id = c.id
   AND tc.code = req.code
   AND COALESCE(tc.is_active, 1) = 1
   AND tc.deleted_at IS NULL
WHERE COALESCE(c.is_active, 1) = 1
  AND tc.id IS NULL;

SELECT 'DOCUMENT_NUMBERING_PO_CONFIG_INCOMPLETE' AS check_name, COUNT(*) AS total
FROM companies c
LEFT JOIN transaction_codes tc
    ON tc.company_id = c.id
   AND tc.code = 'PO'
   AND COALESCE(tc.is_active, 1) = 1
   AND tc.deleted_at IS NULL
WHERE COALESCE(c.is_active, 1) = 1
  AND (
      tc.id IS NULL
      OR COALESCE(tc.prefix, '') = ''
      OR COALESCE(tc.format, '') = ''
      OR COALESCE(tc.reset_period, '') NOT IN ('daily', 'monthly', 'yearly', 'never')
      OR COALESCE(tc.padding, 0) < 1
      OR COALESCE(tc.padding, 0) > 12
  );

SELECT 'DOCUMENT_NUMBERING_SEQUENCE_DUPLICATE' AS check_name, COUNT(*) AS total
FROM (
    SELECT company_id, site_id, transaction_code, prefix, period_key, COUNT(*) AS duplicate_count
    FROM document_number_sequences
    GROUP BY company_id, site_id, transaction_code, prefix, period_key
    HAVING COUNT(*) > 1
) x;
