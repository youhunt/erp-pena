-- Add Sales Margin Report menu item
-- Supports top-level menu parent_id NULL or 0.

SET @sales_parent_id := (
    SELECT id
    FROM menu_items
    WHERE (parent_id IS NULL OR parent_id = 0)
      AND label IN ('Sales', 'Sales & Distribution')
    ORDER BY id
    LIMIT 1
);

INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT @sales_parent_id, 'Reports', '#', NULL, 'dashboard.view', 90, 1, NOW(), NOW()
WHERE @sales_parent_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM (SELECT id FROM menu_items WHERE parent_id = @sales_parent_id AND label = 'Reports') existing_reports
  );

SET @reports_parent_id := COALESCE((
    SELECT id
    FROM menu_items
    WHERE parent_id = @sales_parent_id
      AND label = 'Reports'
    ORDER BY id
    LIMIT 1
), @sales_parent_id);

UPDATE menu_items
SET parent_id = @reports_parent_id,
    label = 'Sales Margin Report',
    route = 'sales/reports/margins',
    icon = NULL,
    permission = 'dashboard.view',
    sort_order = 10,
    is_active = 1,
    updated_at = NOW()
WHERE route = 'sales/reports/margins'
   OR label IN ('Sales Margin', 'Sales Margin Report');

INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT @reports_parent_id, 'Sales Margin Report', 'sales/reports/margins', NULL, 'dashboard.view', 10, 1, NOW(), NOW()
WHERE @reports_parent_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM (SELECT id FROM menu_items WHERE route = 'sales/reports/margins') existing_margin_report
  );

SELECT id, parent_id, label, route, permission, sort_order, is_active
FROM menu_items
WHERE route = 'sales/reports/margins'
   OR (parent_id = @sales_parent_id AND label = 'Reports')
ORDER BY parent_id, sort_order, label;
