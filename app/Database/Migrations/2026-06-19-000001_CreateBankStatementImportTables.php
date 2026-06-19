<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBankStatementImportTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('bank_statement_imports')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'cash_bank_account_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'cash_bank_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'statement_ref' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'statement_date' => ['type' => 'DATE'],
                'source_filename' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'opening_balance' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'closing_balance' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'debit_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'credit_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'net_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'line_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'matched_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'imported'],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'imported_at' => ['type' => 'DATETIME', 'null' => true],
                'imported_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('cash_bank_account_id');
            $this->forge->addKey(['company_id', 'cash_bank_code', 'statement_date']);
            $this->forge->createTable('bank_statement_imports');
        }

        if (! $this->db->tableExists('bank_statement_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'bank_statement_import_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'cash_bank_account_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'cash_bank_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'line_no' => ['type' => 'INT', 'unsigned' => true],
                'statement_date' => ['type' => 'DATE'],
                'value_date' => ['type' => 'DATE', 'null' => true],
                'reference_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'debit_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'credit_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'signed_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'balance_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'match_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'unmatched'],
                'cash_bank_entry_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'raw_payload' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('bank_statement_import_id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('cash_bank_account_id');
            $this->forge->addKey(['company_id', 'cash_bank_code', 'statement_date']);
            $this->forge->addKey('cash_bank_entry_id');
            $this->forge->createTable('bank_statement_lines');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('bank_statement_lines', true);
        $this->forge->dropTable('bank_statement_imports', true);
    }
}
