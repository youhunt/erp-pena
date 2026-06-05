<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\ErpMenu;

class ProductionMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $menu = new ErpMenu();
        $productionId = $this->menuItem(null, 'Production', '#', 'bx-factory', null, 80, $now);
        $sort = 10;

        foreach ($menu->childrenOf('Production') as $item) {
            if (! isset($item['sort_order'])) {
                $item['sort_order'] = $sort;
            }

            $this->menuItem(
                $productionId,
                (string) $item['label'],
                (string) ($item['route'] ?? '#'),
                null,
                $item['permission'] ?? null,
                (int) $item['sort_order'],
                $now
            );
            $sort += 10;
        }

        foreach (['modules/bom', 'modules/work-center', 'modules/routing', 'modules/work-order', 'modules/allocate-work-order', 'modules/work-order-in', 'modules/work-order-out', 'modules/work-order-in-out'] as $route) {
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
