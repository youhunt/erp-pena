<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;
use Config\ErpMenu;

class PenaErpSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $companyId = $this->upsert('companies', ['code' => 'PENA'], [
            'code' => 'PENA',
            'name' => 'PENA ERP Demo Company',
            'legal_name' => 'PT PENA ERP Indonesia',
            'base_currency' => 'IDR',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $siteId = $this->upsert('sites', ['company_id' => $companyId, 'code' => 'HO'], [
            'company_id' => $companyId,
            'code' => 'HO',
            'name' => 'Head Office',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->seedSetupMasters($companyId, $siteId, $now);
        $adminId = $this->seedAdminUser();
        $this->seedAccess($adminId, $companyId, $siteId, $now);
        $this->seedMenus($now);
    }

    private function seedSetupMasters(int $companyId, int $siteId, string $now): void
    {
        foreach ([
            ['currencies', ['code' => 'IDR'], ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'rounding' => 0]],
            ['countries', ['code' => 'IDN'], ['code' => 'IDN', 'name' => 'Indonesia']],
            ['departments', ['company_id' => $companyId, 'site_id' => $siteId, 'code' => 'GEN'], ['company_id' => $companyId, 'site_id' => $siteId, 'code' => 'GEN', 'name' => 'General']],
            ['warehouses', ['company_id' => $companyId, 'site_id' => $siteId, 'code' => 'MAIN'], ['company_id' => $companyId, 'site_id' => $siteId, 'code' => 'MAIN', 'name' => 'Main Warehouse']],
            ['uoms', ['company_id' => $companyId, 'code' => 'PCS'], ['company_id' => $companyId, 'code' => 'PCS', 'name' => 'Pieces']],
            ['uoms', ['company_id' => $companyId, 'code' => 'KG'], ['company_id' => $companyId, 'code' => 'KG', 'name' => 'Kilogram']],
            ['vat_rates', ['company_id' => $companyId, 'code' => 'VAT11'], ['company_id' => $companyId, 'code' => 'VAT11', 'name' => 'VAT 11%', 'rate' => 11]],
            ['wht_rates', ['company_id' => $companyId, 'code' => 'PPH23'], ['company_id' => $companyId, 'code' => 'PPH23', 'name' => 'PPH 23', 'rate' => 2]],
            ['transaction_codes', ['company_id' => $companyId, 'code' => 'SO'], ['company_id' => $companyId, 'code' => 'SO', 'name' => 'Sales Order']],
            ['transaction_codes', ['company_id' => $companyId, 'code' => 'PO'], ['company_id' => $companyId, 'code' => 'PO', 'name' => 'Purchase Order']],
            ['transaction_codes', ['company_id' => $companyId, 'code' => 'SI'], ['company_id' => $companyId, 'code' => 'SI', 'name' => 'Sales Invoice']],
            ['prefix_codes', ['company_id' => $companyId, 'code' => 'SO'], ['company_id' => $companyId, 'code' => 'SO', 'name' => 'Sales Order Prefix']],
            ['prefix_codes', ['company_id' => $companyId, 'code' => 'PO'], ['company_id' => $companyId, 'code' => 'PO', 'name' => 'Purchase Order Prefix']],
            ['prefix_codes', ['company_id' => $companyId, 'code' => 'SI'], ['company_id' => $companyId, 'code' => 'SI', 'name' => 'Sales Invoice Prefix']],
        ] as [$table, $where, $data]) {
            $this->upsert($table, $where, $data + ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function seedAdminUser(): int
    {
        $users = auth()->getProvider();
        $existing = $users->findByCredentials(['email' => 'admin@pena-erp.local']);

        if ($existing !== null) {
            $existing->addGroup('superadmin');

            return (int) $existing->id;
        }

        $user = new User([
            'username' => 'admin',
            'email' => 'admin@pena-erp.local',
            'password' => 'Admin123!',
        ]);

        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->activate();
        $user->addGroup('superadmin');

        return (int) $user->id;
    }

    private function seedAccess(int $adminId, int $companyId, int $siteId, string $now): void
    {
        $this->upsert('user_company_access', ['user_id' => $adminId, 'company_id' => $companyId], [
            'user_id' => $adminId,
            'company_id' => $companyId,
            'is_default' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsert('user_site_access', ['user_id' => $adminId, 'company_id' => $companyId, 'site_id' => $siteId], [
            'user_id' => $adminId,
            'company_id' => $companyId,
            'site_id' => $siteId,
            'is_default' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedMenus(string $now): void
    {
        $this->db->table('menu_items')->update([
            'is_active' => 0,
            'updated_at' => $now,
        ]);

        foreach ((new ErpMenu())->items() as $menu) {
            $this->seedMenuItem(null, $menu, $now);
        }
    }

    /**
     * @param array<string, mixed> $menu
     */
    private function seedMenuItem(?int $parentId, array $menu, string $now): int
    {
        $menuId = $this->menuItem(
            $parentId,
            (string) $menu['label'],
            $menu['route'] ?? '#',
            $menu['icon'] ?? null,
            $menu['permission'] ?? null,
            (int) ($menu['sort_order'] ?? 10),
            $now
        );
        $childSort = 10;

        foreach ($menu['children'] ?? [] as $child) {
            if (! isset($child['sort_order'])) {
                $child['sort_order'] = $childSort;
            }

            $this->seedMenuItem($menuId, $child, $now);
            $childSort += 10;
        }

        return $menuId;
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

    private function upsert(string $table, array $where, array $data): int
    {
        $row = $this->db->table($table)->where($where)->get()->getRowArray();

        if ($row !== null) {
            $this->db->table($table)->where('id', $row['id'])->update($data);

            return (int) $row['id'];
        }

        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }
}
