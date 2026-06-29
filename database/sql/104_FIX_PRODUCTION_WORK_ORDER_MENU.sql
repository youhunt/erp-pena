-- ERP PENA - Fix Production Work Order sidebar menu
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.

UPDATE menu_items SET label = 'Production' WHERE LOWER(label) = 'production';

UPDATE menu_items
SET label = 'BOM', route = 'production/boms', sort_order = 100, is_active = 1
WHERE LOWER(label) = 'bom' OR route = 'production/boms';

UPDATE menu_items
SET label = 'Work Centers', route = 'production/work-centers', sort_order = 110, is_active = 1
WHERE LOWER(label) IN ('work center', 'work centers') OR route = 'production/work-centers';

UPDATE menu_items
SET label = 'Routings', route = 'production/routings', sort_order = 120, is_active = 1
WHERE LOWER(label) IN ('routing', 'routings') OR route = 'production/routings';

UPDATE menu_items
SET label = 'Work Orders', route = 'production/work-orders', sort_order = 130, is_active = 1
WHERE LOWER(label) IN ('work order', 'work orders') OR route = 'production/work-orders';

-- Ini bukan menu terpisah. Ini adalah aksi proses dari halaman detail Work Order.
UPDATE menu_items
SET is_active = 0
WHERE LOWER(label) IN ('allocate work order', 'work order in', 'work order out', 'work order in out', 'work order labor');

UPDATE menu_items
SET label = 'Production Period Close', route = 'gl/period-close/production', sort_order = 190, is_active = 1
WHERE LOWER(label) IN ('production period close', 'period close production') OR route = 'gl/period-close/production';
