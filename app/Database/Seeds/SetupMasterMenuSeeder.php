<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SetupMasterMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $setupId = $this->menuItem(null, 'Setup', '#', 'bx-cog', null, 20, $now);

        $this->deactivateOldFlatItems($setupId, $now);

        $this->menuGroup($setupId, 'Organization', 10, [
            ['Company', 'setup/companies'],
            ['Site / Branch', 'setup/sites'],
            ['Department', 'setup/departments'],
            ['Warehouse', 'setup/warehouses'],
            ['Location', 'setup/locations'],
        ], $now);

        $this->menuGroup($setupId, 'Region', 20, [
            ['Country', 'setup/countries'],
            ['Province', 'setup/provinces'],
            ['City', 'setup/cities'],
            ['Postal Code', 'setup/postal-codes'],
            ['Address', 'setup/address-master'],
        ], $now);

        $this->menuGroup($setupId, 'Finance & Tax', 30, [
            ['Currency', 'setup/currencies'],
            ['VAT', 'setup/vat'],
            ['WHT / PPH', 'setup/wht'],
            ['Item VAT', 'setup/item-vat'],
        ], $now);

        $this->menuGroup($setupId, 'Product & Numbering', 40, [
            ['UoM', 'setup/uoms'],
            ['UoM Conversion', 'setup/uom-conversions'],
            ['Transaction Code', 'setup/transaction-codes'],
            ['Prefix Code', 'setup/prefix-codes'],
        ], $now);

        $this->menuGroup($setupId, 'System', 50, [
            ['User Management', 'admin/users', 'users.view'],
            ['Roles & Permissions', 'admin/roles', 'roles.view'],
            ['Audit Logs', 'audit-logs', 'audit.logs.view'],
        ], $now);
    }

    /**
     * Keep old flat menu rows in database but hide them, so the new grouped structure is clean.
     */
    private function deactivateOldFlatItems(int $setupId, string $now): void
    {
        $labels = [
            'Transaction Code',
            'Prefix Code',
            'Company',
            'Site',
            'Department',
            'Warehouse',
            'Location',
            'Country',
            'Province',
            'City',
            'Postal Code',
            'Currency',
            'Unit of Measure',
            'UoM Conversion',
            'VAT',
            'WHT / PPH',
            'Item VAT',
            'Address Master',
            'Users',
            'User Management',
            'Roles & Permissions',
            'Audit Logs',
        ];

        $this->db->table('menu_items')
            ->where('parent_id', $setupId)
            ->whereIn('label', $labels)
            ->update([
                'is_active' => 0,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param array<int, array{0:string,1:string,2?:string}> $children
     */
    private function menuGroup(int $parentId, string $label, int $sort, array $children, string $now, string $defaultPermission = 'setup.master.view'): void
    {
        $groupId = $this->menuItem($parentId, $label, '#', null, null, $sort, $now);
        $childSort = 10;

        foreach ($children as $child) {
            $this->menuItem($groupId, $child[0], $child[1], null, $child[2] ?? $defaultPermission, $childSort, $now);
            $childSort += 10;
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
