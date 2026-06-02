<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CleanupPurchaseMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('menu_items')
            ->where('label', 'Purchase Order')
            ->where('route', 'modules/purchase-order')
            ->update([
                'is_active' => 0,
                'updated_at' => $now,
            ]);

        $this->db->table('menu_items')
            ->where('route', 'modules/purchase-order')
            ->update([
                'is_active' => 0,
                'updated_at' => $now,
            ]);
    }
}
