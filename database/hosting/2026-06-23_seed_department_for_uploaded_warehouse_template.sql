-- Optional seed for uploaded Warehouses Template
-- File user berisi:
-- company_code = PENA
-- site_code = JKT
-- department_code = JKT
-- code = FG / RM
-- Jika import warehouses gagal dengan pesan department_code 'JKT' tidak ditemukan, jalankan SQL ini dulu.

INSERT INTO departments (company_id, site_id, code, name, description, is_active, created_at, updated_at)
SELECT c.id, s.id, 'JKT', 'JKT', 'Auto-created for warehouse import template', 1, NOW(), NOW()
FROM companies c
INNER JOIN sites s ON s.company_id = c.id AND s.code = 'JKT'
WHERE c.code = 'PENA'
  AND NOT EXISTS (
      SELECT 1
      FROM departments d
      WHERE d.company_id = c.id
        AND d.site_id = s.id
        AND d.code = 'JKT'
        AND (d.deleted_at IS NULL OR d.deleted_at = '')
  );
