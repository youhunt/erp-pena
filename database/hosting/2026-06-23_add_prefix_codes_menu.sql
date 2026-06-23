-- Add Setup menu links for Transaction Codes and Prefix Codes.
-- Use this when routes exist but sidebar/menu database does not show them.
--
-- Main routes:
--   /setup/transaction-codes
--   /setup/prefix-codes
--
-- NOTE:
--   This script assumes the ERP menu table is `menu_items` with common columns:
--   parent_id, title/name/label, route/url, icon, sort_order, permission, is_active, created_at, updated_at.
--   If your menu_items table uses different column names, use the direct URL above or send DESC menu_items.

SET @setup_parent_id := (
    SELECT id
    FROM menu_items
    WHERE LOWER(COALESCE(title, name, label, '')) IN ('setup', 'master setup', 'master data', 'setup master')
       OR route IN ('setup', '/setup')
       OR url IN ('setup', '/setup')
    ORDER BY id ASC
    LIMIT 1
);

-- Fallback: if no setup parent is found, leave parent_id NULL so the menu still exists as top-level.

INSERT INTO menu_items (
    parent_id,
    title,
    route,
    icon,
    sort_order,
    permission,
    is_active,
    created_at,
    updated_at
)
SELECT
    @setup_parent_id,
    'Transaction Codes',
    'setup/transaction-codes',
    'bx bx-transfer',
    10,
    'setup.master.view',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM menu_items
    WHERE route IN ('setup/transaction-codes', '/setup/transaction-codes')
       OR url IN ('setup/transaction-codes', '/setup/transaction-codes')
       OR LOWER(COALESCE(title, name, label, '')) = 'transaction codes'
);

INSERT INTO menu_items (
    parent_id,
    title,
    route,
    icon,
    sort_order,
    permission,
    is_active,
    created_at,
    updated_at
)
SELECT
    @setup_parent_id,
    'Prefix Codes',
    'setup/prefix-codes',
    'bx bx-hash',
    11,
    'setup.master.view',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM menu_items
    WHERE route IN ('setup/prefix-codes', '/setup/prefix-codes')
       OR url IN ('setup/prefix-codes', '/setup/prefix-codes')
       OR LOWER(COALESCE(title, name, label, '')) = 'prefix codes'
);

SELECT id, parent_id, title, route, sort_order, permission, is_active
FROM menu_items
WHERE route IN ('setup/transaction-codes', 'setup/prefix-codes')
   OR title IN ('Transaction Codes', 'Prefix Codes')
ORDER BY sort_order, id;
