<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWorkCenterDetailAndAllocationTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('work_center_machine')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'work_center_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'site' => ['type' => 'VARCHAR', 'constraint' => 12],
                'dept' => ['type' => 'VARCHAR', 'constraint' => 12],
                'warehouse' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'work_center' => ['type' => 'VARCHAR', 'constraint' => 12],
                'no' => ['type' => 'INT', 'unsigned' => true, 'default' => 10],
                'machine' => ['type' => 'VARCHAR', 'constraint' => 12],
                'notes1' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'speed' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
                'capacity' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 100],
                'length' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'luom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'width' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'wuom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'height' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'huom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'volume' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'vuom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'qtylabor' => ['type' => 'DECIMAL', 'constraint' => '16,8', 'default' => 0],
                'workhour' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
                'created_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'system'],
                'updated_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'deleted_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
                'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('work_center_id');
            $this->forge->addKey(['company_id', 'site', 'dept', 'warehouse', 'work_center', 'no'], false, true, 'uq_wc_machine_scope_no');
            $this->forge->createTable('work_center_machine');
        }

        if (! $this->db->tableExists('work_center_cost')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'work_center_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'work_center' => ['type' => 'VARCHAR', 'constraint' => 12],
                'costtype' => ['type' => 'VARCHAR', 'constraint' => 12],
                'costamount' => ['type' => 'DECIMAL', 'constraint' => '20,8', 'default' => 0],
                'costuom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
                'notes2' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'created_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'system'],
                'updated_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'deleted_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
                'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('work_center_id');
            $this->forge->addKey(['company_id', 'work_center', 'costtype'], false, true, 'uq_wc_cost_scope_type');
            $this->forge->createTable('work_center_cost');
        }

        if (! $this->db->tableExists('allocationorder')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'sales_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'allocnumb' => ['type' => 'VARCHAR', 'constraint' => 60],
                'allocdate' => ['type' => 'DATE'],
                'site' => ['type' => 'VARCHAR', 'constraint' => 12],
                'customer' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'customern' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'shipdate' => ['type' => 'DATE', 'null' => true],
                'shipto' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'dept' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'whs' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'remarks' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'posted'],
                'posted_at' => ['type' => 'DATETIME', 'null' => true],
                'posted_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'system'],
                'updated_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'deleted_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
                'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('sales_order_id');
            $this->forge->addKey(['company_id', 'allocnumb'], false, true, 'uq_allocationorder_company_no');
            $this->forge->createTable('allocationorder');
        }

        if (! $this->db->tableExists('allocationline')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'allocationorder_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'sales_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'sales_order_line_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'allocate' => ['type' => 'VARCHAR', 'constraint' => 12],
                'site' => ['type' => 'VARCHAR', 'constraint' => 12],
                'customer' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'customern' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'line' => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => true],
                'soprefix' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
                'salesorder' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'transcode' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
                'soline' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
                'itemcode' => ['type' => 'VARCHAR', 'constraint' => 50],
                'itemname' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'soqty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'souom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
                'whs' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'loc' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'batchno' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'stockqty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'stockuom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
                'availableqty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'availableuom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
                'allocateqty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'allocateuom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
                'shipto' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'created_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'system'],
                'updated_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'deleted_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
                'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('allocationorder_id');
            $this->forge->addKey('sales_order_id');
            $this->forge->addKey('sales_order_line_id');
            $this->forge->createTable('allocationline');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('allocationline', true);
        $this->forge->dropTable('allocationorder', true);
        $this->forge->dropTable('work_center_cost', true);
        $this->forge->dropTable('work_center_machine', true);
    }
}
