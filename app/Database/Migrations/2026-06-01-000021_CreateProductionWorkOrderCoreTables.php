<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductionWorkOrderCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('production_work_orders')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'wo_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'WO'],
                'wo_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'wo_date' => ['type' => 'DATE'],
                'site_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'department_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'warehouse_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'work_center_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'parent_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'parent_item_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'parent_item_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'bom_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'routing_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'batch_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 1],
                'wo_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 1],
                'std_qty_finished' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
                'act_qty_finished' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'draft'],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey(['company_id', 'wo_no'], false, true, 'uq_production_work_order_no');
            $this->forge->addKey('parent_item_code');
            $this->forge->createTable('production_work_orders');
        }

        if (! $this->db->tableExists('production_work_order_components')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'production_work_order_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'line_no' => ['type' => 'INT', 'unsigned' => true],
                'component_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'component_item_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'component_item_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'qty_used' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'warehouse_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'location_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'batch_no' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'booking_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('production_work_order_id');
            $this->forge->addKey(['production_work_order_id', 'line_no'], false, true, 'uq_work_order_component_no');
            $this->forge->createTable('production_work_order_components');
        }

        if (! $this->db->tableExists('production_work_order_routings')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'production_work_order_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'line_no' => ['type' => 'INT', 'unsigned' => true],
                'routing_name' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'work_center_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'work_center_name' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'hour_qty' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('production_work_order_id');
            $this->forge->addKey(['production_work_order_id', 'line_no'], false, true, 'uq_work_order_routing_no');
            $this->forge->createTable('production_work_order_routings');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('production_work_order_routings', true);
        $this->forge->dropTable('production_work_order_components', true);
        $this->forge->dropTable('production_work_orders', true);
    }
}
