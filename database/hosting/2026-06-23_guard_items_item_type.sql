-- ERP PENA - Guard items.item_type for legacy/import/direct SQL inserts.
-- Purpose: any insert/update into items with NULL/blank item_type is normalized before NOT NULL constraint.

UPDATE items
SET item_type = CASE
    WHEN UPPER(COALESCE(item_group, '')) IN ('FG', 'FINISH', 'FINISHED', 'FINISHED_GOODS') THEN 'finished_good'
    WHEN UPPER(COALESCE(item_group, '')) IN ('WIP', 'SEMI', 'SEMI_FINISHED') THEN 'wip'
    WHEN UPPER(COALESCE(item_group, '')) IN ('SP', 'SUPPLY', 'SUPPLIES') THEN 'supply'
    WHEN UPPER(COALESCE(item_group, '')) IN ('SRV', 'SERVICE', 'SERVICES') THEN 'service'
    WHEN UPPER(COALESCE(item_group, '')) IN ('RM', 'RAW', 'RAW_MATERIAL') THEN 'material'
    WHEN UPPER(COALESCE(stockwhs, '')) IN ('FG', 'FINISH', 'FINISHED', 'FINISHED_GOODS') THEN 'finished_good'
    ELSE 'material'
END
WHERE item_type IS NULL OR TRIM(item_type) = '';

DROP TRIGGER IF EXISTS trg_items_default_item_type_bi;

CREATE TRIGGER trg_items_default_item_type_bi
BEFORE INSERT ON items
FOR EACH ROW
SET NEW.item_type = COALESCE(
    NULLIF(TRIM(NEW.item_type), ''),
    CASE
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('FG', 'FINISH', 'FINISHED', 'FINISHED_GOODS') THEN 'finished_good'
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('WIP', 'SEMI', 'SEMI_FINISHED') THEN 'wip'
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('SP', 'SUPPLY', 'SUPPLIES') THEN 'supply'
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('SRV', 'SERVICE', 'SERVICES') THEN 'service'
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('RM', 'RAW', 'RAW_MATERIAL') THEN 'material'
        WHEN UPPER(COALESCE(NEW.stockwhs, '')) IN ('FG', 'FINISH', 'FINISHED', 'FINISHED_GOODS') THEN 'finished_good'
        ELSE 'material'
    END
);

DROP TRIGGER IF EXISTS trg_items_default_item_type_bu;

CREATE TRIGGER trg_items_default_item_type_bu
BEFORE UPDATE ON items
FOR EACH ROW
SET NEW.item_type = COALESCE(
    NULLIF(TRIM(NEW.item_type), ''),
    CASE
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('FG', 'FINISH', 'FINISHED', 'FINISHED_GOODS') THEN 'finished_good'
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('WIP', 'SEMI', 'SEMI_FINISHED') THEN 'wip'
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('SP', 'SUPPLY', 'SUPPLIES') THEN 'supply'
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('SRV', 'SERVICE', 'SERVICES') THEN 'service'
        WHEN UPPER(COALESCE(NEW.item_group, '')) IN ('RM', 'RAW', 'RAW_MATERIAL') THEN 'material'
        WHEN UPPER(COALESCE(NEW.stockwhs, '')) IN ('FG', 'FINISH', 'FINISHED', 'FINISHED_GOODS') THEN 'finished_good'
        ELSE 'material'
    END
);

SELECT 'ITEM_TYPE_NULL_OR_BLANK' AS check_name, COUNT(*) AS total
FROM items
WHERE item_type IS NULL OR TRIM(item_type) = '';
