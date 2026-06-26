<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PurchaseReceiptReversalColumns extends Migration
{
    public function up(): void
    {
        $this->addPurchaseReceiptColumns();
        $this->addPurchaseReceiptLineColumns();
        $this->addInventoryMovementColumns();
    }

    public function down(): void
    {
        // Non-destructive migration. Reversal audit columns are intentionally kept.
    }

    private function addPurchaseReceiptColumns(): void
    {
        if (! $this->db->tableExists('purchase_receipts')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('reversal_gl_entry_id', 'purchase_receipts')) {
            $fields['reversal_gl_entry_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'gl_entry_id'];
        }
        if (! $this->db->fieldExists('reversed_at', 'purchase_receipts')) {
            $fields['reversed_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'posted_by'];
        }
        if (! $this->db->fieldExists('reversed_by', 'purchase_receipts')) {
            $fields['reversed_by'] = ['type' => 'INT', 'null' => true, 'after' => 'reversed_at'];
        }
        if (! $this->db->fieldExists('reversal_reason', 'purchase_receipts')) {
            $fields['reversal_reason'] = ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'reversed_by'];
        }

        if ($fields !== []) {
            $this->forge->addColumn('purchase_receipts', $fields);
        }
    }

    private function addPurchaseReceiptLineColumns(): void
    {
        if (! $this->db->tableExists('purchase_receipt_lines')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('reversed_qty', 'purchase_receipt_lines')) {
            $fields['reversed_qty'] = ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'after' => 'qty_received'];
        }
        if (! $this->db->fieldExists('reversal_movement_id', 'purchase_receipt_lines')) {
            $fields['reversal_movement_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'stock_movement_id'];
        }
        if (! $this->db->fieldExists('reversed_at', 'purchase_receipt_lines')) {
            $fields['reversed_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'location_id'];
        }
        if (! $this->db->fieldExists('reversed_by', 'purchase_receipt_lines')) {
            $fields['reversed_by'] = ['type' => 'INT', 'null' => true, 'after' => 'reversed_at'];
        }
        if (! $this->db->fieldExists('reversal_reason', 'purchase_receipt_lines')) {
            $fields['reversal_reason'] = ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'reversed_by'];
        }

        if ($fields !== []) {
            $this->forge->addColumn('purchase_receipt_lines', $fields);
        }
    }

    private function addInventoryMovementColumns(): void
    {
        if (! $this->db->tableExists('inventory_stock_movements')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('gl_entry_id', 'inventory_stock_movements')) {
            $fields['gl_entry_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'stock_value'];
        }
        if (! $this->db->fieldExists('reference_type', 'inventory_stock_movements')) {
            $fields['reference_type'] = ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'gl_entry_id'];
        }
        if (! $this->db->fieldExists('reference_id', 'inventory_stock_movements')) {
            $fields['reference_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'reference_type'];
        }
        if (! $this->db->fieldExists('reference_no', 'inventory_stock_movements')) {
            $fields['reference_no'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'reference_id'];
        }

        if ($fields !== []) {
            $this->forge->addColumn('inventory_stock_movements', $fields);
        }
    }
}
