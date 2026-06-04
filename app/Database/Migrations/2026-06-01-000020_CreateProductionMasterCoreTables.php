<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductionMasterCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('production_boms')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'site_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'department_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'warehouse_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'parent_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'parent_item_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'parent_item_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'bom_type' => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'standard'],
                'qty_batch' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 1],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'ratio_percent' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'default' => 100],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'active_date' => ['type' => 'DATETIME', 'null' => true],
                'inactive_date' => ['type' => 'DATETIME', 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey(['company_id', 'site_code', 'department_code', 'warehouse_code', 'parent_item_code'], false, true, 'uq_production_bom_parent_scope');
            $this->forge->createTable('production_boms');
        }

        if (! $this->db->tableExists('production_bom_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'production_bom_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'child_no' => ['type' => 'INT', 'unsigned' => true],
                'child_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'child_item_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'child_item_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'component_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'material'],
                'qty_used' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'factor' => ['type' => 'DECIMAL', 'constraint' => '8,5', 'default' => 1],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'active_date' => ['type' => 'DATETIME', 'null' => true],
                'inactive_date' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('production_bom_id');
            $this->forge->addKey(['production_bom_id', 'child_no'], false, true, 'uq_production_bom_line_no');
            $this->forge->createTable('production_bom_lines');
        }

        if (! $this->db->tableExists('production_work_centers')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'site_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'department_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'warehouse_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'work_center_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'description' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'machine_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'notes' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'speed' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
                'capacity_percent' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 100],
                'max_length' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'length_uom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'max_width' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'width_uom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'max_height' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'height_uom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'max_volume' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'volume_uom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'qty_labor' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'working_hour' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
                'cost_type' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'cost_amount' => ['type' => 'DECIMAL', 'constraint' => '20,8', 'default' => 0],
                'cost_uom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
                'active_date' => ['type' => 'DATE', 'null' => true],
                'inactive_date' => ['type' => 'DATE', 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey(['company_id', 'site_code', 'department_code', 'warehouse_code', 'work_center_code'], false, true, 'uq_work_center_scope_code');
            $this->forge->createTable('production_work_centers');
        }

        if (! $this->db->tableExists('production_routings')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'site_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'department_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'warehouse_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'description' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey(['company_id', 'site_code', 'item_code'], false, true, 'uq_routing_scope_item');
            $this->forge->createTable('production_routings');
        }

        if (! $this->db->tableExists('production_routing_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'production_routing_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'route_no' => ['type' => 'VARCHAR', 'constraint' => 12],
                'routing_name' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'work_center_code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'operation_type' => ['type' => 'VARCHAR', 'constraint' => 12],
                'hour_qty' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'hour_uom' => ['type' => 'VARCHAR', 'constraint' => 12],
                'std_speed' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'speed_uom' => ['type' => 'VARCHAR', 'constraint' => 12],
                'notes' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('production_routing_id');
            $this->forge->addKey(['production_routing_id', 'route_no'], false, true, 'uq_routing_line_no');
            $this->forge->createTable('production_routing_lines');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('production_routing_lines', true);
        $this->forge->dropTable('production_routings', true);
        $this->forge->dropTable('production_work_centers', true);
        $this->forge->dropTable('production_bom_lines', true);
        $this->forge->dropTable('production_boms', true);
    }
}
