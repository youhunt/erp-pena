<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureSalesOrderLineItemColumns extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('sales_order_lines')) {
            return;
        }

        $fields = [];
        foreach ($this->lineFields() as $field => $definition) {
            if (! $this->db->fieldExists($field, 'sales_order_lines')) {
                $fields[$field] = $definition;
            }
        }

        if ($fields !== []) {
            $this->forge->addColumn('sales_order_lines', $fields);
        }

        $this->backfillLegacyDescription();
    }

    public function down(): void
    {
        // Repair migration: keep added ERP columns in place.
    }

    private function lineFields(): array
    {
        return [
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'item_id'],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'item_code'],
            'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'qty_outstanding'],
        ];
    }

    private function backfillLegacyDescription(): void
    {
        if (! $this->db->fieldExists('description', 'sales_order_lines') || ! $this->db->fieldExists('item_name', 'sales_order_lines')) {
            return;
        }

        $this->db->query(
            "UPDATE sales_order_lines SET item_name = description WHERE (item_name IS NULL OR item_name = '') AND description IS NOT NULL"
        );
    }
}
