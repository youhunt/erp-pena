<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryTransferDocuments extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('inventory_transfer_headers')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'site_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'transfer_no' => ['type' => 'VARCHAR', 'constraint' => 50],
                'transfer_date' => ['type' => 'DATETIME'],
                'from_warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'from_location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'to_warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'to_location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'posted_at' => ['type' => 'DATETIME', 'null' => true],
                'posted_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'transfer_no'], 'uq_inventory_transfer_no_company');
            $this->forge->addKey(['company_id', 'site_id', 'transfer_date'], false, false, 'idx_inventory_transfer_tenant_date');
            $this->forge->addKey('status');
            $this->forge->createTable('inventory_transfer_headers', true);
        }

        if (! $this->db->tableExists('inventory_transfer_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'header_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'line_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
                'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'PCS'],
                'qty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'transfer_out_movement_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'transfer_in_movement_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('header_id');
            $this->forge->addKey(['item_code', 'uom_code'], false, false, 'idx_inventory_transfer_line_item');
            $this->forge->createTable('inventory_transfer_lines', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_transfer_lines', true);
        $this->forge->dropTable('inventory_transfer_headers', true);
    }
}
