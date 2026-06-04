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

        $this->menuItem(null, 'Dashboard', 'dashboard', 'bx-home-circle', 'dashboard.view', 10, $now);

        $this->menuGroup('Setup', 'bx-cog', 20, [
            ['Transaction Code', 'setup/transaction-codes', 'setup.master.view'],
            ['Company', 'setup/companies', 'setup.master.view'],
            ['Site', 'setup/sites', 'setup.master.view'],
            ['Department', 'setup/departments', 'setup.master.view'],
            ['Warehouse', 'setup/warehouses', 'setup.master.view'],
            ['Location', 'setup/locations', 'setup.master.view'],
            ['Country', 'setup/countries', 'setup.master.view'],
            ['Province', 'setup/provinces', 'setup.master.view'],
            ['City', 'setup/cities', 'setup.master.view'],
            ['Postal Code', 'setup/postal-codes', 'setup.master.view'],
            ['Unit of Measure', 'setup/uoms', 'setup.master.view'],
            ['UoM Conversion', 'setup/uom-conversions', 'setup.master.view'],
            ['VAT', 'setup/vat', 'setup.master.view'],
            ['Item VAT', 'setup/item-vat', 'setup.master.view'],
            ['Address Master', 'setup/address-master', 'setup.master.view'],
        ], $now);

        $this->menuGroup('POS', 'bx-store-alt', 30, [
            ['POS Master', $this->placeholderRoute('POS Master'), 'pos.view'],
            ['POS System', $this->placeholderRoute('POS System'), 'pos.view'],
        ], $now);

        $this->menuGroup('Sales', 'bx-cart', 40, [
            ['Customer Master', 'setup/customers', 'sales.customer.view'],
            ['Customer Terms', 'setup/customer-terms', 'sales.customer.view'],
            ['Customer Promo', 'setup/customer-promos', 'sales.customer.view'],
            ['Customer Address', $this->placeholderRoute('Customer Address'), 'sales.customer.view'],
            ['Sales Order', 'sales/orders', 'sales.order.view'],
            ['Allocation Order', $this->placeholderRoute('Allocation Order'), 'sales.order.view'],
            ['Delivery Order', 'sales/deliveries', 'sales.order.view'],
            ['Sales Period Close', $this->placeholderRoute('Sales Period Close'), 'sales.order.view'],
        ], $now);

        $this->menuGroup('Purchase', 'bx-shopping-bag', 50, [
            ['Supplier Master', 'setup/suppliers', 'purchase.supplier.view'],
            ['Supplier Terms', 'setup/supplier-terms', 'purchase.supplier.view'],
            ['Supplier Promo', 'setup/supplier-promos', 'purchase.supplier.view'],
            ['Supplier Address', $this->placeholderRoute('Supplier Address'), 'purchase.supplier.view'],
            ['Purchase Order', 'purchase/orders', 'purchase.po.view'],
            ['Purchase Intransit', $this->placeholderRoute('Purchase Intransit'), 'purchase.po.view'],
            ['Inventory Purchase Receipt', $this->placeholderRoute('Inventory Purchase Receipt'), 'purchase.po.view'],
            ['Cost Purchase Receipt', $this->placeholderRoute('Cost Purchase Receipt'), 'purchase.po.view'],
            ['Purchase Period Close', $this->placeholderRoute('Purchase Period Close'), 'purchase.po.view'],
        ], $now);

        $this->menuGroup('Inventory', 'bx-package', 60, [
            ['Item Master', 'setup/items', 'inventory.item.view'],
            ['Item UoM Conversion', $this->placeholderRoute('Item UoM Conversion'), 'inventory.item.view'],
            ['Batch Master', $this->placeholderRoute('Batch Master'), 'inventory.item.view'],
            ['Inventory In Out', $this->placeholderRoute('Inventory In Out'), 'inventory.stock.view'],
            ['Inventory Transfer', $this->placeholderRoute('Inventory Transfer'), 'inventory.stock.view'],
            ['Inventory Stock Opname', $this->placeholderRoute('Inventory Stock Opname'), 'inventory.stock.view'],
            ['Inventory Period Close', $this->placeholderRoute('Inventory Period Close'), 'inventory.stock.view'],
        ], $now);

        $this->menuGroup('Planning', 'bx-calendar', 70, [
            ['Forecast', $this->placeholderRoute('Forecast'), 'planning.view'],
            ['Planned Released', $this->placeholderRoute('Planned Released'), 'planning.view'],
            ['MPS', $this->placeholderRoute('MPS'), 'planning.view'],
            ['MRP', $this->placeholderRoute('MRP'), 'planning.view'],
        ], $now);

        $this->menuGroup('Production', 'bx-factory', 80, [
            ['BOM', 'production/boms', 'production.view'],
            ['Work Center', 'production/work-centers', 'production.view'],
            ['Routing', 'production/routings', 'production.view'],
            ['Work Order', 'production/work-orders', 'production.view'],
            ['Allocate Work Order', 'production/work-orders', 'production.view'],
            ['Work Order In', $this->placeholderRoute('Work Order In'), 'production.view'],
            ['Work Order Out', 'production/work-orders', 'production.view'],
            ['Work Order In Out', $this->placeholderRoute('Work Order In Out'), 'production.view'],
            ['Work Order Labor', $this->placeholderRoute('Work Order Labor'), 'production.view'],
            ['Production Period Close', $this->placeholderRoute('Production Period Close'), 'production.view'],
        ], $now);

        $this->menuGroup('Accounts Payable', 'bx-receipt', 90, [
            ['Accounts Payable', $this->placeholderRoute('Accounts Payable'), 'finance.ap.view'],
            ['Manual A/P Invoice', $this->placeholderRoute('Manual A/P Invoice'), 'finance.ap.view'],
            ['Purchase Invoice', $this->placeholderRoute('Purchase Invoice'), 'finance.ap.view'],
            ['Inventory Purchase Invoice', $this->placeholderRoute('Inventory Purchase Invoice'), 'finance.ap.view'],
            ['Advanced A/P Invoice', $this->placeholderRoute('Advanced A/P Invoice'), 'finance.ap.view'],
            ['Payment Invoice', $this->placeholderRoute('Payment Invoice'), 'finance.ap.view'],
            ['A/P Period Close', $this->placeholderRoute('A/P Period Close'), 'finance.ap.view'],
        ], $now);

        $this->menuGroup('Accounts Receivable', 'bx-credit-card', 100, [
            ['Accounts Receivable', $this->placeholderRoute('Accounts Receivable'), 'finance.ar.view'],
            ['Manual A/R Invoice', $this->placeholderRoute('Manual A/R Invoice'), 'finance.ar.view'],
            ['Proforma Invoice', $this->placeholderRoute('Proforma Invoice'), 'finance.ar.view'],
            ['Sales Invoice', 'ar/sales-invoices', 'finance.ar.view'],
            ['Inventory Sales Invoice', $this->placeholderRoute('Inventory Sales Invoice'), 'finance.ar.view'],
            ['Advanced A/R Receipt', $this->placeholderRoute('Advanced A/R Receipt'), 'finance.ar.view'],
            ['Payment Receipt', $this->placeholderRoute('Payment Receipt'), 'finance.ar.view'],
            ['A/R Period Close', $this->placeholderRoute('A/R Period Close'), 'finance.ar.view'],
        ], $now);

        $this->menuGroup('Costing', 'bx-calculator', 110, [
            ['Cost Type', $this->placeholderRoute('Cost Type'), 'costing.view'],
            ['Item Cost', $this->placeholderRoute('Item Cost'), 'costing.view'],
            ['Calculate Cost', $this->placeholderRoute('Calculate Cost'), 'costing.view'],
        ], $now);

        $this->menuGroup('Cash Bank', 'bx-wallet', 120, [
            ['Cash Bank ID', $this->placeholderRoute('Cash Bank ID'), 'cashbank.view'],
            ['Currency', $this->placeholderRoute('Currency'), 'cashbank.view'],
            ['Employee ID', $this->placeholderRoute('Employee ID'), 'cashbank.view'],
            ['Rate Master', $this->placeholderRoute('Rate Master'), 'cashbank.view'],
            ['Cash Entry', $this->placeholderRoute('Cash Entry'), 'cashbank.view'],
            ['Bank Entry', $this->placeholderRoute('Bank Entry'), 'cashbank.view'],
            ['Bank Reconcile', $this->placeholderRoute('Bank Reconcile'), 'cashbank.view'],
        ], $now);

        $this->menuGroup('GL', 'bx-book', 130, [
            ['GL Book', $this->placeholderRoute('GL Book'), 'finance.gl.view'],
            ['GL Column', $this->placeholderRoute('GL Column'), 'finance.gl.view'],
            ['Account No.', $this->placeholderRoute('Account No.'), 'finance.gl.view'],
            ['Chart of Account', $this->placeholderRoute('Chart of Account'), 'finance.gl.view'],
            ['Recurring', $this->placeholderRoute('Recurring'), 'finance.gl.view'],
            ['GL Entry', $this->placeholderRoute('GL Entry'), 'finance.gl.view'],
            ['Recurring Posting', $this->placeholderRoute('Recurring Posting'), 'finance.gl.view'],
            ['GL Period Close', $this->placeholderRoute('GL Period Close'), 'finance.gl.view'],
        ], $now);

        $this->menuGroup('FA', 'bx-building-house', 140, [
            ['Asset ID', $this->placeholderRoute('Asset ID'), 'fixedasset.view'],
            ['Asset Depreciation', $this->placeholderRoute('Asset Depreciation'), 'fixedasset.view'],
            ['Asset Period Close', $this->placeholderRoute('Asset Period Close'), 'fixedasset.view'],
        ], $now);

        $this->menuItem(null, 'AI Documents', 'ai-documents', 'bx-scan', 'ai.document.review', 150, $now);
    }

    private function placeholderRoute(string $label): string
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $label));

        return 'modules/' . trim($slug, '-');
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
