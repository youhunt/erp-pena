-- PENA ERP Hosting SQL Update
-- Date: 2026-06-20
-- Purpose:
--   Add physical unique indexes for clean core master data.
--
-- IMPORTANT:
--   Run these files first and make sure duplicate audit returns zero rows:
--     1. database/hosting/2026-06-20_normalize_core_master_data.sql
--     2. database/hosting/2026-06-20_audit_core_master_codes.sql
--
-- Scope:
--   customers   : company_id + site_id + code
--   suppliers   : company_id + site_id + code
--   items       : company_id + site_id + code
--   warehouses  : company_id + site_id + code
--   locations   : company_id + site_id + warehouse_id + code
--
-- Notes:
--   MySQL/MariaDB allows multiple NULL values in a UNIQUE index.
--   Because site_id can be NULL, this index is strongest when site_id is populated.
--   If your business wants company-level uniqueness across NULL site_id rows too,
--   standardize site_id before running this SQL.

ALTER TABLE `customers`
  ADD UNIQUE INDEX `uniq_customers_company_site_code` (`company_id`, `site_id`, `code`);

ALTER TABLE `suppliers`
  ADD UNIQUE INDEX `uniq_suppliers_company_site_code` (`company_id`, `site_id`, `code`);

ALTER TABLE `items`
  ADD UNIQUE INDEX `uniq_items_company_site_code` (`company_id`, `site_id`, `code`);

ALTER TABLE `warehouses`
  ADD UNIQUE INDEX `uniq_warehouses_company_site_code` (`company_id`, `site_id`, `code`);

ALTER TABLE `locations`
  ADD UNIQUE INDEX `uniq_locations_company_site_warehouse_code` (`company_id`, `site_id`, `warehouse_id`, `code`);

-- Verification
SHOW INDEX FROM `customers` WHERE Key_name = 'uniq_customers_company_site_code';
SHOW INDEX FROM `suppliers` WHERE Key_name = 'uniq_suppliers_company_site_code';
SHOW INDEX FROM `items` WHERE Key_name = 'uniq_items_company_site_code';
SHOW INDEX FROM `warehouses` WHERE Key_name = 'uniq_warehouses_company_site_code';
SHOW INDEX FROM `locations` WHERE Key_name = 'uniq_locations_company_site_warehouse_code';
