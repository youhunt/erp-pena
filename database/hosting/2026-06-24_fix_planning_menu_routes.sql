-- ERP PENA - Fix Planning menu routes to real implemented modules.
-- Run this after menu_items has been synced/seeded.

USE `dberp_pena`;

UPDATE menu_items
SET route = 'production/forecasts', updated_at = NOW()
WHERE label = 'Forecast'
  AND route IN ('modules/forecast', 'forecast', 'planning/forecast', 'production/forecast');

UPDATE menu_items
SET route = 'production/mrp#planned-order-board', updated_at = NOW()
WHERE label = 'Planned Released'
  AND route IN ('modules/planned-released', 'planned-released', 'planned_release', 'planning/planned-released', 'production/planned-released');

-- Temporary: MPS uses Forecast Demand until dedicated MPS page is implemented.
UPDATE menu_items
SET route = 'production/forecasts', updated_at = NOW()
WHERE label = 'MPS'
  AND route IN ('modules/mps', 'mps', 'planning/mps', 'production/mps');

UPDATE menu_items
SET route = 'production/mrp', updated_at = NOW()
WHERE label = 'MRP'
  AND route IN ('modules/mrp', 'mrp', 'planning/mrp');

SELECT id, parent_id, label, route, permission, sort_order, is_active
FROM menu_items
WHERE parent_id = (SELECT id FROM (SELECT id FROM menu_items WHERE label = 'Planning' LIMIT 1) x)
ORDER BY sort_order, id;
