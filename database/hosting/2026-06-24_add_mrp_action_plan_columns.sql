-- ERP PENA - Add MRP Action Plan tracking columns.
-- Purpose: MRP lines can be followed up as open/in_progress/converted/closed/ignored.

ALTER TABLE production_mrp_lines
    ADD COLUMN IF NOT EXISTS action_status VARCHAR(30) NOT NULL DEFAULT 'open' AFTER suggested_action,
    ADD COLUMN IF NOT EXISTS action_owner VARCHAR(100) NULL AFTER action_status,
    ADD COLUMN IF NOT EXISTS action_target_date DATE NULL AFTER action_owner,
    ADD COLUMN IF NOT EXISTS action_notes VARCHAR(500) NULL AFTER action_target_date,
    ADD COLUMN IF NOT EXISTS planned_doc_type VARCHAR(30) NULL AFTER action_notes,
    ADD COLUMN IF NOT EXISTS planned_doc_no VARCHAR(80) NULL AFTER planned_doc_type,
    ADD COLUMN IF NOT EXISTS action_updated_by INT NULL AFTER planned_doc_no,
    ADD COLUMN IF NOT EXISTS action_updated_at DATETIME NULL AFTER action_updated_by;

UPDATE production_mrp_lines
SET action_status = 'open'
WHERE action_status IS NULL OR TRIM(action_status) = '';

CREATE INDEX IF NOT EXISTS idx_mrp_lines_action_status ON production_mrp_lines (company_id, site_id, action_status, suggested_action);

SELECT 'MRP_ACTION_PLAN_COLUMNS_READY' AS check_name, COUNT(*) AS total
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'production_mrp_lines'
  AND column_name IN ('action_status','action_owner','action_target_date','action_notes','planned_doc_type','planned_doc_no','action_updated_by','action_updated_at');

SELECT action_status, suggested_action, COUNT(*) AS total
FROM production_mrp_lines
GROUP BY action_status, suggested_action
ORDER BY action_status, suggested_action;
