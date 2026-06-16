<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInventoryMovementDocumentReversalFields extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('inventory_movement_documents')) {
            $fields = [];

            if (! $this->db->fieldExists('reversed_at', 'inventory_movement_documents')) {
                $fields['reversed_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'posted_by'];
            }
            if (! $this->db->fieldExists('reversed_by', 'inventory_movement_documents')) {
                $fields['reversed_by'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'reversed_at'];
            }
            if (! $this->db->fieldExists('reversal_reason', 'inventory_movement_documents')) {
                $fields['reversal_reason'] = ['type' => 'TEXT', 'null' => true, 'after' => 'reversed_by'];
            }
            if (! $this->db->fieldExists('reversal_document_id', 'inventory_movement_documents')) {
                $fields['reversal_document_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'reversal_reason'];
            }

            if ($fields !== []) {
                $this->forge->addColumn('inventory_movement_documents', $fields);
            }
        }

        if ($this->db->tableExists('inventory_movement_document_lines')) {
            if (! $this->db->fieldExists('reversal_movement_id', 'inventory_movement_document_lines')) {
                $this->forge->addColumn('inventory_movement_document_lines', [
                    'reversal_movement_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'stock_movement_id'],
                ]);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('inventory_movement_document_lines') && $this->db->fieldExists('reversal_movement_id', 'inventory_movement_document_lines')) {
            $this->forge->dropColumn('inventory_movement_document_lines', 'reversal_movement_id');
        }

        if ($this->db->tableExists('inventory_movement_documents')) {
            foreach (['reversal_document_id', 'reversal_reason', 'reversed_by', 'reversed_at'] as $field) {
                if ($this->db->fieldExists($field, 'inventory_movement_documents')) {
                    $this->forge->dropColumn('inventory_movement_documents', $field);
                }
            }
        }
    }
}
