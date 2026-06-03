<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTermsToSalesPurchaseOrders extends Migration
{
    public function up(): void
    {
        $this->addTermsCode('sales_orders', 'customer_name');
        $this->addTermsCode('purchase_orders', 'supplier_name');
    }

    public function down(): void
    {
        foreach (['sales_orders', 'purchase_orders'] as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('terms_code', $table)) {
                $this->forge->dropColumn($table, 'terms_code');
            }
        }
    }

    private function addTermsCode(string $table, string $after): void
    {
        if (! $this->db->tableExists($table) || $this->db->fieldExists('terms_code', $table)) {
            return;
        }

        $this->forge->addColumn($table, [
            'terms_code' => [
                'type' => 'VARCHAR',
                'constraint' => 12,
                'null' => true,
                'after' => $after,
            ],
        ]);
    }
}
