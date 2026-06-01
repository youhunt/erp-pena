<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;

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
            ['prefix_codes', ['company_id' => $companyId, 'code' => 'SO'], ['company_id' => $companyId, 'code' => 'SO', 'name' => 'Sales Order Prefix']],
            ['prefix_codes', ['company_id' => $companyId, 'code' => 'PO'], ['company_id' => $companyId, 'code' => 'PO', 'name' => 'Purchase Order Prefix']],
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

        $this->menuItem(null, 'Dashboard', 'dashboard', 'bx-home-circle', 'dashboard.view', 10, $now);

        $this->menuGroup('Setup', 'bx-cog', 20, [
            ['Transaction Code', '#', 'setup.master.view'],
            ['Company', 'setup/companies', 'setup.master.view'],
            ['Site', 'setup/sites', 'setup.master.view'],
            ['Department', 'setup/departments', 'setup.master.view'],
            ['Warehouse', 'setup/warehouses', 'setup.master.view'],
            ['Location', '#', 'setup.master.view'],
            ['Country', '#', 'setup.master.view'],
            ['Province', '#', 'setup.master.view'],
            ['City', '#', 'setup.master.view'],
            ['Postal Code', '#', 'setup.master.view'],
            ['Unit of Measure', 'setup/uoms', 'setup.master.view'],
            ['UoM Conversion', '#', 'setup.master.view'],
            ['VAT', '#', 'setup.master.view'],
            ['Item VAT', '#', 'setup.master.view'],
            ['Address Master', '#', 'setup.master.view'],
        ], $now);

        $this->menuGroup('POS', 'bx-store-alt', 30, [
            ['Master', [
                ['POS Master', '#', 'pos.view'],
            ]],
            ['Transactions', [
                ['POS System', '#', 'pos.view'],
            ]],
        ], $now);

        $this->menuGroup('Sales', 'bx-cart', 40, [
            ['Master', [
                ['Customer Master', 'setup/customers', 'sales.customer.view'],
                ['Customer Terms', '#', 'sales.customer.view'],
                ['Customer Promo', '#', 'sales.customer.view'],
                ['Customer Address', '#', 'sales.customer.view'],
            ]],
            ['Transactions', [
                ['Sales Order', '#', 'sales.order.view'],
                ['Allocation Order', '#', 'sales.order.view'],
                ['Delivery Order', '#', 'sales.order.view'],
            ]],
            ['Sales Period Close', '#', 'sales.order.view'],
        ], $now);

        $this->menuGroup('Purchase', 'bx-shopping-bag', 50, [
            ['Master', [
                ['Supplier Master', 'setup/suppliers', 'purchase.supplier.view'],
                ['Supplier Terms', '#', 'purchase.supplier.view'],
                ['Supplier Promo', '#', 'purchase.supplier.view'],
                ['Supplier Address', '#', 'purchase.supplier.view'],
            ]],
            ['Transactions', [
                ['Purchase Order', '#', 'purchase.po.view'],
                ['Purchase Intransit', '#', 'purchase.po.view'],
                ['Inventory Purchase Receipt', '#', 'purchase.po.view'],
                ['Cost Purchase Receipt', '#', 'purchase.po.view'],
            ]],
            ['Purchase Period Close', '#', 'purchase.po.view'],
        ], $now);

        $this->menuGroup('Inventory', 'bx-package', 60, [
            ['Master', [
                ['Item Master', 'setup/items', 'inventory.item.view'],
                ['Item UoM Conversion', '#', 'inventory.item.view'],
                ['Batch Master', '#', 'inventory.item.view'],
            ]],
            ['Transactions', [
                ['Inventory In Out', '#', 'inventory.stock.view'],
                ['Inventory Transfer', '#', 'inventory.stock.view'],
                ['Inventory Stock Opname', '#', 'inventory.stock.view'],
            ]],
            ['Inventory Period Close', '#', 'inventory.stock.view'],
        ], $now);

        $this->menuGroup('Planning', 'bx-calendar', 70, [
            ['Forecast', '#', 'planning.view'],
            ['Planned Released', '#', 'planning.view'],
            ['MPS', '#', 'planning.view'],
            ['MRP', '#', 'planning.view'],
        ], $now);

        $this->menuGroup('Production', 'bx-factory', 80, [
            ['Master', [
                ['BOM', '#', 'production.view'],
                ['Work Center', '#', 'production.view'],
                ['Routing', '#', 'production.view'],
            ]],
            ['Transactions', [
                ['Work Order', '#', 'production.view'],
                ['Allocate Work Order', '#', 'production.view'],
                ['Work Order In', '#', 'production.view'],
                ['Work Order Out', '#', 'production.view'],
                ['Work Order In Out', '#', 'production.view'],
                ['Work Order Labor', '#', 'production.view'],
            ]],
            ['Production Period Close', '#', 'production.view'],
        ], $now);

        $this->menuGroup('Accounts Payable', 'bx-receipt', 90, [
            ['Master', '#', 'finance.ap.view'],
            ['Transactions', [
                ['Manual A/P Invoice', '#', 'finance.ap.view'],
                ['Purchase Invoice', '#', 'finance.ap.view'],
                ['Inventory Purchase Invoice', '#', 'finance.ap.view'],
                ['Advanced A/P Invoice', '#', 'finance.ap.view'],
                ['Payment Invoice', '#', 'finance.ap.view'],
            ]],
            ['A/P Period Close', '#', 'finance.ap.view'],
        ], $now);

        $this->menuGroup('Accounts Receivable', 'bx-credit-card', 100, [
            ['Master', '#', 'finance.ar.view'],
            ['Transactions', [
                ['Manual A/R Invoice', '#', 'finance.ar.view'],
                ['Proforma Invoice', '#', 'finance.ar.view'],
                ['Sales Invoice', '#', 'finance.ar.view'],
                ['Inventory Sales Invoice', '#', 'finance.ar.view'],
                ['Advanced A/R Receipt', '#', 'finance.ar.view'],
                ['Payment Receipt', '#', 'finance.ar.view'],
            ]],
            ['A/R Period Close', '#', 'finance.ar.view'],
        ], $now);

        $this->menuGroup('Costing', 'bx-calculator', 110, [
            ['Master', [
                ['Cost Type', '#', 'costing.view'],
                ['Item Cost', '#', 'costing.view'],
            ]],
            ['Transactions', [
                ['Calculate Cost', '#', 'costing.view'],
            ]],
        ], $now);

        $this->menuGroup('Cash Bank', 'bx-wallet', 120, [
            ['Master', [
                ['Cash Bank ID', '#', 'cashbank.view'],
                ['Currency', '#', 'cashbank.view'],
                ['Employee ID', '#', 'cashbank.view'],
                ['Rate Master', '#', 'cashbank.view'],
            ]],
            ['Transactions', [
                ['Cash Entry', '#', 'cashbank.view'],
                ['Bank Entry', '#', 'cashbank.view'],
                ['Bank Reconcile', '#', 'cashbank.view'],
            ]],
        ], $now);

        $this->menuGroup('GL', 'bx-book', 130, [
            ['Master', [
                ['GL Book', '#', 'finance.gl.view'],
                ['GL Column', '#', 'finance.gl.view'],
                ['Account No.', '#', 'finance.gl.view'],
                ['Chart of Account', '#', 'finance.gl.view'],
                ['Recurring', '#', 'finance.gl.view'],
            ]],
            ['Transactions', [
                ['GL Entry', '#', 'finance.gl.view'],
                ['Recurring Posting', '#', 'finance.gl.view'],
            ]],
            ['GL Period Close', '#', 'finance.gl.view'],
        ], $now);

        $this->menuGroup('FA', 'bx-building-house', 140, [
            ['Master', [
                ['Asset ID', '#', 'fixedasset.view'],
            ]],
            ['Transactions', [
                ['Asset Depreciation', '#', 'fixedasset.view'],
            ]],
            ['Asset Period Close', '#', 'fixedasset.view'],
        ], $now);

        $this->menuItem(null, 'AI Documents', 'ai-documents', 'bx-scan', 'ai.document.review', 150, $now);
    }

    /**
     * @param array<int, array<int, mixed>> $children
     */
    private function menuGroup(string $label, string $icon, int $sort, array $children, string $now): int
    {
        $parentId = $this->menuItem(null, $label, '#', $icon, null, $sort, $now);
        $childSort = 10;

        foreach ($children as $child) {
            if (isset($child[1]) && is_array($child[1])) {
                $sectionId = $this->menuItem($parentId, $child[0], '#', null, null, $childSort, $now);
                $leafSort = 10;

                foreach ($child[1] as $leaf) {
                    $this->menuItem($sectionId, $leaf[0], $leaf[1] ?? '#', null, $leaf[2] ?? null, $leafSort, $now);
                    $leafSort += 10;
                }
            } else {
                $this->menuItem($parentId, $child[0], $child[1] ?? '#', null, $child[2] ?? null, $childSort, $now);
            }

            $childSort += 10;
        }

        return $parentId;
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
