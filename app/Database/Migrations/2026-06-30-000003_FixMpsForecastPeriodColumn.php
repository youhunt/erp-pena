<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixMpsForecastPeriodColumn extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('production_forecasts')) {
            return;
        }

        if (! $this->columnExists('production_forecasts', 'period_month')) {
            $this->db->query('ALTER TABLE production_forecasts ADD COLUMN period_month VARCHAR(7) NULL AFTER forecast_date');
        }

        if ($this->columnExists('production_forecasts', 'forecast_date')) {
            $this->db->query("UPDATE production_forecasts SET period_month = DATE_FORMAT(forecast_date, '%Y-%m') WHERE (period_month IS NULL OR period_month = '') AND forecast_date IS NOT NULL");
        }
    }

    public function down(): void
    {
        // Non-destructive ERP migration.
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->db->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->countAllResults() > 0;
    }
}
