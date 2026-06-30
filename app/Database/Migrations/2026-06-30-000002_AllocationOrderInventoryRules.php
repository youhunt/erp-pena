<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllocationOrderInventoryRules extends Migration
{
    public function up(): void
    {
        $this->salesOrderLineColumns();
        $this->batchMasterColumns();
    }

    public function down(): void
    {
        // Non-destructive ERP migration.
    }

    private function salesOrderLineColumns(): void
    {
        if (! $this->db->tableExists('sales_order_lines')) {
            return;
        }

        foreach ([
            'allocation_qty' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            'available_so_qty' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            'so_stock_qty' => 'DECIMAL(20,6) NULL',
            'so_stock_uom' => 'VARCHAR(12) NULL',
            'trans_code' => 'VARCHAR(12) NULL',
            'whs' => 'VARCHAR(30) NULL',
            'shipto' => 'VARCHAR(30) NULL',
        ] as $column => $definition) {
            if (! $this->columnExists('sales_order_lines', $column)) {
                $this->db->query('ALTER TABLE sales_order_lines ADD COLUMN `' . $column . '` ' . $definition);
            }
        }

        $this->db->query("UPDATE sales_order_lines SET allocation_qty = COALESCE(qty_reserved, 0) WHERE allocation_qty = 0");
        $this->db->query("UPDATE sales_order_lines SET available_so_qty = GREATEST(0, COALESCE(qty_ordered, qty, 0) - COALESCE(allocation_qty, qty_reserved, 0)) WHERE available_so_qty = 0");
    }

    private function batchMasterColumns(): void
    {
        if (! $this->db->tableExists('batch_masters')) {
            return;
        }

        foreach ([
            'warehouse_id' => 'INT NULL',
            'location_id' => 'INT NULL',
            'whs' => 'VARCHAR(30) NULL',
            'loc' => 'VARCHAR(30) NULL',
            'stock_qty' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            'allocation_qty' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            'available_qty' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
        ] as $column => $definition) {
            if (! $this->columnExists('batch_masters', $column)) {
                $this->db->query('ALTER TABLE batch_masters ADD COLUMN `' . $column . '` ' . $definition);
            }
        }
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
