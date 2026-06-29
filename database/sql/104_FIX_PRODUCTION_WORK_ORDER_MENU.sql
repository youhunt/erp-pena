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

-- Ini bukan menu terpisah. Ini adalah aksi proses dari halaman detail Work Order.
UPDATE menu_items
SET is_active = 0
WHERE LOWER(label) IN ('allocate work order', 'work order in', 'work order out', 'work order in out', 'work order labor');

-- Hindari banyak menu berbeda memakai route yang sama production/work-orders.
UPDATE menu_items
SET is_active = 0
WHERE route = 'production/work-orders';

UPDATE menu_items mi
JOIN (
    SELECT MIN(id) AS keep_id
    FROM menu_items
    WHERE route = 'production/work-orders'
) keep_row ON mi.id = keep_row.keep_id
SET mi.label = 'Work Orders',
    mi.route = 'production/work-orders',
    mi.sort_order = 130,
    mi.is_active = 1;

UPDATE menu_items
SET label = 'Production Period Close', route = 'gl/period-close/production', sort_order = 190, is_active = 1
WHERE LOWER(label) IN ('production period close', 'period close production') OR route = 'gl/period-close/production';
