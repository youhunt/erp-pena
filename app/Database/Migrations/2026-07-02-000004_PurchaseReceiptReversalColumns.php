<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PurchaseReceiptReversalColumns extends Migration
{
    public function up(): void
    {
        $this->ensureColumn('purchase_receipts', 'reversal_gl_entry_id', 'BIGINT UNSIGNED NULL');
        $this->ensureColumn('purchase_receipts', 'reversed_at', 'DATETIME NULL');
        $this->ensureColumn('purchase_receipts', 'reversed_by', 'BIGINT UNSIGNED NULL');
        $this->ensureColumn('purchase_receipts', 'reversal_reason', 'VARCHAR(255) NULL');

        $this->ensureColumn('purchase_receipt_lines', 'reversed_qty', 'DECIMAL(20,6) NOT NULL DEFAULT 0');
        $this->ensureColumn('purchase_receipt_lines', 'reversal_movement_id', 'BIGINT UNSIGNED NULL');
        $this->ensureColumn('purchase_receipt_lines', 'reversed_at', 'DATETIME NULL');
        $this->ensureColumn('purchase_receipt_lines', 'reversed_by', 'BIGINT UNSIGNED NULL');
        $this->ensureColumn('purchase_receipt_lines', 'reversal_reason', 'VARCHAR(255) NULL');

        $this->ensureIndex('purchase_receipts', 'idx_purchase_receipts_reversal_gl_entry_id', 'reversal_gl_entry_id');
        $this->ensureIndex('purchase_receipt_lines', 'idx_purchase_receipt_lines_reversal_movement_id', 'reversal_movement_id');
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
