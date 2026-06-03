<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureSalesOrderSchema extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('sales_orders')) {
            $fields = [];

            $this->addIfMissing($fields, 'company_id', ['type' => 'BIGINT', 'unsigned' => true, 'null' => true]);
            $this->addIfMissing($fields, 'site_id', ['type' => 'BIGINT', 'unsigned' => true, 'null' => true]);
            $this->addIfMissing($fields, 'so_no', ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true]);
            $this->addIfMissing($fields, 'so_date', ['type' => 'DATE', 'null' => true]);
            $this->addIfMissing($fields, 'customer_id', ['type' => 'BIGINT', 'unsigned' => true, 'null' => true]);
            $this->addIfMissing($fields, 'customer_name', ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true]);
            $this->addIfMissing($fields, 'currency_code', ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR']);
            $this->addIfMissing($fields, 'status', ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'draft']);
            $this->addIfMissing($fields, 'subtotal_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0]);
            $this->addIfMissing($fields, 'tax_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0]);
            $this->addIfMissing($fields, 'discount_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0]);
            $this->addIfMissing($fields, 'total_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0]);
            $this->addIfMissing($fields, 'source_document_upload_id', ['type' => 'BIGINT', 'unsigned' => true, 'null' => true]);
            $this->addIfMissing($fields, 'notes', ['type' => 'TEXT', 'null' => true]);
            $this->addIfMissing($fields, 'created_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
            $this->addIfMissing($fields, 'updated_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
            $this->addIfMissing($fields, 'created_at', ['type' => 'DATETIME', 'null' => true]);
            $this->addIfMissing($fields, 'updated_at', ['type' => 'DATETIME', 'null' => true]);
            $this->addIfMissing($fields, 'deleted_at', ['type' => 'DATETIME', 'null' => true]);

            if ($fields !== []) {
                $this->forge->addColumn('sales_orders', $fields);
            }
        }

        if (! $this->db->tableExists('sales_order_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'sales_order_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'line_no' => ['type' => 'INT', 'unsigned' => true],
                'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'qty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'line_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('sales_order_id');
            $this->forge->createTable('sales_order_lines');
        }
    }

    public function down(): void
    {
        // Intentional no-op: this migration repairs older local schemas safely.
    }

    private function addIfMissing(array &$fields, string $field, array $definition): void
    {
        if (! $this->db->fieldExists($field, 'sales_orders')) {
            $fields[$field] = $definition;
        }
    }
}
