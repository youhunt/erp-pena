<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LinkBankReconciliationToStatementImport extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('bank_reconciliations')
            && ! $this->db->fieldExists('bank_statement_import_id', 'bank_reconciliations')) {
            $this->forge->addColumn('bank_reconciliations', [
                'bank_statement_import_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'cash_bank_account_id',
                ],
            ]);
            $this->forge->addKey('bank_statement_import_id');
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('bank_reconciliations')
            && $this->db->fieldExists('bank_statement_import_id', 'bank_reconciliations')) {
            $this->forge->dropColumn('bank_reconciliations', 'bank_statement_import_id');
        }
    }
}
