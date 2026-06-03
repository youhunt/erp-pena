<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SalesMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $salesId = $this->menuItem(null, 'Sales', '#', 'bx-cart', null, 25, $now);
        $this->menuItem($salesId, 'Sales Order', 'sales/orders', null, 'sales.order.view', 10, $now);

        $this->db->table('menu_items')
            ->where('route', 'modules/sales-order')
            ->update(['is_active' => 0, 'updated_at' => $now]);

        $this->db->table('menu_items')
            ->where('parent_id', $salesId)
            ->where('label', 'Sales Orders')
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
