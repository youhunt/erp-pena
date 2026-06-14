<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGlEntryToInventoryStockMovements extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('inventory_stock_movements') || $this->db->fieldExists('gl_entry_id', 'inventory_stock_movements')) {
            return;
        }

        $this->forge->addColumn('inventory_stock_movements', [
            'gl_entry_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
                'after' => 'stock_value',
            ],
        ]);
        $this->forge->addKey('gl_entry_id', false, false, 'idx_inventory_stock_movements_gl_entry');
    }

    public function down(): void
    {
        if ($this->db->tableExists('inventory_stock_movements') && $this->db->fieldExists('gl_entry_id', 'inventory_stock_movements')) {
            $this->forge->dropColumn('inventory_stock_movements', 'gl_entry_id');
        }
    }
}
