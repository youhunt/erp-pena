<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePurchaseReceiptCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('purchase_receipts')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'receipt_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'receipt_date' => ['type' => 'DATE'],
                'purchase_order_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'po_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'supplier_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'supplier_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'supplier_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
                'warehouse_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'location_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'posted'],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'posted_at' => ['type' => 'DATETIME', 'null' => true],
                'posted_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('purchase_order_id');
            $this->forge->addKey(['company_id', 'receipt_no'], false, true, 'uq_purchase_receipts_company_no');
            $this->forge->createTable('purchase_receipts');
        }

        if (! $this->db->tableExists('purchase_receipt_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'purchase_receipt_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'purchase_order_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'purchase_order_line_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'line_no' => ['type' => 'INT', 'unsigned' => true],
                'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'qty_received' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'warehouse_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'location_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('purchase_receipt_id');
            $this->forge->addKey('purchase_order_id');
            $this->forge->addKey('purchase_order_line_id');
            $this->forge->createTable('purchase_receipt_lines');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('purchase_receipt_lines', true);
        $this->forge->dropTable('purchase_receipts', true);
    }
}
