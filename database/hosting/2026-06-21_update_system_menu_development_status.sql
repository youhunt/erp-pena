-- Update System menu item for existing hosting database.
-- Reason: sidebar reads menu_items table through MenuService, not Config\ErpMenu directly.
-- Replace redundant "Data Import Export" menu with "Development Status".

UPDATE menu_items
SET
    label = 'Development Status',
    route = 'system/development-status',
    permission = 'dashboard.view',
    sort_order = sort_order,
    is_active = 1,
    updated_at = NOW()
WHERE label = 'Data Import Export'
   OR route = 'system/data-import';

-- Fallback: if the old menu row does not exist, insert Development Status under System.
INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT system_menu.id, 'Development Status', 'system/development-status', NULL, 'dashboard.view', 30, 1, NOW(), NOW()
FROM menu_items system_menu
WHERE system_menu.parent_id = 0
  AND system_menu.label = 'System'
  AND NOT EXISTS (
      SELECT 1
      FROM (SELECT id FROM menu_items WHERE route = 'system/development-status') existing_menu
  );
