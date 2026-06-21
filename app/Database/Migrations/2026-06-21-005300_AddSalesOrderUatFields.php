<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSalesOrderUatFields extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('sales_orders')) {
            $orderFields = [];
            if (! $this->db->fieldExists('discount_percent', 'sales_orders')) {
                $orderFields['discount_percent'] = ['type' => 'DECIMAL', 'constraint' => '9,4', 'default' => 0, 'after' => 'discount_amount'];
            }
            if (! $this->db->fieldExists('freight_amount', 'sales_orders')) {
                $orderFields['freight_amount'] = ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'discount_percent'];
            }
            if (! $this->db->fieldExists('other_amount', 'sales_orders')) {
                $orderFields['other_amount'] = ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'freight_amount'];
            }
            if (! $this->db->fieldExists('remarks', 'sales_orders')) {
                $orderFields['remarks'] = ['type' => 'TEXT', 'null' => true, 'after' => 'notes'];
            }
            if ($orderFields !== []) {
                $this->forge->addColumn('sales_orders', $orderFields);
            }
        }

        if ($this->db->tableExists('sales_order_lines')) {
            $lineFields = [];
            if (! $this->db->fieldExists('description', 'sales_order_lines')) {
                $lineFields['description'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'item_name'];
            }
            if (! $this->db->fieldExists('discount_percent', 'sales_order_lines')) {
                $lineFields['discount_percent'] = ['type' => 'DECIMAL', 'constraint' => '9,4', 'default' => 0, 'after' => 'unit_price'];
            }
            if (! $this->db->fieldExists('freight_amount', 'sales_order_lines')) {
                $lineFields['freight_amount'] = ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'discount_amount'];
            }
            if (! $this->db->fieldExists('special_charge_amount', 'sales_order_lines')) {
                $lineFields['special_charge_amount'] = ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'freight_amount'];
            }
            if (! $this->db->fieldExists('other_amount', 'sales_order_lines')) {
                $lineFields['other_amount'] = ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'special_charge_amount'];
            }
            if ($lineFields !== []) {
                $this->forge->addColumn('sales_order_lines', $lineFields);
            }
        }
    }

    public function down(): void
    {
        foreach (['remarks', 'other_amount', 'freight_amount', 'discount_percent'] as $field) {
            if ($this->db->tableExists('sales_orders') && $this->db->fieldExists($field, 'sales_orders')) {
                $this->forge->dropColumn('sales_orders', $field);
            }
        }
        foreach (['other_amount', 'special_charge_amount', 'freight_amount', 'discount_percent', 'description'] as $field) {
            if ($this->db->tableExists('sales_order_lines') && $this->db->fieldExists($field, 'sales_order_lines')) {
                $this->forge->dropColumn('sales_order_lines', $field);
            }
        }
    }
}
