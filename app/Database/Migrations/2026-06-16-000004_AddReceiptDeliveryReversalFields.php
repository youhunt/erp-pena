<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReceiptDeliveryReversalFields extends Migration
{
    public function up(): void
    {
        $this->addHeaderFields('purchase_receipts');
        $this->addLineFields('purchase_receipt_lines');
        $this->addHeaderFields('sales_deliveries');
        $this->addLineFields('sales_delivery_lines');
    }

    public function down(): void
    {
        $this->dropLineFields('sales_delivery_lines');
        $this->dropHeaderFields('sales_deliveries');
        $this->dropLineFields('purchase_receipt_lines');
        $this->dropHeaderFields('purchase_receipts');
    }

    private function addHeaderFields(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('reversed_at', $table)) {
            $fields['reversed_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'posted_by'];
        }
        if (! $this->db->fieldExists('reversed_by', $table)) {
            $fields['reversed_by'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'reversed_at'];
        }
        if (! $this->db->fieldExists('reversal_reason', $table)) {
            $fields['reversal_reason'] = ['type' => 'TEXT', 'null' => true, 'after' => 'reversed_by'];
        }

        if ($fields !== []) {
            $this->forge->addColumn($table, $fields);
        }
    }

    private function addLineFields(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('stock_movement_id', $table)) {
            $fields['stock_movement_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => $table === 'purchase_receipt_lines' ? 'purchase_order_line_id' : 'sales_order_line_id'];
        }
        if (! $this->db->fieldExists('reversal_movement_id', $table)) {
            $fields['reversal_movement_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'stock_movement_id'];
        }

        if ($fields !== []) {
            $this->forge->addColumn($table, $fields);
        }
    }

    private function dropHeaderFields(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach (['reversal_reason', 'reversed_by', 'reversed_at'] as $field) {
            if ($this->db->fieldExists($field, $table)) {
                $this->forge->dropColumn($table, $field);
            }
        }
    }

    private function dropLineFields(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach (['reversal_movement_id', 'stock_movement_id'] as $field) {
            if ($this->db->fieldExists($field, $table)) {
                $this->forge->dropColumn($table, $field);
            }
        }
    }
}
