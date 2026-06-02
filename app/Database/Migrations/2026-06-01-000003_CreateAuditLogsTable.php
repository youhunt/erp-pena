<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogsTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('audit_logs')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'module' => ['type' => 'VARCHAR', 'constraint' => 80],
            'action' => ['type' => 'VARCHAR', 'constraint' => 50],
            'table_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'record_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'record_code' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'old_values' => ['type' => 'JSON', 'null' => true],
            'new_values' => ['type' => 'JSON', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['company_id', 'site_id']);
        $this->forge->addKey(['user_id']);
        $this->forge->addKey(['module', 'action']);
        $this->forge->addKey(['table_name', 'record_id']);
        $this->forge->addKey(['created_at']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('audit_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs', true);
    }
}
