<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureSalesOrderCoreSchema extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('sales_orders')) {
            $fields = [];
            foreach ($this->headerFields() as $field => $definition) {
                if (! $this->db->fieldExists($field, 'sales_orders')) {
                    $fields[$field] = $definition;
                }
            }
            if ($fields !== []) {
                $this->forge->addColumn('sales_orders', $fields);
            }
        }

        if ($this->db->tableExists('sales_order_lines')) {
            $fields = [];
            foreach ($this->lineFields() as $field => $definition) {
                if (! $this->db->fieldExists($field, 'sales_order_lines')) {
                    $fields[$field] = $definition;
                }
            }
            if ($fields !== []) {
                $this->forge->addColumn('sales_order_lines', $fields);
            }
            $this->backfillLines();
        }
    }

    public function down(): void
    {
        // No-op: repair migration for existing local schemas.
    }

    private function headerFields(): array
    {
        return [
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'customer' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'customer_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'document_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'draft'],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'submitted_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'approved_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reserved_at' => ['type' => 'DATETIME', 'null' => true],
            'reserved_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
            'cancelled_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'cancel_reason' => ['type' => 'TEXT', 'null' => true],
        ];
    }

    private function lineFields(): array
    {
        return [
            'qty_ordered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_reserved' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_delivered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_outstanding' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'line_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open'],
        ];
    }

    private function backfillLines(): void
    {
        $rows = $this->db->table('sales_order_lines')->get()->getResultArray();
        foreach ($rows as $row) {
            $qty = (float) ($row['qty'] ?? 0);
            $ordered = (float) ($row['qty_ordered'] ?? 0);
            $reserved = (float) ($row['qty_reserved'] ?? 0);
            $delivered = (float) ($row['qty_delivered'] ?? 0);
            if ($ordered <= 0) {
                $ordered = $qty;
            }
            $this->db->table('sales_order_lines')->where('id', $row['id'])->update([
                'qty_ordered' => $ordered,
                'qty_reserved' => $reserved,
                'qty_delivered' => $delivered,
                'qty_outstanding' => max(0, $ordered - $delivered),
                'line_status' => $delivered >= $ordered && $ordered > 0 ? 'delivered' : ($reserved > 0 ? 'reserved' : 'open'),
            ]);
        }
    }
}
