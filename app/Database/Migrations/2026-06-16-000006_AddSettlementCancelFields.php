<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSettlementCancelFields extends Migration
{
    public function up(): void
    {
        $this->addFields('ap_payments');
        $this->addFields('ar_receipts');
    }

    public function down(): void
    {
        $this->dropFields('ar_receipts');
        $this->dropFields('ap_payments');
    }

    private function addFields(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('status', $table)) {
            $fields['status'] = ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'posted', 'after' => 'notes'];
        }
        if (! $this->db->fieldExists('cancelled_at', $table)) {
            $fields['cancelled_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'posted_by'];
        }
        if (! $this->db->fieldExists('cancelled_by', $table)) {
            $fields['cancelled_by'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'cancelled_at'];
        }
        if (! $this->db->fieldExists('cancel_reason', $table)) {
            $fields['cancel_reason'] = ['type' => 'TEXT', 'null' => true, 'after' => 'cancelled_by'];
        }
        if (! $this->db->fieldExists('reversal_cash_bank_entry_id', $table)) {
            $fields['reversal_cash_bank_entry_id'] = ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'cash_bank_entry_id'];
        }
        if (! $this->db->fieldExists('reversal_gl_entry_id', $table)) {
            $fields['reversal_gl_entry_id'] = ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'gl_entry_id'];
        }

        if ($fields !== []) {
            $this->forge->addColumn($table, $fields);
        }
    }

    private function dropFields(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach (['reversal_gl_entry_id', 'reversal_cash_bank_entry_id', 'cancel_reason', 'cancelled_by', 'cancelled_at', 'status'] as $field) {
            if ($this->db->fieldExists($field, $table)) {
                $this->forge->dropColumn($table, $field);
            }
        }
    }
}
