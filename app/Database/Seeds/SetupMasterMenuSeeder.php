<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SetupMasterMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $parentId = $this->menuItem(null, 'Setup', '#', 'bx-cog', null, 20, $now);

        $sort = 10;
        foreach ([
            ['Transaction Code', 'setup/transaction-codes'],
            ['Prefix Code', 'setup/prefix-codes'],
            ['Company', 'setup/companies'],
            ['Site', 'setup/sites'],
            ['Department', 'setup/departments'],
            ['Warehouse', 'setup/warehouses'],
            ['Location', 'setup/locations'],
            ['Country', 'setup/countries'],
            ['Province', 'setup/provinces'],
            ['City', 'setup/cities'],
            ['Postal Code', 'setup/postal-codes'],
            ['Currency', 'setup/currencies'],
            ['Unit of Measure', 'setup/uoms'],
            ['UoM Conversion', 'setup/uom-conversions'],
            ['VAT', 'setup/vat'],
            ['WHT / PPH', 'setup/wht'],
            ['Item VAT', 'setup/item-vat'],
            ['Address Master', 'setup/address-master'],
        ] as [$label, $route]) {
            $this->menuItem($parentId, $label, $route, null, 'setup.master.view', $sort, $now);
            $sort += 10;
        }
    }

    private function menuItem(?int $parentId, string $label, ?string $route, ?string $icon, ?string $permission, int $sort, string $now): int
    {
        $builder = $this->db->table('menu_items')
            ->where('label', $label)
            ->where('parent_id', $parentId);

        $row = $builder->get()->getRowArray();
        $data = [
            'parent_id' => $parentId,
            'label' => $label,
            'route' => $route ?: '#',
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
