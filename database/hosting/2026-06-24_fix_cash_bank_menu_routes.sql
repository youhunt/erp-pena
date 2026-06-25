-- ERP PENA - Fix Cash Bank menu routes to implemented master pages.
-- Run after menu_items has been synced/seeded.

USE `dberp_pena`;

UPDATE menu_items
SET route = 'cash-bank/accounts', updated_at = NOW()
WHERE label = 'Cash Bank ID';

UPDATE menu_items
SET route = 'cash-bank/currencies', updated_at = NOW()
WHERE label = 'Currency';

UPDATE menu_items
SET route = 'cash-bank/employees', updated_at = NOW()
WHERE label = 'Employee ID';

UPDATE menu_items
SET route = 'cash-bank/rates', updated_at = NOW()
WHERE label = 'Rate Master';

SELECT id, parent_id, label, route, permission, sort_order, is_active
FROM menu_items
WHERE parent_id = (SELECT id FROM (SELECT id FROM menu_items WHERE label = 'Cash Bank' LIMIT 1) x)
ORDER BY sort_order, id;
