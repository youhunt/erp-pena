<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBankReconciliationCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('bank_reconciliations')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'cash_bank_account_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'cash_bank_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'reconcile_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'statement_date' => ['type' => 'DATE'],
                'statement_ref' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'book_balance' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'statement_balance' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'reconciled_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'difference_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
                'entry_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'posted'],
                'notes' => ['type' => 'TEXT', 'null' => true],
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
            $this->forge->addKey('cash_bank_account_id');
            $this->forge->addKey(['company_id', 'reconcile_no'], false, true, 'uq_bank_reconciliations_company_no');
            $this->forge->createTable('bank_reconciliations');
        }

        if ($this->db->tableExists('cash_bank_entries')) {
            $fields = [];
            if (! $this->db->fieldExists('bank_reconciliation_id', 'cash_bank_entries')) {
                $fields['bank_reconciliation_id'] = ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'gl_entry_id'];
            }
            if (! $this->db->fieldExists('reconciled_at', 'cash_bank_entries')) {
                $fields['reconciled_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'bank_reconciliation_id'];
            }
            if (! $this->db->fieldExists('reconciled_by', 'cash_bank_entries')) {
                $fields['reconciled_by'] = ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'reconciled_at'];
            }

            if ($fields !== []) {
                $this->forge->addColumn('cash_bank_entries', $fields);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('cash_bank_entries')) {
            foreach (['reconciled_by', 'reconciled_at', 'bank_reconciliation_id'] as $field) {
                if ($this->db->fieldExists($field, 'cash_bank_entries')) {
                    $this->forge->dropColumn('cash_bank_entries', $field);
                }
            }
        }

        $this->forge->dropTable('bank_reconciliations', true);
    }
}
