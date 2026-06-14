-- Manual MySQL migration for Batch Master
-- Project: PENA ERP
-- Use this file when hosting/cPanel cannot run: php spark migrate --all

CREATE TABLE IF NOT EXISTS `batch_masters` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `site_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `item_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `item_code` VARCHAR(80) NOT NULL,
  `batch_no` VARCHAR(80) NOT NULL,
  `batch_name` VARCHAR(255) NULL DEFAULT NULL,
  `production_date` DATE NULL DEFAULT NULL,
  `expiry_date` DATE NULL DEFAULT NULL,
  `supplier_lot_no` VARCHAR(80) NULL DEFAULT NULL,
  `manufacturer_lot_no` VARCHAR(80) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch_master_scope_item_batch` (`company_id`, `site_id`, `item_code`, `batch_no`),
  KEY `idx_batch_master_company_site` (`company_id`, `site_id`),
  KEY `idx_batch_master_item` (`company_id`, `item_code`),
  KEY `idx_batch_master_expiry` (`expiry_date`),
  KEY `idx_batch_master_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional verification:
-- SHOW TABLES LIKE 'batch_masters';
-- DESCRIBE batch_masters;
