<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalesDeliveryCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('sales_deliveries')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'delivery_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'delivery_date' => ['type' => 'DATE'],
                'sales_order_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'so_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'customer_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'customer_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
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
            $this->forge->addKey('sales_order_id');
            $this->forge->addKey(['company_id', 'delivery_no'], false, true, 'uq_sales_deliveries_company_no');
            $this->forge->createTable('sales_deliveries');
        }

        if (! $this->db->tableExists('sales_delivery_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'sales_delivery_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'sales_order_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'sales_order_line_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'line_no' => ['type' => 'INT', 'unsigned' => true],
                'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'qty_delivered' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'warehouse_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'location_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('sales_delivery_id');
            $this->forge->addKey('sales_order_id');
            $this->forge->addKey('sales_order_line_id');
            $this->forge->createTable('sales_delivery_lines');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('sales_delivery_lines', true);
        $this->forge->dropTable('sales_deliveries', true);
    }
}
