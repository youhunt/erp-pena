<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCashBankCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('cash_bank_accounts')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'cash_bank_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'cash_bank_name' => ['type' => 'VARCHAR', 'constraint' => 180],
                'account_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'bank'],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'gl_account_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'opening_balance' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'current_balance' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey(['company_id', 'cash_bank_code'], false, true, 'uq_cash_bank_company_code');
            $this->forge->createTable('cash_bank_accounts');
        }

        if (! $this->db->tableExists('cash_bank_entries')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'cash_bank_account_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'entry_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'entry_date' => ['type' => 'DATE'],
                'entry_type' => ['type' => 'VARCHAR', 'constraint' => 20],
                'cash_bank_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'counter_account_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'reference_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'posted'],
                'gl_entry_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'posted_at' => ['type' => 'DATETIME', 'null' => true],
                'posted_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey(['company_id', 'entry_no'], false, true, 'uq_cash_bank_entries_company_no');
            $this->forge->addKey('cash_bank_account_id');
            $this->forge->addKey('gl_entry_id');
            $this->forge->createTable('cash_bank_entries');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('cash_bank_entries', true);
        $this->forge->dropTable('cash_bank_accounts', true);
    }
}
