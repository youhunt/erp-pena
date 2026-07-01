<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DeliveryFromAllocationLink extends Migration
{
    public function up(): void
    {
        $this->ensureColumn('sales_deliveries', 'allocation_order_id', 'BIGINT UNSIGNED NULL');
        $this->ensureColumn('sales_delivery_lines', 'allocationline_id', 'BIGINT UNSIGNED NULL');
        $this->ensureColumn('allocationorder', 'delivery_id', 'BIGINT UNSIGNED NULL');
        $this->ensureColumn('allocationorder', 'delivered_at', 'DATETIME NULL');
        $this->ensureColumn('allocationorder', 'delivered_by', 'VARCHAR(50) NULL');
        $this->ensureColumn('allocationline', 'delivered_qty', 'DECIMAL(20,6) NOT NULL DEFAULT 0');
        $this->ensureColumn('allocationline', 'delivery_line_id', 'BIGINT UNSIGNED NULL');
        $this->ensureIndex('sales_deliveries', 'idx_sales_deliveries_allocation_order_id', 'allocation_order_id');
        $this->ensureIndex('sales_delivery_lines', 'idx_sales_delivery_lines_allocationline_id', 'allocationline_id');
        $this->ensureIndex('allocationline', 'idx_allocationline_delivery_line_id', 'delivery_line_id');
    }

    public function down(): void
    {
        // Non-destructive ERP migration.
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if (! $this->db->tableExists($table) || $this->columnExists($table, $column)) {
            return;
        }

        $this->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }

    private function ensureIndex(string $table, string $index, string $column): void
    {
        if (! $this->db->tableExists($table) || ! $this->columnExists($table, $column) || $this->indexExists($table, $index)) {
            return;
        }

        $this->db->query('ALTER TABLE `' . $table . '` ADD INDEX `' . $index . '` (`' . $column . '`)');
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->db->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->countAllResults() > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        return (int) $this->db->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->countAllResults() > 0;
    }
}
