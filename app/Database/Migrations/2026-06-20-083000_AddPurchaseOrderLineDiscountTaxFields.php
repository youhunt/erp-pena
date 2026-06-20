<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPurchaseOrderLineDiscountTaxFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('purchase_order_lines')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('discount_percent', 'purchase_order_lines')) {
            $fields['discount_percent'] = [
                'type' => 'DECIMAL',
                'constraint' => '18,4',
                'default' => 0,
                'after' => 'unit_price',
            ];
        }

        if (! $this->db->fieldExists('discount_amount', 'purchase_order_lines')) {
            $fields['discount_amount'] = [
                'type' => 'DECIMAL',
                'constraint' => '18,2',
                'default' => 0,
                'after' => 'discount_percent',
            ];
        }

        if (! $this->db->fieldExists('vat_amount', 'purchase_order_lines')) {
            $fields['vat_amount'] = [
                'type' => 'DECIMAL',
                'constraint' => '18,2',
                'default' => 0,
                'after' => 'discount_amount',
            ];
        }

        if (! $this->db->fieldExists('wht_amount', 'purchase_order_lines')) {
            $fields['wht_amount'] = [
                'type' => 'DECIMAL',
                'constraint' => '18,2',
                'default' => 0,
                'after' => 'vat_amount',
            ];
        }

        if (! $this->db->fieldExists('tax_amount', 'purchase_order_lines')) {
            $fields['tax_amount'] = [
                'type' => 'DECIMAL',
                'constraint' => '18,2',
                'default' => 0,
                'after' => 'wht_amount',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('purchase_order_lines', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('purchase_order_lines')) {
            return;
        }

        foreach (['tax_amount', 'wht_amount', 'vat_amount', 'discount_amount', 'discount_percent'] as $field) {
            if ($this->db->fieldExists($field, 'purchase_order_lines')) {
                $this->forge->dropColumn('purchase_order_lines', $field);
            }
        }
    }
}
