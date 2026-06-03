<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryStockCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('inventory_stock_balances')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'warehouse_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'location_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'PCS'],
                'qty_on_hand' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'qty_reserved' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'qty_available' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'avg_cost' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'stock_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id', 'warehouse_id', 'location_id']);
            $this->forge->addKey(['company_id', 'item_code']);
            $this->forge->addKey(['company_id', 'site_id', 'warehouse_id', 'location_id', 'item_code'], false, true, 'uq_inventory_stock_scope_item');
            $this->forge->createTable('inventory_stock_balances');
        }

        if (! $this->db->tableExists('inventory_stock_movements')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'warehouse_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'location_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
                'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'PCS'],
                'movement_date' => ['type' => 'DATETIME'],
                'movement_type' => ['type' => 'VARCHAR', 'constraint' => 40],
                'direction' => ['type' => 'VARCHAR', 'constraint' => 10],
                'qty' => ['type' => 'DECIMAL', 'constraint' => '18,4'],
                'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'stock_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'reference_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'reference_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'reference_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'movement_date']);
            $this->forge->addKey(['company_id', 'item_code']);
            $this->forge->addKey(['reference_type', 'reference_id']);
            $this->forge->createTable('inventory_stock_movements');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_stock_movements', true);
        $this->forge->dropTable('inventory_stock_balances', true);
    }
}
