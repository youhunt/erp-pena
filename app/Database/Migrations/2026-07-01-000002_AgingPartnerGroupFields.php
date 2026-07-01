<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AgingPartnerGroupFields extends Migration
{
    public function up(): void
    {
        $this->ensureColumn('suppliers', 'supplier_group', 'VARCHAR(50) NULL');
        $this->ensureColumn('customers', 'customer_group', 'VARCHAR(50) NULL');
        $this->ensureIndex('suppliers', 'idx_suppliers_supplier_group', 'supplier_group');
        $this->ensureIndex('customers', 'idx_customers_customer_group', 'customer_group');
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

        $this->db->query('CREATE INDEX `' . $index . '` ON `' . $table . '` (`' . $column . '`)');
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
