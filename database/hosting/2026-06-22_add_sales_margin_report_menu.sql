-- Add Sales Margin Report menu item
-- Purpose: expose /sales/reports/margins in ERP sidebar.
-- Safe to run multiple times.

SET @sales_parent_id := (
    SELECT id
    FROM menu_items
    WHERE parent_id = 0
      AND label IN ('Sales', 'Sales & Distribution')
    ORDER BY id
    LIMIT 1
);

-- Create Reports section under Sales if it does not exist.
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

-- Normalize existing menu row if it already exists with older label/route.
UPDATE menu_items
SET
    parent_id = @reports_parent_id,
    label = 'Sales Margin Report',
    route = 'sales/reports/margins',
    icon = NULL,
    permission = 'dashboard.view',
    sort_order = 10,
    is_active = 1,
    updated_at = NOW()
WHERE route = 'sales/reports/margins'
   OR label IN ('Sales Margin', 'Sales Margin Report');

-- Insert menu item if still missing.
INSERT INTO menu_items (parent_id, label, route, icon, permission, sort_order, is_active, created_at, updated_at)
SELECT @reports_parent_id, 'Sales Margin Report', 'sales/reports/margins', NULL, 'dashboard.view', 10, 1, NOW(), NOW()
WHERE @reports_parent_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM (SELECT id FROM menu_items WHERE route = 'sales/reports/margins') existing_margin_report
  );

-- Verification
SELECT id, parent_id, label, route, permission, sort_order, is_active
FROM menu_items
WHERE route = 'sales/reports/margins'
   OR (parent_id = @sales_parent_id AND label = 'Reports')
ORDER BY parent_id, sort_order, label;
