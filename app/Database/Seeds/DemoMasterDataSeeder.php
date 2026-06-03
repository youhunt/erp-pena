<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Database;

class DemoMasterDataSeeder extends Seeder
{
    private int $companyId = 1;
    private int $siteId = 1;
    private string $companyCode = 'PENA';
    private string $siteCode = 'HO';
    private string $now;

    public function run(): void
    {
        $this->now = date('Y-m-d H:i:s');
        $this->ensureCompanyAndSite();
        $this->seedUoms();
        $this->seedWarehousesAndLocations();
        $this->seedVat();
        $this->seedPostalCodes();
        $this->seedAddressTemplates();
        $this->seedCustomers();
        $this->seedSuppliers();
        $this->seedItems(150);
        $this->seedOpeningStock();
    }

    private function ensureCompanyAndSite(): void
    {
        $company = $this->db->table('companies')->where('code', $this->companyCode)->get()->getRowArray();
        if ($company === null) {
            $this->db->table('companies')->insert([
                'code' => $this->companyCode,
                'name' => 'Pena Inovasi Sistem',
                'legal_name' => 'PT Pena Inovasi Sistem',
                'base_currency' => 'IDR',
                'address' => 'Demo Address',
                'is_active' => 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
            $this->companyId = (int) $this->db->insertID();
        } else {
            $this->companyId = (int) $company['id'];
        }

        $site = $this->db->table('sites')
            ->where('company_id', $this->companyId)
            ->where('code', $this->siteCode)
            ->get()
            ->getRowArray();

        if ($site === null) {
            $this->db->table('sites')->insert([
                'company_id' => $this->companyId,
                'code' => $this->siteCode,
                'name' => 'Head Office',
                'address' => 'Demo Head Office',
                'is_active' => 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
            $this->siteId = (int) $this->db->insertID();
        } else {
            $this->siteId = (int) $site['id'];
        }
    }

    private function seedUoms(): void
    {
        $uoms = [
            ['PCS', 'Pieces'], ['BOX', 'Box'], ['CTN', 'Carton'], ['PACK', 'Pack'], ['SET', 'Set'],
            ['ROLL', 'Roll'], ['RIM', 'Rim'], ['KG', 'Kilogram'], ['GR', 'Gram'], ['LTR', 'Liter'],
            ['ML', 'Milliliter'], ['MTR', 'Meter'], ['CM', 'Centimeter'], ['MM', 'Millimeter'], ['DOZ', 'Dozen'],
        ];

        foreach ($uoms as [$code, $name]) {
            $this->upsertByCode('uoms', [
                'company_id' => $this->companyId,
                'code' => $code,
                'name' => $name,
                'description' => 'Demo UoM ' . $name,
                'is_active' => 1,
            ], ['company_id' => $this->companyId, 'code' => $code]);
        }
    }

    private function seedWarehousesAndLocations(): void
    {
        $warehouses = [
            ['MAIN', 'Main Warehouse'],
            ['RM', 'Raw Material Warehouse'],
            ['FG', 'Finished Goods Warehouse'],
            ['QC', 'Quality Control Warehouse'],
            ['RET', 'Return Warehouse'],
        ];

        foreach ($warehouses as [$code, $name]) {
            $this->upsertByCode('warehouses', [
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'code' => $code,
                'name' => $name,
                'description' => 'Demo ' . $name,
                'is_active' => 1,
            ], ['company_id' => $this->companyId, 'site_id' => $this->siteId, 'code' => $code]);
        }

        $mainWhs = $this->db->table('warehouses')
            ->where('company_id', $this->companyId)
            ->where('site_id', $this->siteId)
            ->where('code', 'MAIN')
            ->get()
            ->getRowArray();

        $warehouseId = (int) ($mainWhs['id'] ?? 0);
        foreach ([['A01', 'Rack A01'], ['A02', 'Rack A02'], ['B01', 'Rack B01'], ['B02', 'Rack B02'], ['QC01', 'QC Area']] as [$code, $name]) {
            $this->upsertByCode('locations', [
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                'code' => $code,
                'name' => $name,
                'description' => 'Demo location ' . $name,
                'is_active' => 1,
            ], ['company_id' => $this->companyId, 'site_id' => $this->siteId, 'code' => $code]);
        }
    }

    private function seedVat(): void
    {
        foreach ([['VAT00', 'VAT 0%', 0], ['VAT11', 'VAT 11%', 11], ['VAT12', 'VAT 12%', 12]] as [$code, $name, $rate]) {
            $this->upsertByCode('vat_rates', [
                'company_id' => $this->companyId,
                'code' => $code,
                'name' => $name,
                'rate' => $rate,
                'description' => 'Demo ' . $name,
                'is_active' => 1,
            ], ['company_id' => $this->companyId, 'code' => $code]);
        }
    }

    private function seedPostalCodes(): void
    {
        $this->upsertByCode('countries', [
            'code' => 'ID',
            'name' => 'Indonesia',
            'is_active' => 1,
        ], ['code' => 'ID']);

        foreach ($this->demoAddressLocations() as $location) {
            $ids = $this->resolveLocationIds($location);
            if ($ids['city_id'] === null) {
                continue;
            }

            $this->upsertByCode('postal_codes', [
                'country_id' => $ids['country_id'],
                'province_id' => $ids['province_id'],
                'city_id' => $ids['city_id'],
                'code' => $location['postal_code'],
                'name' => $location['city_name'] . ' ' . $location['postal_code'],
                'district' => $location['district'],
                'village' => $location['village'],
                'is_active' => 1,
            ], ['code' => $location['postal_code'], 'city_id' => $ids['city_id']]);
        }
    }

    private function seedAddressTemplates(): void
    {
        $templates = [
            ['ADDR-JKT', 'Jakarta Business Address', 'general', 'Jakarta Selatan', 'Jl. Jend. Sudirman Kav. 52-53', 'SCBD Lot 8', 'Andi Wijaya', '021-50881234', '08119001001', 'office-jkt@example.test'],
            ['ADDR-BDG', 'Bandung Warehouse Address', 'ship_to', 'Bandung', 'Jl. Soekarno Hatta No. 88', 'Kawasan Bizpark Bandung', 'Rina Permata', '022-6012345', '08119001002', 'warehouse-bdg@example.test'],
            ['ADDR-SBY', 'Surabaya Branch Address', 'bill_to', 'Surabaya', 'Jl. Basuki Rahmat No. 101', 'Gedung Graha Pena Lt. 5', 'Budi Santoso', '031-5478821', '08119001003', 'branch-sby@example.test'],
            ['ADDR-SMG', 'Semarang Distribution Address', 'ship_to', 'Semarang', 'Jl. Pemuda No. 144', 'Area Pergudangan Semarang', 'Dewi Lestari', '024-3557711', '08119001004', 'dc-smg@example.test'],
            ['ADDR-DPS', 'Denpasar Store Address', 'mail_to', 'Denpasar', 'Jl. Teuku Umar No. 22', 'Ruko Niaga Barat', 'Made Arya', '0361-224455', '08119001005', 'store-dps@example.test'],
        ];

        foreach ($templates as [$code, $name, $type, $cityName, $line1, $line2, $contact, $phone, $mobile, $email]) {
            $location = $this->demoAddressLocationByCity($cityName);
            $ids = $this->resolveLocationIds($location);
            $postalCodeId = $this->postalCodeId($location['postal_code'], $ids['city_id']);

            $this->upsertByCode('addresses', [
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'address_type' => $type,
                'owner_type' => 'template',
                'owner_code' => null,
                'code' => $code,
                'name' => $name,
                'country_id' => $ids['country_id'],
                'province_id' => $ids['province_id'],
                'city_id' => $ids['city_id'],
                'postal_code_id' => $postalCodeId,
                'address_line1' => $line1,
                'address_line2' => $line2,
                'contact_name' => $contact,
                'phone' => $phone,
                'mobile' => $mobile,
                'email' => $email,
                'is_active' => 1,
            ], ['company_id' => $this->companyId, 'site_id' => $this->siteId, 'code' => $code]);
        }
    }

    private function seedCustomers(): void
    {
        $customers = [
            ['CUST-001', 'PT Nusantara Retailindo', 'Jakarta Selatan', 'ADDR-JKT', 'Andi Wijaya', 'NET30', 750000000],
            ['CUST-002', 'CV Bandung Makmur', 'Bandung', 'ADDR-BDG', 'Rina Permata', 'NET14', 250000000],
            ['CUST-003', 'PT Surya Distribusi Jaya', 'Surabaya', 'ADDR-SBY', 'Budi Santoso', 'NET30', 500000000],
            ['CUST-004', 'PT Semarang Sentosa', 'Semarang', 'ADDR-SMG', 'Dewi Lestari', 'NET21', 300000000],
            ['CUST-005', 'UD Bali Niaga', 'Denpasar', 'ADDR-DPS', 'Made Arya', 'COD', 150000000],
            ['CUST-006', 'PT Prima Grosir Mandiri', 'Jakarta Selatan', 'ADDR-JKT', 'Siti Rahma', 'NET30', 900000000],
            ['CUST-007', 'CV Sinar Bandung', 'Bandung', 'ADDR-BDG', 'Agus Setiawan', 'NET14', 200000000],
            ['CUST-008', 'PT Timur Logistik', 'Surabaya', 'ADDR-SBY', 'Nina Kartika', 'NET21', 450000000],
        ];

        foreach ($customers as [$code, $name, $cityName, $addressCode, $contact, $terms, $limit]) {
            $address = $this->partnerAddressValues($cityName, $addressCode, $contact);

            $this->upsertByCode('customers', array_merge([
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'company' => $this->companyCode,
                'site' => $this->siteCode,
                'code' => $code,
                'name' => $name,
                'customer' => $code,
                'customern' => $name,
                'customerr' => $name,
                'contactnar' => $contact,
                'description' => 'Demo customer generated for master data testing.',
                'shipwhs' => 'MAIN',
                'terms_code' => $terms,
                'currency_code' => 'IDR',
                'tax_number' => '01.' . substr(preg_replace('/\D/', '', $code), -3) . '.000.0-000.000',
                'address' => $address['line1'],
                'phone' => $address['phone'],
                'email' => strtolower(str_replace(['PT ', 'CV ', 'UD ', ' '], ['', '', '', '.'], $name)) . '@example.test',
                'taxcode' => 'PKP',
                'taxnumber' => '01.' . substr(preg_replace('/\D/', '', $code), -3) . '.000.0-000.000',
                'vat' => 'VAT11',
                'limitamound' => $limit,
                'limitqty' => 0,
                'terms' => $terms,
                'limitdays' => $terms === 'COD' ? 0 : (int) substr($terms, -2),
                'salescode' => 'SLS-01',
                'salesname' => 'Sales Demo',
                'bank1' => 'BCA',
                'bankaccou' => '8800' . substr(preg_replace('/\D/', '', $code), -3),
                'billingcust' => $code,
                'billingtoc' => $code,
                'mailcustom' => $code,
                'mailcode' => $code,
                'shiptocust' => $code,
                'shiptocode' => $code,
                'active' => 1,
                'is_active' => 1,
            ], $this->customerAddressColumns($address)), ['company_id' => $this->companyId, 'site_id' => $this->siteId, 'code' => $code]);
        }
    }

    private function seedSuppliers(): void
    {
        $suppliers = [
            ['SUP-001', 'PT Sumber Material Prima', 'Jakarta Selatan', 'ADDR-JKT', 'Dimas Putra', 'NET30', 600000000],
            ['SUP-002', 'CV Bandung Packaging', 'Bandung', 'ADDR-BDG', 'Yulia Safitri', 'NET14', 220000000],
            ['SUP-003', 'PT Surabaya Komponen Industri', 'Surabaya', 'ADDR-SBY', 'Hendra Wijaya', 'NET30', 650000000],
            ['SUP-004', 'PT Semarang Bahan Baku', 'Semarang', 'ADDR-SMG', 'Ratih Anggraini', 'NET21', 350000000],
            ['SUP-005', 'UD Bali Supply', 'Denpasar', 'ADDR-DPS', 'Komang Wira', 'COD', 125000000],
            ['SUP-006', 'PT Mandiri Sparepart Nusantara', 'Jakarta Selatan', 'ADDR-JKT', 'Taufik Hidayat', 'NET30', 800000000],
            ['SUP-007', 'CV Mega Chemical Bandung', 'Bandung', 'ADDR-BDG', 'Lia Marlina', 'NET14', 280000000],
            ['SUP-008', 'PT Timur Transportasi', 'Surabaya', 'ADDR-SBY', 'Farhan Akbar', 'NET21', 420000000],
        ];

        foreach ($suppliers as [$code, $name, $cityName, $addressCode, $contact, $terms, $limit]) {
            $address = $this->partnerAddressValues($cityName, $addressCode, $contact);

            $this->upsertByCode('suppliers', array_merge([
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'company' => $this->companyCode,
                'site' => $this->siteCode,
                'code' => $code,
                'name' => $name,
                'supplier' => $code,
                'supplierna' => $name,
                'supplierref' => $name,
                'contactnar' => $contact,
                'description' => 'Demo supplier generated for master data testing.',
                'terms_code' => $terms,
                'currency_code' => 'IDR',
                'tax_number' => '02.' . substr(preg_replace('/\D/', '', $code), -3) . '.000.0-000.000',
                'address' => $address['line1'],
                'phone' => $address['phone'],
                'email' => strtolower(str_replace(['PT ', 'CV ', 'UD ', ' '], ['', '', '', '.'], $name)) . '@example.test',
                'taxcode' => 'PKP',
                'taxnumber' => '02.' . substr(preg_replace('/\D/', '', $code), -3) . '.000.0-000.000',
                'vat' => 'VAT11',
                'limitamound' => $limit,
                'limitqty' => 0,
                'terms' => $terms,
                'limitdays' => $terms === 'COD' ? 0 : (int) substr($terms, -2),
                'employee' => 'EMP-001',
                'purchasing' => 'PUR-01',
                'bank1' => 'MANDIRI',
                'bankaccou' => '9900' . substr(preg_replace('/\D/', '', $code), -3),
                'active' => 1,
                'is_active' => 1,
            ], $this->supplierAddressColumns($address)), ['company_id' => $this->companyId, 'site_id' => $this->siteId, 'code' => $code]);
        }
    }

    private function seedItems(int $count): void
    {
        $groups = ['RM', 'FG', 'PKG', 'SP', 'CONS'];
        $classes = ['A', 'B', 'C'];
        $types = ['STK', 'SRV', 'AST'];
        $uoms = ['PCS', 'BOX', 'CTN', 'PACK', 'KG', 'LTR', 'MTR'];
        $names = [
            'Kertas A4 80gsm', 'Pulpen Hitam', 'Tinta Printer', 'Label Barcode', 'Karton Box',
            'Plastik Wrap', 'Botol Plastik', 'Tutup Botol', 'Bahan Kimia A', 'Bahan Kimia B',
            'Produk Jadi A', 'Produk Jadi B', 'Sparepart Gear', 'Bearing Standard', 'Kabel Power',
            'Sensor Module', 'Packaging Set', 'Sticker Produk', 'Manual Book', 'Pallet Kayu',
        ];

        for ($i = 1; $i <= $count; $i++) {
            $code = 'ITEM-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $baseName = $names[($i - 1) % count($names)];
            $uom = $uoms[$i % count($uoms)];
            $purchaseUom = $i % 3 === 0 ? 'BOX' : $uom;
            $sellingUom = $i % 4 === 0 ? 'PACK' : $uom;
            $price = 1000 + ($i * 750);

            $this->upsertByCode('items', [
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'company' => $this->companyCode,
                'site' => $this->siteCode,
                'code' => $code,
                'name' => $baseName . ' ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'item_code' => $code,
                'item_name' => $baseName . ' ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'item_coded' => $code . '-D',
                'item_named' => $baseName . ' Detail ' . $i,
                'shelf_life' => ($i % 12) * 30,
                'stockuom' => $uom,
                'purchaseuom' => $purchaseUom,
                'sellinguom' => $sellingUom,
                'stockwhs' => $i % 5 === 0 ? 'FG' : 'MAIN',
                'item_price' => $price,
                'purchasep' => $price * 0.9,
                'sellingprice' => $price * 1.25,
                'vat' => $i % 7 === 0 ? 'VAT00' : 'VAT11',
                'item_length' => 10 + ($i % 40),
                'item_width' => 5 + ($i % 25),
                'item_heigh' => 2 + ($i % 15),
                'item_diam' => $i % 10,
                'item_lengt' => 'CM',
                'item_widthh' => 'CM',
                'item_heigh_uom' => 'CM',
                'item_diam_uom' => 'CM',
                'out_length' => 20 + ($i % 50),
                'out_width' => 15 + ($i % 30),
                'out_height' => 10 + ($i % 20),
                'out_diame' => $i % 12,
                'out_lengt' => 'CM',
                'out_widthh' => 'CM',
                'out_height_uom' => 'CM',
                'out_diame_uom' => 'CM',
                'item_group' => $groups[$i % count($groups)],
                'item_subg' => 'S' . (($i % 9) + 1),
                'item_class' => $classes[$i % count($classes)],
                'item_subc' => 'SC' . (($i % 5) + 1),
                'item_type' => $types[$i % count($types)],
                'item_subty' => 'T' . (($i % 6) + 1),
                'item_atribu' => 'AT' . (($i % 8) + 1),
                'active' => $i % 25 === 0 ? 0 : 1,
                'is_active' => $i % 25 === 0 ? 0 : 1,
                'created_by' => 'demo',
                'updated_by' => 'demo',
            ], ['company_id' => $this->companyId, 'site_id' => $this->siteId, 'item_code' => $code]);
        }
    }

    private function seedOpeningStock(): void
    {
        if (! $this->db->tableExists('inventory_stock_balances') || ! $this->db->tableExists('inventory_stock_movements')) {
            return;
        }

        $warehouse = $this->db->table('warehouses')
            ->where('company_id', $this->companyId)
            ->where('site_id', $this->siteId)
            ->where('code', 'MAIN')
            ->get()
            ->getRowArray();

        $location = $this->db->table('locations')
            ->where('company_id', $this->companyId)
            ->where('site_id', $this->siteId)
            ->where('code', 'A01')
            ->get()
            ->getRowArray();

        $warehouseId = (int) ($warehouse['id'] ?? 0);
        $locationId = (int) ($location['id'] ?? 0);
        $items = $this->db->table('items')
            ->where('company_id', $this->companyId)
            ->where('site_id', $this->siteId)
            ->orderBy('item_code', 'ASC')
            ->get(80)
            ->getResultArray();

        foreach ($items as $index => $item) {
            $qty = 10 + (($index + 1) % 75);
            $cost = (float) ($item['item_price'] ?? 0);
            $value = $qty * $cost;
            $reserved = ($index + 1) % 6 === 0 ? 2 : 0;

            $where = [
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                'location_id' => $locationId > 0 ? $locationId : null,
                'item_code' => $item['item_code'],
            ];

            $balance = [
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                'location_id' => $locationId > 0 ? $locationId : null,
                'item_id' => $item['id'],
                'item_code' => $item['item_code'],
                'uom_code' => $item['stockuom'] ?: 'PCS',
                'qty_on_hand' => $qty,
                'qty_reserved' => $reserved,
                'qty_available' => $qty - $reserved,
                'avg_cost' => $cost,
                'stock_value' => $value,
            ];
            $this->upsertByCode('inventory_stock_balances', $balance, $where);

            $movementWhere = [
                'company_id' => $this->companyId,
                'reference_type' => 'demo_opening_stock',
                'reference_no' => 'OPEN-' . $item['item_code'],
            ];
            $exists = $this->db->table('inventory_stock_movements')->where($movementWhere)->get()->getRowArray();
            if ($exists === null) {
                $this->db->table('inventory_stock_movements')->insert([
                    'company_id' => $this->companyId,
                    'site_id' => $this->siteId,
                    'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                    'location_id' => $locationId > 0 ? $locationId : null,
                    'item_id' => $item['id'],
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'uom_code' => $item['stockuom'] ?: 'PCS',
                    'movement_date' => $this->now,
                    'movement_type' => 'opening_stock',
                    'direction' => 'in',
                    'qty' => $qty,
                    'unit_cost' => $cost,
                    'stock_value' => $value,
                    'reference_type' => 'demo_opening_stock',
                    'reference_no' => 'OPEN-' . $item['item_code'],
                    'notes' => 'Demo opening stock generated by DemoMasterDataSeeder.',
                    'created_by' => null,
                    'created_at' => $this->now,
                ]);
            }
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function demoAddressLocations(): array
    {
        return [
            [
                'province_code' => '31',
                'province_name' => 'DKI Jakarta',
                'city_code' => '3174',
                'city_name' => 'Jakarta Selatan',
                'postal_code' => '12190',
                'district' => 'Kebayoran Baru',
                'village' => 'Senayan',
            ],
            [
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'city_code' => '3273',
                'city_name' => 'Bandung',
                'postal_code' => '40115',
                'district' => 'Sumur Bandung',
                'village' => 'Braga',
            ],
            [
                'province_code' => '35',
                'province_name' => 'Jawa Timur',
                'city_code' => '3578',
                'city_name' => 'Surabaya',
                'postal_code' => '60271',
                'district' => 'Tegalsari',
                'village' => 'Kedungdoro',
            ],
            [
                'province_code' => '33',
                'province_name' => 'Jawa Tengah',
                'city_code' => '3374',
                'city_name' => 'Semarang',
                'postal_code' => '50132',
                'district' => 'Semarang Tengah',
                'village' => 'Sekayu',
            ],
            [
                'province_code' => '51',
                'province_name' => 'Bali',
                'city_code' => '5171',
                'city_name' => 'Denpasar',
                'postal_code' => '80227',
                'district' => 'Denpasar Barat',
                'village' => 'Pemecutan Kelod',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function demoAddressLocationByCity(string $cityName): array
    {
        foreach ($this->demoAddressLocations() as $location) {
            if ($location['city_name'] === $cityName) {
                return $location;
            }
        }

        return $this->demoAddressLocations()[0];
    }

    /**
     * @param array<string, string> $location
     *
     * @return array{country_id: int|null, province_id: int|null, city_id: int|null}
     */
    private function resolveLocationIds(array $location): array
    {
        $countryId = $this->lookupId('countries', ['ID', 'IDN'], ['Indonesia']);
        $provinceId = $this->lookupId('provinces', [$location['province_code']], [$location['province_name']]);
        $cityId = $this->lookupId('cities', [$location['city_code']], [$location['city_name'], 'Kota ' . $location['city_name']]);

        return [
            'country_id' => $countryId,
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ];
    }

    /**
     * @param list<string> $codes
     * @param list<string> $names
     */
    private function lookupId(string $table, array $codes, array $names): ?int
    {
        if (! $this->db->tableExists($table)) {
            return null;
        }

        $builder = $this->db->table($table);
        $builder->groupStart();
        foreach ($codes as $index => $code) {
            if ($index === 0) {
                $builder->where('code', $code);
                continue;
            }

            $builder->orWhere('code', $code);
        }

        foreach ($names as $name) {
            $builder->orLike('name', $name, 'both', null, true);
        }
        $builder->groupEnd();

        $row = $builder->orderBy('code', 'ASC')->get(1)->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function postalCodeId(string $postalCode, ?int $cityId): ?int
    {
        if (! $this->db->tableExists('postal_codes')) {
            return null;
        }

        $builder = $this->db->table('postal_codes')->where('code', $postalCode);
        if ($cityId !== null) {
            $builder->where('city_id', $cityId);
        }

        $row = $builder->get(1)->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * @return array<string, string>
     */
    private function partnerAddressValues(string $cityName, string $addressCode, string $contact): array
    {
        $template = $this->db->table('addresses')
            ->select('addresses.*, provinces.name AS province_name, cities.name AS city_name, countries.name AS country_name, postal_codes.code AS postal_code')
            ->join('provinces', 'provinces.id = addresses.province_id', 'left')
            ->join('cities', 'cities.id = addresses.city_id', 'left')
            ->join('countries', 'countries.id = addresses.country_id', 'left')
            ->join('postal_codes', 'postal_codes.id = addresses.postal_code_id', 'left')
            ->where('addresses.company_id', $this->companyId)
            ->where('addresses.site_id', $this->siteId)
            ->where('addresses.code', $addressCode)
            ->get(1)
            ->getRowArray();

        $fallback = $this->demoAddressLocationByCity($cityName);

        return [
            'line1' => (string) ($template['address_line1'] ?? 'Jl. Demo ERP No. 1'),
            'line2' => (string) ($template['address_line2'] ?? ''),
            'city' => (string) ($template['city_name'] ?? $fallback['city_name']),
            'province' => (string) ($template['province_name'] ?? $fallback['province_name']),
            'country' => (string) ($template['country_name'] ?? 'Indonesia'),
            'postal' => (string) ($template['postal_code'] ?? $fallback['postal_code']),
            'contact' => $contact,
            'phone' => (string) ($template['phone'] ?? '021-500000'),
            'mobile' => (string) ($template['mobile'] ?? '08119000000'),
        ];
    }

    /**
     * @param array<string, string> $address
     *
     * @return array<string, string>
     */
    private function customerAddressColumns(array $address): array
    {
        return [
            'officeaddre' => $address['line1'],
            'officecity' => $address['city'],
            'officeprovir' => $address['province'],
            'officecount' => $address['country'],
            'officeposta' => $address['postal'],
            'officeconta' => $address['contact'],
            'officephon' => $address['phone'],
            'officehp' => $address['mobile'],
            'billingaddre' => $address['line1'],
            'billingcity' => $address['city'],
            'billingprovi' => $address['province'],
            'billingcoun' => $address['country'],
            'billingposta' => $address['postal'],
            'billingconta' => $address['contact'],
            'billingphon' => $address['phone'],
            'billinghp' => $address['mobile'],
            'mailaddres' => $address['line1'],
            'mailcity' => $address['city'],
            'mailprovin' => $address['province'],
            'mailcountr' => $address['country'],
            'mailpostal' => $address['postal'],
            'mailcontac' => $address['contact'],
            'mailphone' => $address['phone'],
            'mailhp' => $address['mobile'],
            'shiptoaddr' => $address['line1'],
            'shiptocity' => $address['city'],
            'shiptoprovi' => $address['province'],
            'shiptocour' => $address['country'],
            'shiptopost' => $address['postal'],
            'shiptocont' => $address['contact'],
            'shiptophon' => $address['phone'],
            'shiptohp' => $address['mobile'],
        ];
    }

    /**
     * @param array<string, string> $address
     *
     * @return array<string, string>
     */
    private function supplierAddressColumns(array $address): array
    {
        return [
            'officeaddre' => $address['line1'],
            'officecity' => $address['city'],
            'officeprovir' => $address['province'],
            'officecoun' => $address['country'],
            'officeposta' => $address['postal'],
            'officeconta' => $address['contact'],
            'officephon' => $address['phone'],
            'officehp' => $address['mobile'],
            'mailaddres' => $address['line1'],
            'mailcity' => $address['city'],
            'mailprovin' => $address['province'],
            'mailcountr' => $address['country'],
            'mailpostal' => $address['postal'],
            'mailcontac' => $address['contact'],
            'mailphone' => $address['phone'],
            'mailhp' => $address['mobile'],
            'billingadre' => $address['line1'],
            'billingcity' => $address['city'],
            'billingprovi' => $address['province'],
            'billingcoun' => $address['country'],
            'billingposta' => $address['postal'],
            'billingconta' => $address['contact'],
            'billingphon' => $address['phone'],
            'billinghp' => $address['mobile'],
            'shiptoaddr' => $address['line1'],
            'shiptocity' => $address['city'],
            'shiptoprovi' => $address['province'],
            'shiptocoun' => $address['country'],
            'shiptopost' => $address['postal'],
            'shiptocont' => $address['contact'],
            'shiptophon' => $address['phone'],
            'shiptohp' => $address['mobile'],
        ];
    }

    private function upsertByCode(string $table, array $data, array $where): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $existing = $this->db->table($table)->where($where)->get()->getRowArray();
        $data = $this->filterExistingFields($table, $data);
        $data['updated_at'] = $this->now;

        if ($existing !== null) {
            $this->db->table($table)->where('id', $existing['id'])->update($data);
            return;
        }

        $data['created_at'] = $this->now;
        $this->db->table($table)->insert($data);
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
