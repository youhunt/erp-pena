-- Manual MySQL migration for Inventory Transfer Document Header-Line
-- Project: PENA ERP
-- Use this file when hosting/cPanel cannot run: php spark migrate --all
-- Tested syntax target: MySQL 5.7+/MariaDB 10.x

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `inventory_transfer_headers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) UNSIGNED NOT NULL,
  `site_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `transfer_no` VARCHAR(50) NOT NULL,
  `transfer_date` DATETIME NOT NULL,
  `from_warehouse_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `from_location_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `to_warehouse_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `to_location_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'posted',
  `notes` TEXT NULL,
  `posted_at` DATETIME NULL DEFAULT NULL,
  `posted_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `created_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT(11) UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_transfer_no_company` (`company_id`, `transfer_no`),
  KEY `idx_inventory_transfer_tenant_date` (`company_id`, `site_id`, `transfer_date`),
  KEY `idx_inventory_transfer_status` (`status`),
  KEY `idx_inventory_transfer_from_wh` (`from_warehouse_id`),
  KEY `idx_inventory_transfer_to_wh` (`to_warehouse_id`),
  KEY `idx_inventory_transfer_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_transfer_lines` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `header_id` INT(11) UNSIGNED NOT NULL,
  `line_no` INT(11) NOT NULL DEFAULT 1,
  `item_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `item_code` VARCHAR(80) NOT NULL,
  `item_name` VARCHAR(255) NULL DEFAULT NULL,
  `uom_code` VARCHAR(30) NOT NULL DEFAULT 'PCS',
  `qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `transfer_out_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `transfer_in_movement_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_transfer_line_header` (`header_id`),
  KEY `idx_inventory_transfer_line_item` (`item_code`, `uom_code`),
  KEY `idx_inventory_transfer_line_item_id` (`item_id`),
  KEY `idx_inventory_transfer_line_out_movement` (`transfer_out_movement_id`),
  KEY `idx_inventory_transfer_line_in_movement` (`transfer_in_movement_id`),
  CONSTRAINT `fk_inventory_transfer_lines_header`
    FOREIGN KEY (`header_id`) REFERENCES `inventory_transfer_headers` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Optional verification:
-- SHOW TABLES LIKE 'inventory_transfer_%';
-- DESCRIBE inventory_transfer_headers;
-- DESCRIBE inventory_transfer_lines;
