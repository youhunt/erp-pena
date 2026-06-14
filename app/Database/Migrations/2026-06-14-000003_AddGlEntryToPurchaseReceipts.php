<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGlEntryToPurchaseReceipts extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('purchase_receipts') || $this->db->fieldExists('gl_entry_id', 'purchase_receipts')) {
            return;
        }

        $this->forge->addColumn('purchase_receipts', [
            'gl_entry_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
                'after' => 'status',
            ],
        ]);
        $this->forge->addKey('gl_entry_id', false, false, 'idx_purchase_receipts_gl_entry');
    }

    public function down(): void
    {
        if ($this->db->tableExists('purchase_receipts') && $this->db->fieldExists('gl_entry_id', 'purchase_receipts')) {
            $this->forge->dropColumn('purchase_receipts', 'gl_entry_id');
        }
    }
}
