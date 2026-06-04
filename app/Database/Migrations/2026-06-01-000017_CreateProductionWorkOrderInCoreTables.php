<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductionWorkOrderInCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('production_work_orders')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'wo_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'wo_date' => ['type' => 'DATE'],
                'production_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'work_order_in'],
                'finished_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'finished_item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
                'finished_item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'qty_plan' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'qty_good' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'qty_reject' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
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
            $this->forge->addKey(['company_id', 'wo_no'], false, true, 'uq_production_wo_company_no');
            $this->forge->createTable('production_work_orders');
        }

        if (! $this->db->tableExists('production_work_order_outputs')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'production_work_order_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'line_no' => ['type' => 'INT', 'unsigned' => true, 'default' => 10],
                'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
                'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'qty_good' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'qty_reject' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'warehouse_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'location_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'inventory_movement_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('production_work_order_id');
            $this->forge->addKey('inventory_movement_id');
            $this->forge->createTable('production_work_order_outputs');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('production_work_order_outputs', true);
        $this->forge->dropTable('production_work_orders', true);
    }
}
