<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemovePurchaseOrderLineCommercialFields extends Migration
{
    private array $obsoleteFields = [
        'discount_percent',
        'discount_amount',
        'freight_amount',
        'special_charge_amount',
        'vat_percent',
        'vat_amount',
        'wht_percent',
        'wht_amount',
        'tax_amount',
        'delivery_date',
        'arrive_date',
    ];

    public function up(): void
    {
        if (! $this->db->tableExists('purchase_order_lines')) {
            return;
        }

        foreach ($this->obsoleteFields as $field) {
            if ($this->db->fieldExists($field, 'purchase_order_lines')) {
                $this->forge->dropColumn('purchase_order_lines', $field);
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('purchase_order_lines')) {
            return;
        }

        $fields = [];
        $this->addIfMissing($fields, 'discount_percent', ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0, 'after' => 'unit_price']);
        $this->addIfMissing($fields, 'discount_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'discount_percent']);
        $this->addIfMissing($fields, 'freight_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'discount_amount']);
        $this->addIfMissing($fields, 'special_charge_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'freight_amount']);
        $this->addIfMissing($fields, 'vat_percent', ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0, 'after' => 'special_charge_amount']);
        $this->addIfMissing($fields, 'vat_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'vat_percent']);
        $this->addIfMissing($fields, 'wht_percent', ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0, 'after' => 'vat_amount']);
        $this->addIfMissing($fields, 'wht_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'wht_percent']);
        $this->addIfMissing($fields, 'tax_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'wht_amount']);
        $this->addIfMissing($fields, 'delivery_date', ['type' => 'DATE', 'null' => true, 'after' => 'line_status']);
        $this->addIfMissing($fields, 'arrive_date', ['type' => 'DATE', 'null' => true, 'after' => 'delivery_date']);

        if ($fields !== []) {
            $this->forge->addColumn('purchase_order_lines', $fields);
        }
    }

    private function addIfMissing(array &$fields, string $field, array $definition): void
    {
        if (! $this->db->fieldExists($field, 'purchase_order_lines')) {
            $fields[$field] = $definition;
        }
    }
}
