<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGeneralLedgerCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('gl_books')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'book_code' => ['type' => 'VARCHAR', 'constraint' => 30],
                'book_name' => ['type' => 'VARCHAR', 'constraint' => 120],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'book_code'], false, true, 'uq_gl_books_company_code');
            $this->forge->createTable('gl_books');
        }

        if (! $this->db->tableExists('chart_accounts')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'account_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'account_name' => ['type' => 'VARCHAR', 'constraint' => 180],
                'account_type' => ['type' => 'VARCHAR', 'constraint' => 40],
                'normal_balance' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'debit'],
                'parent_account_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'is_postable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'account_no'], false, true, 'uq_chart_accounts_company_no');
            $this->forge->addKey(['company_id', 'account_type']);
            $this->forge->createTable('chart_accounts');
        }

        if (! $this->db->tableExists('gl_entries')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'gl_book_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'journal_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'journal_date' => ['type' => 'DATE'],
                'period' => ['type' => 'VARCHAR', 'constraint' => 7],
                'source_module' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'manual'],
                'source_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'source_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'source_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'exchange_rate' => ['type' => 'DECIMAL', 'constraint' => '18,8', 'default' => 1],
                'total_debit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'total_credit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'posted'],
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
            $this->forge->addKey(['company_id', 'journal_no'], false, true, 'uq_gl_entries_company_no');
            $this->forge->addKey(['company_id', 'period']);
            $this->forge->createTable('gl_entries');
        }

        if (! $this->db->tableExists('gl_entry_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'gl_entry_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'line_no' => ['type' => 'INT', 'unsigned' => true, 'default' => 10],
                'account_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'account_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'account_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'debit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'credit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('gl_entry_id');
            $this->forge->addKey(['company_id', 'account_no']);
            $this->forge->createTable('gl_entry_lines');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('gl_entry_lines', true);
        $this->forge->dropTable('gl_entries', true);
        $this->forge->dropTable('chart_accounts', true);
        $this->forge->dropTable('gl_books', true);
    }
}
