-- PENA ERP Hosting SQL Update
-- Date: 2026-06-20
-- Purpose:
--   Normalize existing core master data so transaction lookups are stable.
--
-- Master data normalized:
--   customers  : customer/customern -> code/name, active -> is_active
--   suppliers  : supplier/supplierna -> code/name, active -> is_active
--   items      : item_code/item_name -> code/name, active -> is_active,
--                stockuom -> purchaseuom/sellinguom fallback,
--                price fallback among item_price/purchasep/sellingprice
--   warehouses : uppercase code, active default
--   locations  : uppercase code, active default
--
-- How to run on hosting:
--   1. Backup database first from phpMyAdmin.
--   2. Open phpMyAdmin > choose PENA ERP database.
--   3. Open SQL tab.
--   4. Paste and execute this file.

-- Customers
UPDATE `customers`
SET
  `customer` = UPPER(TRIM(COALESCE(NULLIF(`customer`, ''), NULLIF(`code`, ''), `customer`))),
  `code` = UPPER(TRIM(COALESCE(NULLIF(`code`, ''), NULLIF(`customer`, ''), `code`))),
  `customern` = TRIM(COALESCE(NULLIF(`customern`, ''), NULLIF(`name`, ''), `customern`)),
  `name` = TRIM(COALESCE(NULLIF(`name`, ''), NULLIF(`customern`, ''), `name`)),
  `terms_code` = COALESCE(NULLIF(`terms_code`, ''), NULLIF(`terms`, ''), `terms_code`),
  `tax_number` = COALESCE(NULLIF(`tax_number`, ''), NULLIF(`taxnumber`, ''), `tax_number`),
  `address` = COALESCE(NULLIF(`address`, ''), NULLIF(`officeaddre`, ''), `address`),
  `phone` = COALESCE(NULLIF(`phone`, ''), NULLIF(`officephon`, ''), `phone`),
  `is_active` = COALESCE(`is_active`, `active`, 1),
  `active` = COALESCE(`active`, `is_active`, 1)
WHERE `deleted_at` IS NULL OR `deleted_at` IS NOT NULL;

-- Suppliers
UPDATE `suppliers`
SET
  `supplier` = UPPER(TRIM(COALESCE(NULLIF(`supplier`, ''), NULLIF(`code`, ''), `supplier`))),
  `code` = UPPER(TRIM(COALESCE(NULLIF(`code`, ''), NULLIF(`supplier`, ''), `code`))),
  `supplierna` = TRIM(COALESCE(NULLIF(`supplierna`, ''), NULLIF(`name`, ''), `supplierna`)),
  `name` = TRIM(COALESCE(NULLIF(`name`, ''), NULLIF(`supplierna`, ''), `name`)),
  `terms_code` = COALESCE(NULLIF(`terms_code`, ''), NULLIF(`terms`, ''), `terms_code`),
  `tax_number` = COALESCE(NULLIF(`tax_number`, ''), NULLIF(`taxnumber`, ''), `tax_number`),
  `address` = COALESCE(NULLIF(`address`, ''), NULLIF(`officeaddre`, ''), `address`),
  `phone` = COALESCE(NULLIF(`phone`, ''), NULLIF(`officephon`, ''), `phone`),
  `is_active` = COALESCE(`is_active`, `active`, 1),
  `active` = COALESCE(`active`, `is_active`, 1)
WHERE `deleted_at` IS NULL OR `deleted_at` IS NOT NULL;

-- Items
UPDATE `items`
SET
  `item_code` = UPPER(TRIM(COALESCE(NULLIF(`item_code`, ''), NULLIF(`code`, ''), NULLIF(`item_coded`, ''), `item_code`))),
  `code` = UPPER(TRIM(COALESCE(NULLIF(`code`, ''), NULLIF(`item_code`, ''), NULLIF(`item_coded`, ''), `code`))),
  `item_name` = TRIM(COALESCE(NULLIF(`item_name`, ''), NULLIF(`name`, ''), NULLIF(`item_named`, ''), `item_name`)),
  `name` = TRIM(COALESCE(NULLIF(`name`, ''), NULLIF(`item_name`, ''), NULLIF(`item_named`, ''), `name`)),
  `stockuom` = UPPER(TRIM(`stockuom`)),
  `purchaseuom` = UPPER(TRIM(COALESCE(NULLIF(`purchaseuom`, ''), NULLIF(`stockuom`, ''), `purchaseuom`))),
  `sellinguom` = UPPER(TRIM(COALESCE(NULLIF(`sellinguom`, ''), NULLIF(`stockuom`, ''), `sellinguom`))),
  `item_price` = COALESCE(NULLIF(`item_price`, 0), NULLIF(`sellingprice`, 0), NULLIF(`purchasep`, 0), 0),
  `purchasep` = COALESCE(NULLIF(`purchasep`, 0), NULLIF(`item_price`, 0), 0),
  `sellingprice` = COALESCE(NULLIF(`sellingprice`, 0), NULLIF(`item_price`, 0), 0),
  `is_active` = COALESCE(`is_active`, `active`, 1),
  `active` = COALESCE(`active`, `is_active`, 1)
WHERE `deleted_at` IS NULL OR `deleted_at` IS NOT NULL;

-- Warehouses
UPDATE `warehouses`
SET
  `code` = UPPER(TRIM(`code`)),
  `name` = TRIM(`name`),
  `is_active` = COALESCE(`is_active`, 1)
WHERE `deleted_at` IS NULL OR `deleted_at` IS NOT NULL;

-- Locations
UPDATE `locations`
SET
  `code` = UPPER(TRIM(`code`)),
  `name` = TRIM(`name`),
  `is_active` = COALESCE(`is_active`, 1)
WHERE `deleted_at` IS NULL OR `deleted_at` IS NOT NULL;

-- Verification
SELECT 'customers missing code/name' AS check_name, COUNT(*) AS total
FROM `customers`
WHERE COALESCE(`code`, '') = '' OR COALESCE(`name`, '') = '';

SELECT 'suppliers missing code/name' AS check_name, COUNT(*) AS total
FROM `suppliers`
WHERE COALESCE(`code`, '') = '' OR COALESCE(`name`, '') = '';

SELECT 'items missing code/name/uom' AS check_name, COUNT(*) AS total
FROM `items`
WHERE COALESCE(`code`, '') = '' OR COALESCE(`name`, '') = '' OR COALESCE(`stockuom`, '') = '';

SELECT 'warehouses missing code/name' AS check_name, COUNT(*) AS total
FROM `warehouses`
WHERE COALESCE(`code`, '') = '' OR COALESCE(`name`, '') = '';

SELECT 'locations missing code/name' AS check_name, COUNT(*) AS total
FROM `locations`
WHERE COALESCE(`code`, '') = '' OR COALESCE(`name`, '') = '';
