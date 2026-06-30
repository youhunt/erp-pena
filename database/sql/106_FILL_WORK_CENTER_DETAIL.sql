-- ERP PENA - Fill Work Center Machine Detail and Cost Detail
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark db:seed.

INSERT INTO work_center_machine (
    company_id, site_id, work_center_id, site, dept, warehouse, work_center,
    no, machine, notes1, speed, capacity, length, luom, width, wuom,
    height, huom, volume, vuom, qtylabor, workhour, active,
    created_by, updated_by, created_at, updated_at
)
SELECT
    wc.company_id,
    wc.site_id,
    wc.id,
    wc.site_code,
    wc.department_code,
    wc.warehouse_code,
    wc.work_center_code,
    1,
    COALESCE(NULLIF(wc.machine_code, ''), CONCAT(wc.work_center_code, '-MCH')),
    COALESCE(NULLIF(wc.notes, ''), 'Production feeder machine detail.'),
    COALESCE(wc.speed, 1),
    COALESCE(wc.capacity_percent, 100),
    COALESCE(wc.max_length, 0),
    COALESCE(wc.length_uom, ''),
    COALESCE(wc.max_width, 0),
    COALESCE(wc.width_uom, ''),
    COALESCE(wc.max_height, 0),
    COALESCE(wc.height_uom, ''),
    COALESCE(wc.max_volume, 0),
    COALESCE(wc.volume_uom, ''),
    COALESCE(wc.qty_labor, 1),
    COALESCE(wc.working_hour, 8),
    1,
    'feeder',
    'feeder',
    NOW(),
    NOW()
FROM production_work_centers wc
WHERE wc.work_center_code IN ('WC001','WC002','WC003','WC004','WC005')
  AND NOT EXISTS (
      SELECT 1
      FROM work_center_machine m
      WHERE m.work_center_id = wc.id
        AND m.no = 1
        AND m.deleted_at IS NULL
  );

UPDATE work_center_machine m
JOIN production_work_centers wc ON wc.id = m.work_center_id
SET
    m.site = wc.site_code,
    m.dept = wc.department_code,
    m.warehouse = wc.warehouse_code,
    m.work_center = wc.work_center_code,
    m.machine = COALESCE(NULLIF(wc.machine_code, ''), CONCAT(wc.work_center_code, '-MCH')),
    m.notes1 = COALESCE(NULLIF(wc.notes, ''), 'Production feeder machine detail.'),
    m.speed = COALESCE(wc.speed, 1),
    m.capacity = COALESCE(wc.capacity_percent, 100),
    m.qtylabor = COALESCE(wc.qty_labor, 1),
    m.workhour = COALESCE(wc.working_hour, 8),
    m.active = 1,
    m.updated_by = 'feeder',
    m.updated_at = NOW()
WHERE wc.work_center_code IN ('WC001','WC002','WC003','WC004','WC005')
  AND m.no = 1;

INSERT INTO work_center_cost (
    company_id, site_id, work_center_id, work_center,
    costtype, costamount, costuom, notes2, active,
    created_by, updated_by, created_at, updated_at
)
SELECT
    wc.company_id,
    wc.site_id,
    wc.id,
    wc.work_center_code,
    COALESCE(NULLIF(wc.cost_type, ''), 'Labor'),
    COALESCE(wc.cost_amount, 0),
    COALESCE(NULLIF(wc.cost_uom, ''), 'Hour'),
    'Production feeder cost detail.',
    1,
    'feeder',
    'feeder',
    NOW(),
    NOW()
FROM production_work_centers wc
WHERE wc.work_center_code IN ('WC001','WC002','WC003','WC004','WC005')
  AND NOT EXISTS (
      SELECT 1
      FROM work_center_cost c
      WHERE c.work_center_id = wc.id
        AND c.costtype = COALESCE(NULLIF(wc.cost_type, ''), 'Labor')
        AND c.deleted_at IS NULL
  );

UPDATE work_center_cost c
JOIN production_work_centers wc ON wc.id = c.work_center_id
SET
    c.work_center = wc.work_center_code,
    c.costtype = COALESCE(NULLIF(wc.cost_type, ''), 'Labor'),
    c.costamount = COALESCE(wc.cost_amount, 0),
    c.costuom = COALESCE(NULLIF(wc.cost_uom, ''), 'Hour'),
    c.notes2 = 'Production feeder cost detail.',
    c.active = 1,
    c.updated_by = 'feeder',
    c.updated_at = NOW()
WHERE wc.work_center_code IN ('WC001','WC002','WC003','WC004','WC005');
