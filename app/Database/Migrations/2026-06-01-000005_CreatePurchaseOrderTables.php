<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePurchaseOrderTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('purchase_orders')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'po_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'po_date' => ['type' => 'DATE'],
                'supplier_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'supplier_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'draft'],
                'subtotal_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'total_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'source_document_upload_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey(['company_id', 'po_no'], false, true, 'uq_purchase_orders_company_po_no');
            $this->forge->addKey('supplier_id');
            $this->forge->addKey('source_document_upload_id');
            $this->forge->createTable('purchase_orders');
        }

        if (! $this->db->tableExists('purchase_order_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'purchase_order_id' => ['type' => 'BIGINT', 'unsigned' => true],
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
            $this->forge->addKey('purchase_order_id');
            $this->forge->addKey('item_id');
            $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->createTable('purchase_order_lines');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('purchase_order_lines', true);
        $this->forge->dropTable('purchase_orders', true);
    }
}
