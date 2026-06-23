-- Add sidebar/menu link for Setup > Document Numbering.
-- Route:
--   /setup/document-numbering
--
-- If this SQL does not match your menu table structure, access the page directly by URL.

SET @setup_parent_id := (
    SELECT id
    FROM menu_items
    WHERE LOWER(COALESCE(title, name, label, '')) IN ('setup', 'master setup', 'master data', 'setup master')
       OR route IN ('setup', '/setup')
       OR url IN ('setup', '/setup')
    ORDER BY id ASC
    LIMIT 1
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
    'Document Numbering',
    'setup/document-numbering',
    'bx bx-hash',
    9,
    'setup.master.view',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM menu_items
    WHERE route IN ('setup/document-numbering', '/setup/document-numbering')
       OR url IN ('setup/document-numbering', '/setup/document-numbering')
       OR LOWER(COALESCE(title, name, label, '')) = 'document numbering'
);

SELECT id, parent_id, title, route, sort_order, permission, is_active
FROM menu_items
WHERE route IN ('setup/document-numbering')
   OR title = 'Document Numbering';
