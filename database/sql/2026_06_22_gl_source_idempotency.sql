-- Prevent a transaction source from creating more than one GL journal.
-- Safe to run from phpMyAdmin after reviewing the duplicate-source query.
SET @db_name := DATABASE();

-- This result must be empty before continuing.
SELECT company_id, source_module, source_type, source_id, COUNT(*) AS total
FROM gl_entries
WHERE source_id IS NOT NULL
GROUP BY company_id, source_module, source_type, source_id
HAVING COUNT(*) > 1;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'gl_entries'
      AND INDEX_NAME = 'uq_gl_entries_company_source'
);
SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE `gl_entries` ADD UNIQUE INDEX `uq_gl_entries_company_source` (`company_id`, `source_module`, `source_type`, `source_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verification:
-- SHOW INDEX FROM gl_entries WHERE Key_name = 'uq_gl_entries_company_source';
