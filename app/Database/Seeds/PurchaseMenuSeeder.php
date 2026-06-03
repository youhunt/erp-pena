<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PurchaseMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $purchaseId = $this->menuItem(null, 'Purchase', '#', 'bx-cart', null, 30, $now);
        $this->menuItem($purchaseId, 'Purchase Order', 'purchase/orders', null, 'purchase.po.view', 10, $now);

        $this->db->table('menu_items')
            ->where('parent_id', $purchaseId)
            ->where('label', 'Purchase Orders')
            ->update(['is_active' => 0, 'updated_at' => $now]);
    }

    private function menuItem(?int $parentId, string $label, string $route, ?string $icon, ?string $permission, int $sort, string $now): int
    {
        $row = $this->db->table('menu_items')
            ->where('parent_id', $parentId)
            ->where('label', $label)
            ->get()
            ->getRowArray();

        $data = [
            'parent_id' => $parentId,
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'permission' => $permission,
            'sort_order' => $sort,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if ($row !== null) {
            $this->db->table('menu_items')->where('id', $row['id'])->update($data);

            return (int) $row['id'];
        }

        $this->db->table('menu_items')->insert($data + ['created_at' => $now]);

        return (int) $this->db->insertID();
    }
}
