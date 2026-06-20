<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentNumberSequences extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('document_number_sequences')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'company_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'site_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'comment'    => '0 means company-level sequence or no active site',
            ],
            'transaction_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'prefix' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'period_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'comment'    => 'Examples: 2026, 202606, 20260619, ALL',
            ],
            'last_number' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'padding' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 5,
            ],
            'reset_period' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'monthly',
                'comment'    => 'daily, monthly, yearly, never',
            ],
            'last_document_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(
            ['company_id', 'site_id', 'transaction_code', 'prefix', 'period_key'],
            'uq_doc_no_sequence_scope'
        );
        $this->forge->addKey(['company_id', 'site_id']);
        $this->forge->addKey(['transaction_code']);
        $this->forge->createTable('document_number_sequences', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('document_number_sequences', true);
    }
}
