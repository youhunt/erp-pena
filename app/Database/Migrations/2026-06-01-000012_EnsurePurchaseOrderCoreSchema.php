<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsurePurchaseOrderCoreSchema extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('purchase_orders')) {
            $fields = [];
            foreach ($this->headerFields() as $field => $definition) {
                if (! $this->db->fieldExists($field, 'purchase_orders')) {
                    $fields[$field] = $definition;
                }
            }
            if ($fields !== []) {
                $this->forge->addColumn('purchase_orders', $fields);
            }
        }

        if ($this->db->tableExists('purchase_order_lines')) {
            $fields = [];
            foreach ($this->lineFields() as $field => $definition) {
                if (! $this->db->fieldExists($field, 'purchase_order_lines')) {
                    $fields[$field] = $definition;
                }
            }
            if ($fields !== []) {
                $this->forge->addColumn('purchase_order_lines', $fields);
            }
        }
    }

    public function down(): void
    {
        // No-op: schema repair migration for core ERP lifecycle fields.
    }

    private function headerFields(): array
    {
        return [
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'supplier' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'supplier_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'document_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'draft'],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'submitted_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'approved_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'closed_at' => ['type' => 'DATETIME', 'null' => true],
            'closed_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
            'cancelled_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'cancel_reason' => ['type' => 'TEXT', 'null' => true],
        ];
    }

    private function lineFields(): array
    {
        return [
            'qty_ordered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_received' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_outstanding' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'line_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open'],
        ];
    }
}
