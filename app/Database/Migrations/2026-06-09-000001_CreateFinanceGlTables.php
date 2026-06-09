<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFinanceGlTables extends Migration
{
    public function up(): void
    {
        $this->createGlBooks();
        $this->createChartAccounts();
        $this->createGlPostingProfiles();
        $this->createGlEntries();
        $this->createGlEntryLines();
    }

    public function down(): void
    {
        $this->forge->dropTable('gl_entry_lines', true);
        $this->forge->dropTable('gl_entries', true);
        $this->forge->dropTable('gl_posting_profiles', true);
        $this->forge->dropTable('chart_accounts', true);
        $this->forge->dropTable('gl_books', true);
    }

    private function createGlBooks(): void
    {
        if ($this->db->tableExists('gl_books')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'book_code' => ['type' => 'VARCHAR', 'constraint' => 60],
            'book_name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'book_code'], 'uk_gl_books_company_code');
        $this->forge->addKey('company_id', false, false, 'idx_gl_books_company');
        $this->forge->createTable('gl_books', true);
    }

    private function createChartAccounts(): void
    {
        if ($this->db->tableExists('chart_accounts')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'account_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'account_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'account_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'asset'],
            'normal_balance' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'debit'],
            'parent_account_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'is_postable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'account_no'], 'uk_chart_accounts_company_account');
        $this->forge->addKey('company_id', false, false, 'idx_chart_accounts_company');
        $this->forge->addKey('account_no', false, false, 'idx_chart_accounts_account_no');
        $this->forge->createTable('chart_accounts', true);
    }

    private function createGlPostingProfiles(): void
    {
        if ($this->db->tableExists('gl_posting_profiles')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'module_code' => ['type' => 'VARCHAR', 'constraint' => 60],
            'posting_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'account_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'updated_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'module_code', 'posting_key'], 'uk_gl_profiles_company_module_key');
        $this->forge->addKey('company_id', false, false, 'idx_gl_profiles_company');
        $this->forge->createTable('gl_posting_profiles', true);
    }

    private function createGlEntries(): void
    {
        if ($this->db->tableExists('gl_entries')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'gl_book_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'journal_no' => ['type' => 'VARCHAR', 'constraint' => 80],
            'journal_date' => ['type' => 'DATE'],
            'period' => ['type' => 'VARCHAR', 'constraint' => 7, 'null' => true],
            'source_module' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'source_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'source_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
            'exchange_rate' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 1],
            'total_debit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'total_credit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'posted'],
            'posted_at' => ['type' => 'DATETIME', 'null' => true],
            'posted_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'updated_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'journal_no'], 'uk_gl_entries_company_journal');
        $this->forge->addKey(['company_id', 'journal_date'], false, false, 'idx_gl_entries_company_date');
        $this->forge->addKey('site_id', false, false, 'idx_gl_entries_site');
        $this->forge->createTable('gl_entries', true);
    }

    private function createGlEntryLines(): void
    {
        if ($this->db->tableExists('gl_entry_lines')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'gl_entry_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'line_no' => ['type' => 'INT', 'constraint' => 10, 'default' => 1],
            'account_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'account_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'account_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'debit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'credit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('gl_entry_id', false, false, 'idx_gl_entry_lines_entry');
        $this->forge->addKey(['company_id', 'account_no'], false, false, 'idx_gl_entry_lines_company_account');
        $this->forge->createTable('gl_entry_lines', true);
    }
}
