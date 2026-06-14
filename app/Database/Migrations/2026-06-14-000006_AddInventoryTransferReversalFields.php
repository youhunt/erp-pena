<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInventoryTransferReversalFields extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('inventory_transfer_headers')) {
            $fields = [];

            if (! $this->db->fieldExists('reversed_at', 'inventory_transfer_headers')) {
                $fields['reversed_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'cancel_reason'];
            }
            if (! $this->db->fieldExists('reversed_by', 'inventory_transfer_headers')) {
                $fields['reversed_by'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'reversed_at'];
            }
            if (! $this->db->fieldExists('reversal_reason', 'inventory_transfer_headers')) {
                $fields['reversal_reason'] = ['type' => 'TEXT', 'null' => true, 'after' => 'reversed_by'];
            }

            if ($fields !== []) {
                $this->forge->addColumn('inventory_transfer_headers', $fields);
            }
        }

        if ($this->db->tableExists('inventory_transfer_lines')) {
            $fields = [];

            if (! $this->db->fieldExists('reversal_out_movement_id', 'inventory_transfer_lines')) {
                $fields['reversal_out_movement_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'transfer_in_movement_id'];
            }
            if (! $this->db->fieldExists('reversal_in_movement_id', 'inventory_transfer_lines')) {
                $fields['reversal_in_movement_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'reversal_out_movement_id'];
            }

            if ($fields !== []) {
                $this->forge->addColumn('inventory_transfer_lines', $fields);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('inventory_transfer_lines')) {
            foreach (['reversal_in_movement_id', 'reversal_out_movement_id'] as $field) {
                if ($this->db->fieldExists($field, 'inventory_transfer_lines')) {
                    $this->forge->dropColumn('inventory_transfer_lines', $field);
                }
            }
        }

        if ($this->db->tableExists('inventory_transfer_headers')) {
            foreach (['reversal_reason', 'reversed_by', 'reversed_at'] as $field) {
                if ($this->db->fieldExists($field, 'inventory_transfer_headers')) {
                    $this->forge->dropColumn('inventory_transfer_headers', $field);
                }
            }
        }
    }
}
