<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePeriodCloseCoreTables extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('period_closes')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'module_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'period' => ['type' => 'VARCHAR', 'constraint' => 7],
            'period_start' => ['type' => 'DATE'],
            'period_end' => ['type' => 'DATE'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'closed'],
            'closed_at' => ['type' => 'DATETIME', 'null' => true],
            'closed_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reopened_at' => ['type' => 'DATETIME', 'null' => true],
            'reopened_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['company_id', 'site_id']);
        $this->forge->addKey(['company_id', 'module_code', 'period'], false, true, 'uq_period_closes_company_module_period');
        $this->forge->createTable('period_closes');
    }

    public function down(): void
    {
        $this->forge->dropTable('period_closes', true);
    }
}
