<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductionMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $productionId = $this->menuItem(null, 'Production', '#', 'bx-factory', null, 80, $now);
        $this->menuItem($productionId, 'BOM', 'production/boms', null, 'production.view', 10, $now);
        $this->menuItem($productionId, 'Work Center', 'production/work-centers', null, 'production.view', 20, $now);
        $this->menuItem($productionId, 'Routing', 'production/routings', null, 'production.view', 30, $now);

        foreach (['modules/bom', 'modules/work-center', 'modules/routing'] as $route) {
            $this->db->table('menu_items')->where('route', $route)->update(['is_active' => 0, 'updated_at' => $now]);
        }
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
