<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PurchaseReceiptEditablePriceFields extends Migration
{
    public function up(): void
    {
        $this->ensureColumn('purchase_receipt_lines', 'unit_price', 'DECIMAL(20,6) NOT NULL DEFAULT 0');
        $this->ensureColumn('purchase_receipt_lines', 'freight_amount', 'DECIMAL(20,6) NOT NULL DEFAULT 0');
        $this->ensureColumn('purchase_receipt_lines', 'special_price', 'DECIMAL(20,6) NOT NULL DEFAULT 0');

        if ($this->db->tableExists('purchase_receipt_lines')) {
            $this->db->query('UPDATE purchase_receipt_lines SET unit_price = COALESCE(NULLIF(unit_price, 0), unit_cost, 0) WHERE unit_price IS NULL OR unit_price = 0');
        }
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

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->db->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->countAllResults() > 0;
    }
}
