<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryMovementDocumentTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('inventory_movement_documents')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'site_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'document_no' => ['type' => 'VARCHAR', 'constraint' => 50],
                'document_date' => ['type' => 'DATETIME'],
                'document_type' => ['type' => 'VARCHAR', 'constraint' => 30],
                'direction' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'posted'],
                'warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'location_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'total_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
                'total_value' => ['type' => 'DECIMAL', 'constraint' => '24,6', 'default' => 0],
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
            $this->forge->addUniqueKey(['company_id', 'site_id', 'document_no'], 'uq_inventory_movement_document_no');
            $this->forge->addKey(['company_id', 'site_id', 'document_type', 'document_date'], false, false, 'idx_inventory_movement_documents_scope');
            $this->forge->createTable('inventory_movement_documents', true);
        }

        if (! $this->db->tableExists('inventory_movement_document_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'document_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'stock_movement_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'line_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'batch_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => ''],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PCS'],
                'system_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'null' => true],
                'counted_qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'null' => true],
                'qty' => ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0],
                'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '24,6', 'default' => 0],
                'stock_value' => ['type' => 'DECIMAL', 'constraint' => '24,6', 'default' => 0],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['document_id', 'line_no'], false, false, 'idx_inventory_movement_document_lines_doc');
            $this->forge->addKey('stock_movement_id', false, false, 'idx_inventory_movement_document_lines_movement');
            $this->forge->addForeignKey('document_id', 'inventory_movement_documents', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('inventory_movement_document_lines', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_movement_document_lines', true);
        $this->forge->dropTable('inventory_movement_documents', true);
    }
}
