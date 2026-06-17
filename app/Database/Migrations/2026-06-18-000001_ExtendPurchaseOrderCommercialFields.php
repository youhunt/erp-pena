<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendPurchaseOrderCommercialFields extends Migration
{
    public function up(): void
    {
        $this->addHeaderFields();
        $this->addLineFields();
    }

    public function down(): void
    {
        if ($this->db->tableExists('purchase_order_lines')) {
            foreach (['arrive_date','delivery_date','wht_amount','wht_percent','vat_amount','vat_percent','special_charge_amount','freight_amount','discount_percent','description'] as $field) {
                if ($this->db->fieldExists($field, 'purchase_order_lines')) {
                    $this->forge->dropColumn('purchase_order_lines', $field);
                }
            }
        }

        if ($this->db->tableExists('purchase_orders')) {
            foreach (['remarks','wht_amount','vat_amount','special_charge_amount','other_amount','freight_amount','discount_percent','arrive_date','delivery_date'] as $field) {
                if ($this->db->fieldExists($field, 'purchase_orders')) {
                    $this->forge->dropColumn('purchase_orders', $field);
                }
            }
        }
    }

    private function addHeaderFields(): void
    {
        if (! $this->db->tableExists('purchase_orders')) {
            return;
        }

        $fields = [];
        $this->addIfMissing($fields, 'delivery_date', ['type' => 'DATE', 'null' => true, 'after' => 'po_date']);
        $this->addIfMissing($fields, 'arrive_date', ['type' => 'DATE', 'null' => true, 'after' => 'delivery_date']);
        $this->addIfMissing($fields, 'discount_percent', ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0, 'after' => 'subtotal_amount']);
        $this->addIfMissing($fields, 'freight_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'discount_amount']);
        $this->addIfMissing($fields, 'other_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'freight_amount']);
        $this->addIfMissing($fields, 'special_charge_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'other_amount']);
        $this->addIfMissing($fields, 'vat_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'special_charge_amount']);
        $this->addIfMissing($fields, 'wht_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'vat_amount']);
        $this->addIfMissing($fields, 'remarks', ['type' => 'TEXT', 'null' => true, 'after' => 'notes']);

        if ($fields !== []) {
            $this->forge->addColumn('purchase_orders', $fields);
        }
    }

    private function addLineFields(): void
    {
        if (! $this->db->tableExists('purchase_order_lines')) {
            return;
        }

        $fields = [];
        $this->addIfMissing($fields, 'description', ['type' => 'TEXT', 'null' => true, 'after' => 'item_name']);
        $this->addIfMissing($fields, 'discount_percent', ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0, 'after' => 'unit_price']);
        $this->addIfMissing($fields, 'freight_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'discount_amount']);
        $this->addIfMissing($fields, 'special_charge_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'freight_amount']);
        $this->addIfMissing($fields, 'vat_percent', ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0, 'after' => 'special_charge_amount']);
        $this->addIfMissing($fields, 'vat_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'vat_percent']);
        $this->addIfMissing($fields, 'wht_percent', ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0, 'after' => 'vat_amount']);
        $this->addIfMissing($fields, 'wht_amount', ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'wht_percent']);
        $this->addIfMissing($fields, 'delivery_date', ['type' => 'DATE', 'null' => true, 'after' => 'line_status']);
        $this->addIfMissing($fields, 'arrive_date', ['type' => 'DATE', 'null' => true, 'after' => 'delivery_date']);

        if ($fields !== []) {
            $this->forge->addColumn('purchase_order_lines', $fields);
        }
    }

    private function addIfMissing(array &$fields, string $field, array $definition): void
    {
        $table = str_contains($field, 'dummy') ? '' : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        $actualTable = $table === 'addHeaderFields' ? 'purchase_orders' : 'purchase_order_lines';
        if (! $this->db->fieldExists($field, $actualTable)) {
            $fields[$field] = $definition;
        }
    }
}
