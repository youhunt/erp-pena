<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPurchaseReceiptLineReversalFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('purchase_receipt_lines')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('reversed_qty', 'purchase_receipt_lines')) {
            $fields['reversed_qty'] = [
                'type' => 'DECIMAL',
                'constraint' => '18,4',
                'default' => 0,
                'after' => 'qty_received',
            ];
        }

        if (! $this->db->fieldExists('reversed_at', 'purchase_receipt_lines')) {
            $fields['reversed_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'location_id',
            ];
        }

        if (! $this->db->fieldExists('reversed_by', 'purchase_receipt_lines')) {
            $fields['reversed_by'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'reversed_at',
            ];
        }

        if (! $this->db->fieldExists('reversal_reason', 'purchase_receipt_lines')) {
            $fields['reversal_reason'] = [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'reversed_by',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('purchase_receipt_lines', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('purchase_receipt_lines')) {
            return;
        }

        foreach (['reversal_reason', 'reversed_by', 'reversed_at', 'reversed_qty'] as $field) {
            if ($this->db->fieldExists($field, 'purchase_receipt_lines')) {
                $this->forge->dropColumn('purchase_receipt_lines', $field);
            }
        }
    }
}
