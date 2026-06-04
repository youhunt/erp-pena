<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureOrderLineInsertColumns extends Migration
{
    public function up(): void
    {
        $this->ensureColumns('sales_order_lines', [
            'sales_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'line_no' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'qty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_ordered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_reserved' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_delivered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_outstanding' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'line_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->ensureColumns('purchase_order_lines', [
            'purchase_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'line_no' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'qty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_ordered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_received' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_outstanding' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'line_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    public function down(): void
    {
        // No-op: safe repair migration for existing local schemas.
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     */
    private function ensureColumns(string $table, array $definitions): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = [];
        foreach ($definitions as $field => $definition) {
            if (! $this->db->fieldExists($field, $table)) {
                $fields[$field] = $definition;
            }
        }

        if ($fields !== []) {
            $this->forge->addColumn($table, $fields);
        }
    }
}
