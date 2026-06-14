<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGlEntryToSalesDeliveries extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('sales_deliveries') || $this->db->fieldExists('gl_entry_id', 'sales_deliveries')) {
            return;
        }

        $this->forge->addColumn('sales_deliveries', [
            'gl_entry_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
                'after' => 'status',
            ],
        ]);
        $this->forge->addKey('gl_entry_id', false, false, 'idx_sales_deliveries_gl_entry');
    }

    public function down(): void
    {
        if ($this->db->tableExists('sales_deliveries') && $this->db->fieldExists('gl_entry_id', 'sales_deliveries')) {
            $this->forge->dropColumn('sales_deliveries', 'gl_entry_id');
        }
    }
}
