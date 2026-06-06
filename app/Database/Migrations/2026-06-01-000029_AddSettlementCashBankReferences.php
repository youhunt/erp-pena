<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSettlementCashBankReferences extends Migration
{
    public function up(): void
    {
        $this->addSettlementRefs('ap_payments');
        $this->addSettlementRefs('ar_receipts');
    }

    public function down(): void
    {
        $this->dropSettlementRefs('ar_receipts');
        $this->dropSettlementRefs('ap_payments');
    }

    private function addSettlementRefs(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('cash_bank_entry_id', $table)) {
            $fields['cash_bank_entry_id'] = ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'cash_bank_code'];
        }
        if (! $this->db->fieldExists('gl_entry_id', $table)) {
            $fields['gl_entry_id'] = ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'cash_bank_entry_id'];
        }

        if ($fields !== []) {
            $this->forge->addColumn($table, $fields);
        }
    }

    private function dropSettlementRefs(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach (['gl_entry_id', 'cash_bank_entry_id'] as $field) {
            if ($this->db->fieldExists($field, $table)) {
                $this->forge->dropColumn($table, $field);
            }
        }
    }
}
