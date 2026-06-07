<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlPostingProfiles extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('gl_posting_profiles')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'module_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'posting_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'account_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('company_id');
        $this->forge->addKey(['company_id', 'module_code', 'posting_key'], false, true, 'uq_gl_posting_profiles_key');
        $this->forge->createTable('gl_posting_profiles');
    }

    public function down(): void
    {
        $this->forge->dropTable('gl_posting_profiles', true);
    }
}
