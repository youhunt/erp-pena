-- ERP PENA - Fix MPS forecast period_month column
-- Jalankan di phpMyAdmin/cPanel jika tidak bisa menjalankan php spark migrate.

SET @db := DATABASE();
SELECT @db AS selected_database;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='production_forecasts' AND COLUMN_NAME='period_month') = 0,
    'ALTER TABLE production_forecasts ADD COLUMN period_month VARCHAR(7) NULL AFTER forecast_date',
    'SELECT ''production_forecasts.period_month exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE production_forecasts
SET period_month = DATE_FORMAT(forecast_date, '%Y-%m')
WHERE (period_month IS NULL OR period_month = '')
  AND forecast_date IS NOT NULL;
