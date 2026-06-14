<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInventoryTransferWorkflowAuditFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('inventory_transfer_headers')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('submitted_at', 'inventory_transfer_headers')) {
            $fields['submitted_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'notes'];
        }
        if (! $this->db->fieldExists('submitted_by', 'inventory_transfer_headers')) {
            $fields['submitted_by'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'submitted_at'];
        }
        if (! $this->db->fieldExists('cancelled_at', 'inventory_transfer_headers')) {
            $fields['cancelled_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'posted_by'];
        }
        if (! $this->db->fieldExists('cancelled_by', 'inventory_transfer_headers')) {
            $fields['cancelled_by'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'cancelled_at'];
        }
        if (! $this->db->fieldExists('cancel_reason', 'inventory_transfer_headers')) {
            $fields['cancel_reason'] = ['type' => 'TEXT', 'null' => true, 'after' => 'cancelled_by'];
        }

        if ($fields !== []) {
            $this->forge->addColumn('inventory_transfer_headers', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('inventory_transfer_headers')) {
            return;
        }

        foreach (['cancel_reason', 'cancelled_by', 'cancelled_at', 'submitted_by', 'submitted_at'] as $field) {
            if ($this->db->fieldExists($field, 'inventory_transfer_headers')) {
                $this->forge->dropColumn('inventory_transfer_headers', $field);
            }
        }
    }
}
