<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\ErpMenu;

class SyncErpMenuSeeder extends Seeder
{
    private string $now;

    public function run(): void
    {
        if (! $this->db->tableExists('menu_items')) {
            return;
        }

        $this->now = date('Y-m-d H:i:s');

        $this->db->disableForeignKeyChecks();
        $this->db->table('menu_items')->truncate();
        $this->db->enableForeignKeyChecks();

        foreach (config(ErpMenu::class)->items() as $index => $menu) {
            $this->seedMenuItem(null, $menu, (int) ($menu['sort_order'] ?? (($index + 1) * 10)));
        }
    }

    /**
     * @param array<string, mixed> $menu
     */
    private function seedMenuItem(?int $parentId, array $menu, int $sortOrder): int
    {
        $data = $this->filterFields([
            'parent_id' => $parentId,
            'label' => $menu['label'],
            'route' => $menu['route'] ?? '#',
            'icon' => $menu['icon'] ?? null,
            'permission' => $menu['permission'] ?? null,
            'sort_order' => $sortOrder,
            'is_active' => 1,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $this->db->table('menu_items')->insert($data);
        $menuId = (int) $this->db->insertID();

        $childSort = 10;
        foreach (($menu['children'] ?? []) as $child) {
            $this->seedMenuItem($menuId, $child, (int) ($child['sort_order'] ?? $childSort));
            $childSort += 10;
        }

        return $menuId;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function filterFields(array $data): array
    {
        return array_filter(
            $data,
            fn (string $field): bool => $this->db->fieldExists($field, 'menu_items'),
            ARRAY_FILTER_USE_KEY
        );
    }
}
