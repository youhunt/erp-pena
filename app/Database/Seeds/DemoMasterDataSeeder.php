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
