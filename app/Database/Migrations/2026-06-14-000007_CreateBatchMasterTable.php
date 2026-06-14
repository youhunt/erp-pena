<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBatchMasterTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('batch_masters')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'batch_no' => ['type' => 'VARCHAR', 'constraint' => 80],
            'batch_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'production_date' => ['type' => 'DATE', 'null' => true],
            'expiry_date' => ['type' => 'DATE', 'null' => true],
            'supplier_lot_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'manufacturer_lot_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['company_id', 'site_id']);
        $this->forge->addKey(['company_id', 'item_code']);
        $this->forge->addKey('expiry_date');
        $this->forge->addUniqueKey(['company_id', 'site_id', 'item_code', 'batch_no'], 'uq_batch_master_scope_item_batch');
        $this->forge->createTable('batch_masters', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('batch_masters', true);
    }
}
