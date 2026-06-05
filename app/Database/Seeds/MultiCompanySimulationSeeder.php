<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;
use Config\Database;

class MultiCompanySimulationSeeder extends Seeder
{
    private string $now;

    /**
     * @var list<array{code:string,name:string,legal:string,currency:string,sites:list<array{code:string,name:string}>}>
     */
    private array $companies = [
        [
            'code' => 'SIM-A',
            'name' => 'Simulasi Alpha Trading',
            'legal' => 'PT Simulasi Alpha Trading',
            'currency' => 'IDR',
            'sites' => [
                ['code' => 'HO', 'name' => 'Alpha Head Office'],
                ['code' => 'JKT', 'name' => 'Alpha Jakarta Branch'],
                ['code' => 'SBY', 'name' => 'Alpha Surabaya Branch'],
            ],
        ],
        [
            'code' => 'SIM-B',
            'name' => 'Simulasi Beta Manufacturing',
            'legal' => 'PT Simulasi Beta Manufacturing',
            'currency' => 'IDR',
            'sites' => [
                ['code' => 'HO', 'name' => 'Beta Head Office'],
                ['code' => 'BDG', 'name' => 'Beta Bandung Plant'],
                ['code' => 'SMG', 'name' => 'Beta Semarang Plant'],
            ],
        ],
        [
            'code' => 'SIM-C',
            'name' => 'Simulasi Cakra Distribution',
            'legal' => 'PT Simulasi Cakra Distribution',
            'currency' => 'IDR',
            'sites' => [
                ['code' => 'HO', 'name' => 'Cakra Head Office'],
                ['code' => 'DPS', 'name' => 'Cakra Denpasar Branch'],
                ['code' => 'MKS', 'name' => 'Cakra Makassar Branch'],
            ],
        ],
        [
            'code' => 'SIM-D',
            'name' => 'Simulasi Delta Retail',
            'legal' => 'PT Simulasi Delta Retail',
            'currency' => 'IDR',
            'sites' => [
                ['code' => 'HO', 'name' => 'Delta Head Office'],
                ['code' => 'TGR', 'name' => 'Delta Tangerang Store'],
                ['code' => 'BGR', 'name' => 'Delta Bogor Store'],
            ],
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $userCompanyCodes = [];

    /**
     * @var array<string, list<array{company:string,site:string}>>
     */
    private array $userSiteCodes = [];

    public function run(): void
    {
        $this->now = date('Y-m-d H:i:s');

        foreach ($this->companies as $company) {
            $companyId = $this->seedCompany($company);
            $this->seedCompanyMasters($companyId);

            foreach ($company['sites'] as $site) {
                $siteId = $this->seedSite($companyId, $company, $site);
                $warehouseIds = $this->seedWarehousesAndLocations($companyId, $siteId, $company['code'], $site['code']);
                $this->seedPartners($companyId, $siteId, $company['code'], $site['code']);
                $this->seedItemsAndStock($companyId, $siteId, $company['code'], $site['code'], $warehouseIds);
            }
        }

        $this->seedUsersAndAccess();
    }

    /**
     * @param array{code:string,name:string,legal:string,currency:string} $company
     */
    private function seedCompany(array $company): int
    {
        return $this->upsert('companies', ['code' => $company['code']], [
            'code' => $company['code'],
            'name' => $company['name'],
            'legal_name' => $company['legal'],
            'tax_number' => 'SIM-' . $company['code'] . '-NPWP',
            'base_currency' => $company['currency'],
            'address' => 'Demo multi-company address for ' . $company['name'],
            'is_active' => 1,
        ]);
    }

    /**
     * @param array{code:string,name:string} $company
     * @param array{code:string,name:string} $site
     */
    private function seedSite(int $companyId, array $company, array $site): int
    {
        return $this->upsert('sites', ['company_id' => $companyId, 'code' => $site['code']], [
            'company_id' => $companyId,
            'code' => $site['code'],
            'name' => $site['name'],
            'address' => $site['name'] . ', generated simulation site for ' . $company['code'],
            'is_active' => 1,
        ]);
    }

    private function seedCompanyMasters(int $companyId): void
    {
        foreach ([['PCS', 'Pieces'], ['BOX', 'Box'], ['CTN', 'Carton'], ['PACK', 'Pack'], ['KG', 'Kilogram'], ['LTR', 'Liter'], ['MTR', 'Meter'], ['HOUR', 'Hour']] as [$code, $name]) {
            $this->upsert('uoms', ['company_id' => $companyId, 'code' => $code], [
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'description' => 'Simulation UoM ' . $name,
                'is_active' => 1,
            ]);
        }

        foreach ([['VAT00', 'VAT 0%', 0], ['VAT11', 'VAT 11%', 11], ['VAT12', 'VAT 12%', 12]] as [$code, $name, $rate]) {
            $this->upsert('vat_rates', ['company_id' => $companyId, 'code' => $code], [
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'rate' => $rate,
                'description' => 'Simulation ' . $name,
                'is_active' => 1,
            ]);
        }

        foreach ([['SO', 'Sales Order'], ['PO', 'Purchase Order'], ['WO', 'Work Order'], ['DO', 'Delivery Order'], ['INV', 'Invoice']] as [$code, $name]) {
            $this->upsert('transaction_codes', ['company_id' => $companyId, 'code' => $code], [
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'description' => 'Simulation transaction code ' . $name,
                'is_active' => 1,
            ]);
            $this->upsert('prefix_codes', ['company_id' => $companyId, 'code' => $code], [
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name . ' Prefix',
                'description' => 'Simulation prefix for ' . $name,
                'is_active' => 1,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function seedWarehousesAndLocations(int $companyId, int $siteId, string $companyCode, string $siteCode): array
    {
        $warehouseIds = [];
        $warehouses = [
            ['MAIN', 'Main Warehouse'],
            ['RM', 'Raw Material Warehouse'],
            ['FG', 'Finished Goods Warehouse'],
            ['QC', 'Quality Control Warehouse'],
        ];

        foreach ($warehouses as [$code, $name]) {
            $warehouseId = $this->upsert('warehouses', ['company_id' => $companyId, 'site_id' => $siteId, 'code' => $code], [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'code' => $code,
                'name' => $companyCode . ' ' . $siteCode . ' ' . $name,
                'description' => 'Simulation warehouse',
                'is_active' => 1,
            ]);
            $warehouseIds[] = $warehouseId;

            foreach ([['A01', 'Rack A01'], ['A02', 'Rack A02'], ['B01', 'Rack B01'], ['B02', 'Rack B02'], ['STG', 'Staging Area']] as [$locCode, $locName]) {
                $fullLocCode = $code . '-' . $locCode;
                $this->upsert('locations', ['company_id' => $companyId, 'site_id' => $siteId, 'code' => $fullLocCode], [
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                    'warehouse_id' => $warehouseId,
                    'code' => $fullLocCode,
                    'name' => $companyCode . ' ' . $siteCode . ' ' . $code . ' ' . $locName,
                    'description' => 'Simulation location',
                    'is_active' => 1,
                ]);
            }
        }

        return $warehouseIds;
    }

    private function seedPartners(int $companyId, int $siteId, string $companyCode, string $siteCode): void
    {
        $companyShortCode = substr($companyCode, -1);
        $partnerPrefix = $companyShortCode . '-' . $siteCode;

        foreach ([['COD', 'Cash On Delivery', 0], ['NET14', 'Net 14 Days', 14], ['NET30', 'Net 30 Days', 30]] as [$code, $name, $days]) {
            foreach (['customer_terms', 'supplier_terms'] as $table) {
                $this->upsert($table, ['company_id' => $companyId, 'site_id' => $siteId, 'terms_code' => $code], [
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                    'company' => $companyCode,
                    'site' => $siteCode,
                    'terms_code' => $code,
                    'terms_name' => $name,
                    'terms_days' => $days,
                    'is_active' => 1,
                ]);
            }
        }

        for ($i = 1; $i <= 6; $i++) {
            $suffix = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $customerCode = $partnerPrefix . '-C' . $suffix;
            $supplierCode = $partnerPrefix . '-S' . $suffix;

            $this->upsert('customers', ['company_id' => $companyId, 'site_id' => $siteId, 'code' => $customerCode], [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'company' => $companyCode,
                'site' => $siteCode,
                'code' => $customerCode,
                'name' => 'Customer ' . $companyCode . ' ' . $siteCode . ' ' . $suffix,
                'customer' => $customerCode,
                'customern' => 'Customer ' . $companyCode . ' ' . $siteCode . ' ' . $suffix,
                'customerr' => 'Customer Ref ' . $suffix,
                'contactnar' => 'Customer Contact ' . $suffix,
                'terms_code' => $i % 2 === 0 ? 'NET30' : 'NET14',
                'currency_code' => 'IDR',
                'tax_number' => 'SIM-CUST-' . $suffix,
                'address' => 'Jl. Customer Simulation ' . $suffix,
                'phone' => '021-77' . $suffix,
                'email' => strtolower($customerCode) . '@simulation.test',
                'shipwhs' => 'MAIN',
                'taxcode' => 'PKP',
                'taxnumber' => 'SIM-CUST-' . $suffix,
                'vat' => 'VAT11',
                'limitamound' => 100000000 + ($i * 25000000),
                'terms' => $i % 2 === 0 ? 'NET30' : 'NET14',
                'limitdays' => $i % 2 === 0 ? 30 : 14,
                'salescode' => 'SLS-' . $siteCode,
                'salesname' => 'Sales ' . $siteCode,
                'active' => 1,
                'is_active' => 1,
            ]);

            $this->upsert('suppliers', ['company_id' => $companyId, 'site_id' => $siteId, 'code' => $supplierCode], [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'company' => $companyCode,
                'site' => $siteCode,
                'code' => $supplierCode,
                'name' => 'Supplier ' . $companyCode . ' ' . $siteCode . ' ' . $suffix,
                'supplier' => $supplierCode,
                'supplierna' => 'Supplier ' . $companyCode . ' ' . $siteCode . ' ' . $suffix,
                'supplierref' => 'Supplier Ref ' . $suffix,
                'contactnar' => 'Supplier Contact ' . $suffix,
                'terms_code' => $i % 2 === 0 ? 'NET30' : 'NET14',
                'currency_code' => 'IDR',
                'tax_number' => 'SIM-SUP-' . $suffix,
                'address' => 'Jl. Supplier Simulation ' . $suffix,
                'phone' => '021-88' . $suffix,
                'email' => strtolower($supplierCode) . '@simulation.test',
                'taxcode' => 'PKP',
                'taxnumber' => 'SIM-SUP-' . $suffix,
                'vat' => 'VAT11',
                'limitamound' => 125000000 + ($i * 30000000),
                'terms' => $i % 2 === 0 ? 'NET30' : 'NET14',
                'limitdays' => $i % 2 === 0 ? 30 : 14,
                'employee' => 'EMP-' . $siteCode,
                'purchasing' => 'PUR-' . $siteCode,
                'active' => 1,
                'is_active' => 1,
            ]);
        }
    }

    /**
     * @param list<int> $warehouseIds
     */
    private function seedItemsAndStock(int $companyId, int $siteId, string $companyCode, string $siteCode, array $warehouseIds): void
    {
        $companyShortCode = substr($companyCode, -1);
        $uoms = ['PCS', 'BOX', 'CTN', 'PACK', 'KG', 'LTR', 'MTR'];
        $groups = ['RM', 'FG', 'PKG', 'SP', 'CONS'];
        $types = ['STK', 'AST', 'SRV'];

        for ($i = 1; $i <= 30; $i++) {
            $suffix = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $itemCode = $companyShortCode . '-' . $siteCode . '-I' . $suffix;
            $uom = $uoms[$i % count($uoms)];
            $price = 1500 + ($i * 625);

            $itemId = $this->upsert('items', ['company_id' => $companyId, 'site_id' => $siteId, 'item_code' => $itemCode], [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'company' => $companyCode,
                'site' => $siteCode,
                'code' => $itemCode,
                'name' => 'Simulation Item ' . $companyCode . ' ' . $siteCode . ' ' . $suffix,
                'item_code' => $itemCode,
                'item_name' => 'Simulation Item ' . $companyCode . ' ' . $siteCode . ' ' . $suffix,
                'item_coded' => $itemCode . '-D',
                'item_named' => 'Simulation Item Detail ' . $suffix,
                'shelf_life' => ($i % 12) * 30,
                'stockuom' => $uom,
                'purchaseuom' => $uom,
                'sellinguom' => $uom,
                'stockwhs' => $i % 5 === 0 ? 'FG' : 'MAIN',
                'item_price' => $price,
                'purchasep' => $price * 0.9,
                'sellingprice' => $price * 1.3,
                'vat' => $i % 7 === 0 ? 'VAT00' : 'VAT11',
                'item_group' => $groups[$i % count($groups)],
                'item_class' => chr(65 + ($i % 3)),
                'item_type' => $types[$i % count($types)],
                'active' => 1,
                'is_active' => 1,
                'created_by' => 'simulation',
                'updated_by' => 'simulation',
            ]);

            if ($i > 15 || $warehouseIds === []) {
                continue;
            }

            $warehouseId = $warehouseIds[$i % count($warehouseIds)];
            $location = $this->db->table('locations')
                ->where('company_id', $companyId)
                ->where('site_id', $siteId)
                ->where('warehouse_id', $warehouseId)
                ->orderBy('code', 'ASC')
                ->get(1)
                ->getRowArray();
            $qty = 25 + ($i * 3);
            $reserved = $i % 4 === 0 ? 4 : 0;
            $this->upsert('inventory_stock_balances', [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'warehouse_id' => $warehouseId,
                'location_id' => $location['id'] ?? null,
                'item_code' => $itemCode,
            ], [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'warehouse_id' => $warehouseId,
                'location_id' => $location['id'] ?? null,
                'item_id' => $itemId,
                'item_code' => $itemCode,
                'uom_code' => $uom,
                'qty_on_hand' => $qty,
                'qty_reserved' => $reserved,
                'qty_available' => $qty - $reserved,
                'avg_cost' => $price,
                'stock_value' => $qty * $price,
            ]);
        }
    }

    private function seedUsersAndAccess(): void
    {
        $users = [
            ['admin@pena-erp.local', 'admin', 'superadmin', 'Admin123!', 'all'],
            ['company-admin@pena-erp.local', 'company_admin_demo', 'company_admin', 'Admin123!', ['SIM-A', 'SIM-B', 'SIM-C', 'SIM-D']],
            ['sales-alpha@pena-erp.local', 'sales_alpha', 'sales', 'Admin123!', ['SIM-A']],
            ['purchase-beta@pena-erp.local', 'purchase_beta', 'purchase', 'Admin123!', ['SIM-B']],
            ['inventory-cakra@pena-erp.local', 'inventory_cakra', 'inventory', 'Admin123!', ['SIM-C']],
            ['production-beta@pena-erp.local', 'production_beta', 'production', 'Admin123!', ['SIM-B']],
            ['finance-group@pena-erp.local', 'finance_group', 'finance', 'Admin123!', ['SIM-A', 'SIM-B', 'SIM-C', 'SIM-D']],
            ['viewer-delta@pena-erp.local', 'viewer_delta', 'viewer', 'Admin123!', ['SIM-D']],
        ];

        foreach ($users as [$email, $username, $group, $password, $companyScope]) {
            $userId = $this->seedUser($email, $username, $group, $password);
            $companyCodes = $companyScope === 'all'
                ? array_map(static fn (array $company): string => $company['code'], $this->companies)
                : $companyScope;

            $this->assignAccess((int) $userId, $companyCodes);
        }
    }

    /**
     * @param list<string> $companyCodes
     */
    private function assignAccess(int $userId, array $companyCodes): void
    {
        $companyRows = [];
        foreach ($companyCodes as $companyCode) {
            $company = $this->db->table('companies')->where('code', $companyCode)->get()->getRowArray();
            if ($company === null) {
                continue;
            }

            $companyRows[] = $company;
        }

        if ($companyRows === []) {
            return;
        }

        $companyIds = array_map(static fn (array $company): int => (int) $company['id'], $companyRows);
        $siteRows = $this->db->table('sites')
            ->whereIn('company_id', $companyIds)
            ->orderBy('company_id', 'ASC')
            ->orderBy('code', 'ASC')
            ->get()
            ->getResultArray();
        $siteIds = array_map(static fn (array $site): int => (int) $site['id'], $siteRows);

        $hasDefaultCompanyOutsideScope = $this->db->table('user_company_access')
            ->where('user_id', $userId)
            ->where('is_default', 1)
            ->whereNotIn('company_id', $companyIds)
            ->countAllResults() > 0;
        $hasDefaultSiteOutsideScope = $siteIds !== [] && $this->db->table('user_site_access')
            ->where('user_id', $userId)
            ->where('is_default', 1)
            ->whereNotIn('site_id', $siteIds)
            ->countAllResults() > 0;

        $isFirstCompany = ! $hasDefaultCompanyOutsideScope;
        foreach ($companyRows as $company) {
            $companyId = (int) $company['id'];
            $this->upsert('user_company_access', ['user_id' => $userId, 'company_id' => $companyId], [
                'user_id' => $userId,
                'company_id' => $companyId,
                'site_id' => null,
                'is_default' => $isFirstCompany ? 1 : 0,
            ]);

            $sites = $this->db->table('sites')
                ->where('company_id', $companyId)
                ->orderBy('code', 'ASC')
                ->get()
                ->getResultArray();
            $isFirstSite = ! $hasDefaultSiteOutsideScope && $isFirstCompany;
            foreach ($sites as $site) {
                $this->upsert('user_site_access', ['user_id' => $userId, 'company_id' => $companyId, 'site_id' => (int) $site['id']], [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'site_id' => (int) $site['id'],
                    'is_default' => $isFirstSite ? 1 : 0,
                ]);
                $isFirstSite = false;
            }

            $isFirstCompany = false;
        }
    }

    private function seedUser(string $email, string $username, string $group, string $password): int
    {
        $provider = auth()->getProvider();
        $user = $provider->findByCredentials(['email' => $email]);

        if ($user === null) {
            $entity = new User([
                'username' => $username,
                'email' => $email,
                'password' => $password,
            ]);
            $provider->save($entity);
            $user = $provider->findById($provider->getInsertID());
            $user->activate();
        }

        $this->db->table('auth_groups_users')
            ->where('user_id', (int) $user->id)
            ->where('group', $group)
            ->delete();
        $user->addGroup($group);

        return (int) $user->id;
    }

    private function upsert(string $table, array $where, array $data): int
    {
        if (! $this->db->tableExists($table)) {
            return 0;
        }

        $data = $this->filterExistingFields($table, $data);
        $where = $this->filterExistingFields($table, $where);
        $existing = $this->db->table($table)->where($where)->get()->getRowArray();
        $data['updated_at'] = $this->now;

        if ($existing !== null) {
            $this->db->table($table)->where('id', $existing['id'])->update($data);

            return (int) $existing['id'];
        }

        $data['created_at'] = $this->now;
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    private function filterExistingFields(string $table, array $data): array
    {
        $db = Database::connect();

        return array_filter(
            $data,
            static fn (string $field): bool => $db->fieldExists($field, $table),
            ARRAY_FILTER_USE_KEY
        );
    }
}
