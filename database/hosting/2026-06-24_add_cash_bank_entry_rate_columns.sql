-- ERP PENA - Cash/Bank Entry currency rate columns

USE `dberp_pena`;

ALTER TABLE cash_bank_entries
    ADD COLUMN IF NOT EXISTS rate_type VARCHAR(12) NULL AFTER currency_code,
    ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(20,12) NOT NULL DEFAULT 1 AFTER rate_type,
    ADD COLUMN IF NOT EXISTS base_currency VARCHAR(6) NOT NULL DEFAULT 'IDR' AFTER exchange_rate,
    ADD COLUMN IF NOT EXISTS base_amount DECIMAL(20,2) NOT NULL DEFAULT 0 AFTER base_currency;

UPDATE cash_bank_entries
SET
    exchange_rate = CASE WHEN exchange_rate IS NULL OR exchange_rate = 0 THEN 1 ELSE exchange_rate END,
    base_currency = COALESCE(NULLIF(base_currency, ''), 'IDR'),
    base_amount = CASE WHEN base_amount IS NULL OR base_amount = 0 THEN amount ELSE base_amount END;

SELECT 'CASH_BANK_ENTRY_RATE_COLUMNS_READY' AS check_name, COUNT(*) AS ready
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'cash_bank_entries'
  AND COLUMN_NAME IN ('rate_type','exchange_rate','base_currency','base_amount');
