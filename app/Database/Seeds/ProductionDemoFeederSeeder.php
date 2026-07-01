<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Database;

class ProductionDemoFeederSeeder extends Seeder
{
    private ?int $companyId = null;
    private ?int $siteId = null;
    private string $siteCode = 'HO';
    private string $departmentCode = 'GEN';
    private string $warehouseCode = '';

    public function run(): void
    {
        $this->db = Database::connect();
        $this->resolveTenantDefaults();
        $this->seedCostTypes();
        $this->seedWorkCenters();
        $this->seedRoutingsAndBoms();
    }

    private function resolveTenantDefaults(): void
    {
        if ($this->db->tableExists('companies')) {
            $company = $this->db->table('companies')
                ->groupStart()
                    ->where('code', 'PENA')
                    ->orWhere('company_code', 'PENA')
                ->groupEnd()
                ->get(1)
                ->getRowArray();
            if ($company === null) {
                $company = $this->db->table('companies')->orderBy('id', 'ASC')->get(1)->getRowArray();
            }
            $this->companyId = isset($company['id']) ? (int) $company['id'] : 1;
        } else {
            $this->companyId = 1;
        }

        if ($this->db->tableExists('sites')) {
            $site = $this->db->table('sites')
                ->groupStart()
                    ->where('code', 'HO')
                    ->orWhere('site_code', 'HO')
                ->groupEnd();
            if ($this->companyId !== null && $this->db->fieldExists('company_id', 'sites')) {
                $site->where('company_id', $this->companyId);
            }
            $siteRow = $site->get(1)->getRowArray();
            if ($siteRow === null) {
                $siteRow = $this->db->table('sites')->orderBy('id', 'ASC')->get(1)->getRowArray();
            }
            $this->siteId = isset($siteRow['id']) ? (int) $siteRow['id'] : null;
            $this->siteCode = (string) ($siteRow['code'] ?? $siteRow['site_code'] ?? 'HO');
        }
    }

    private function seedCostTypes(): void
    {
        if (! $this->db->tableExists('costing_cost_types')) {
            return;
        }

        foreach ([
            ['type' => 'Material', 'description' => 'Material Cost', 'cost_group' => 'Material'],
            ['type' => 'Labor', 'description' => 'Labor Cost', 'cost_group' => 'Labor'],
            ['type' => 'Overhead', 'description' => 'Overhead Cost', 'cost_group' => 'Overhead'],
        ] as $row) {
            $existing = $this->db->table('costing_cost_types')
                ->where('type', $row['type'])
                ->groupStart()
                    ->where('company_id', $this->companyId)
                    ->orWhere('company_id', null)
                ->groupEnd()
                ->get(1)
                ->getRowArray();

            $payload = $row + [
                'company_id' => $this->companyId,
                'is_active' => 1,
                'updated_by' => 'seeder',
                'updated_at' => date('Y-m-d H:i:s'),
                'deleted_at' => null,
            ];
            $payload = $this->filterPayload('costing_cost_types', $payload);

            if ($existing !== null) {
                $this->db->table('costing_cost_types')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload += $this->filterPayload('costing_cost_types', [
                    'created_by' => 'seeder',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->db->table('costing_cost_types')->insert($payload);
            }
        }
    }

    private function seedWorkCenters(): void
    {
        if (! $this->db->tableExists('production_work_centers')) {
            return;
        }

        $rows = [
            ['WC001', 'Assembly', 'Labor', 50000, 'Hour', 100, 8],
            ['WC002', 'Quality Control', 'Labor', 45000, 'Hour', 100, 8],
            ['WC003', 'Packing', 'Labor', 35000, 'Hour', 100, 8],
            ['WC004', 'Mixing / Filling', 'Labor', 60000, 'Hour', 100, 8],
            ['WC005', 'Printing / Labeling', 'Labor', 30000, 'Hour', 100, 8],
        ];

        foreach ($rows as $row) {
            [$code, $description, $costType, $costAmount, $costUom, $capacity, $workingHour] = $row;
            $existing = $this->db->table('production_work_centers')
                ->where('company_id', $this->companyId)
                ->where('work_center_code', $code)
                ->get(1)
                ->getRowArray();

            $payload = $this->filterPayload('production_work_centers', [
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'site_code' => $this->siteCode,
                'department_code' => $this->departmentCode,
                'warehouse_code' => $this->warehouseCode,
                'work_center_code' => $code,
                'description' => $description,
                'machine_code' => $code . '-MCH',
                'notes' => 'Demo feeder work center for routing/BOM testing.',
                'speed' => 1,
                'capacity_percent' => $capacity,
                'qty_labor' => 1,
                'working_hour' => $workingHour,
                'cost_type' => $costType,
                'cost_amount' => $costAmount,
                'cost_uom' => $costUom,
                'active_date' => '2024-07-01',
                'inactive_date' => '9999-12-31',
                'is_active' => 1,
                'updated_by' => 'seeder',
                'updated_at' => date('Y-m-d H:i:s'),
                'deleted_at' => null,
            ]);

            if ($existing !== null) {
                $this->db->table('production_work_centers')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload += $this->filterPayload('production_work_centers', [
                    'created_by' => 'seeder',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->db->table('production_work_centers')->insert($payload);
            }
        }
    }

    private function seedRoutingsAndBoms(): void
    {
        if (! $this->db->tableExists('production_routings') || ! $this->db->tableExists('production_boms')) {
            return;
        }

        foreach ([0, 20, 40, 60, 80] as $offset) {
            $this->seedGroup($offset);
        }
    }

    private function seedGroup(int $offset): void
    {
        $c = fn (int $n): string => sprintf('ITEM-%04d', $offset + $n);

        $sensor = $c(16);
        $gear = $c(13);
        $packaging = $c(17);
        $productA = $c(11);
        $productB = $c(12);

        $routingIds = [];
        $routingIds[$sensor] = $this->upsertRouting($sensor, 'Routing ' . $this->itemLabel($sensor), [
            [10, 'Assembly Sensor', 'WC001', 'Main', 10, 'Minute', 1, 'PCS', 'Sensor assembly'],
            [20, 'QC Sensor', 'WC002', 'Main', 5, 'Minute', 1, 'PCS', 'Sensor inspection'],
        ]);
        $routingIds[$gear] = $this->upsertRouting($gear, 'Routing ' . $this->itemLabel($gear), [
            [10, 'Gear Assembly', 'WC001', 'Main', 15, 'Minute', 1, 'PCS', 'Gear assembly'],
            [20, 'Gear Inspection', 'WC002', 'Main', 8, 'Minute', 1, 'PCS', 'Gear inspection'],
        ]);
        $routingIds[$packaging] = $this->upsertRouting($packaging, 'Routing ' . $this->itemLabel($packaging), [
            [10, 'Packing Material Prep', 'WC003', 'Main', 7, 'Minute', 1, 'SET', 'Prepare packaging material'],
            [20, 'Final Pack Set', 'WC003', 'Main', 5, 'Minute', 1, 'SET', 'Final packaging set'],
        ]);
        $routingIds[$productA] = $this->upsertRouting($productA, 'Routing ' . $this->itemLabel($productA), [
            [10, 'Final Assembly A', 'WC001', 'Main', 20, 'Minute', 1, 'PCS', 'Final assembly A'],
            [20, 'Mixing / Filling A', 'WC004', 'Main', 12, 'Minute', 1, 'PCS', 'Mixing/filling A'],
            [30, 'Final QC A', 'WC002', 'Main', 10, 'Minute', 1, 'PCS', 'Final QC A'],
            [40, 'Final Packing A', 'WC003', 'Main', 8, 'Minute', 1, 'PCS', 'Final packing A'],
        ]);
        $routingIds[$productB] = $this->upsertRouting($productB, 'Routing ' . $this->itemLabel($productB), [
            [10, 'Final Assembly B', 'WC001', 'Main', 25, 'Minute', 1, 'PCS', 'Final assembly B'],
            [20, 'Mixing / Filling B', 'WC004', 'Main', 15, 'Minute', 1, 'PCS', 'Mixing/filling B'],
            [30, 'Final QC B', 'WC002', 'Main', 10, 'Minute', 1, 'PCS', 'Final QC B'],
            [40, 'Final Packing B', 'WC003', 'Main', 8, 'Minute', 1, 'PCS', 'Final packing B'],
        ]);

        $this->upsertBom($sensor, $routingIds[$sensor], 1, 'PCS', 100, [
            [10, $c(15), 'Main Child', 1, 'PCS', 1, 'Kabel power sensor'],
            [20, $c(4), 'Main Child', 1, 'PCS', 1, 'Label barcode sensor'],
            [30, $c(3), 'Main Child', 0.05, 'LTR', 1, 'Tinta printer sensor'],
        ]);
        $this->upsertBom($gear, $routingIds[$gear], 1, 'PCS', 95, [
            [10, $c(14), 'Main Child', 2, 'PCS', 1, 'Bearing standard'],
            [20, $sensor, 'Main Child', 1, 'PCS', 1, 'Sensor module'],
            [30, $c(15), 'Alt 1', 1, 'PCS', 1, 'Alternative kabel power'],
        ]);
        $this->upsertBom($packaging, $routingIds[$packaging], 1, 'SET', 100, [
            [10, $c(5), 'Main Child', 1, 'PCS', 1, 'Karton box'],
            [20, $c(6), 'Main Child', 1, 'MTR', 1, 'Plastik wrap'],
            [30, $c(18), 'Main Child', 1, 'PCS', 1, 'Sticker produk'],
            [40, $c(19), 'Main Child', 1, 'PCS', 1, 'Manual book'],
            [50, $c(20), 'Main Child', 0.1, 'PCS', 1, 'Pallet kayu'],
        ]);
        $this->upsertBom($productA, $routingIds[$productA], 1, 'PCS', 98, [
            [10, $gear, 'Main Child', 1, 'PCS', 1, 'Sparepart gear'],
            [20, $packaging, 'Main Child', 1, 'SET', 1, 'Packaging set'],
            [30, $c(7), 'Main Child', 1, 'PCS', 1, 'Botol plastik'],
            [40, $c(8), 'Main Child', 1, 'PCS', 1, 'Tutup botol'],
            [50, $c(9), 'Main Child', 0.25, 'LTR', 1, 'Bahan kimia A'],
            [60, $c(10), 'Main Child', 0.15, 'LTR', 1, 'Bahan kimia B'],
        ]);
        $this->upsertBom($productB, $routingIds[$productB], 1, 'PCS', 97, [
            [10, $gear, 'Main Child', 1, 'PCS', 1.1, 'Sparepart gear with factor'],
            [20, $packaging, 'Main Child', 1, 'SET', 1, 'Packaging set'],
            [30, $c(1), 'Main Child', 2, 'PCS', 1, 'Kertas A4'],
            [40, $c(2), 'Main Child', 1, 'PCS', 1, 'Pulpen hitam'],
            [50, $c(9), 'Main Child', 0.3, 'LTR', 1, 'Bahan kimia A'],
            [60, $c(10), 'Alt 1', 0.2, 'LTR', 1, 'Alternative bahan kimia B'],
        ]);
    }

    /** @param array<int,array<int,mixed>> $lines */
    private function upsertRouting(string $itemCode, string $description, array $lines): int
    {
        $item = $this->item($itemCode);
        $existing = $this->db->table('production_routings')
            ->where('company_id', $this->companyId)
            ->where('site_code', $this->siteCode)
            ->where('item_code', $itemCode)
            ->get(1)
            ->getRowArray();

        $payload = $this->filterPayload('production_routings', [
            'company_id' => $this->companyId,
            'site_id' => $this->siteId,
            'site_code' => $this->siteCode,
            'department_code' => $this->departmentCode,
            'warehouse_code' => $this->warehouseCode,
            'item_id' => $item['id'] ?? null,
            'item_code' => $itemCode,
            'description' => $description,
            'is_active' => 1,
            'updated_by' => 'seeder',
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ]);

        if ($existing !== null) {
            $this->db->table('production_routings')->where('id', (int) $existing['id'])->update($payload);
            $routingId = (int) $existing['id'];
        } else {
            $payload += $this->filterPayload('production_routings', [
                'created_by' => 'seeder',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('production_routings')->insert($payload);
            $routingId = (int) $this->db->insertID();
        }

        foreach ($lines as $line) {
            [$routeNo, $name, $workCenter, $type, $hour, $hourUom, $speed, $speedUom, $notes] = $line;
            $existingLine = $this->db->table('production_routing_lines')
                ->where('production_routing_id', $routingId)
                ->where('route_no', $routeNo)
                ->get(1)
                ->getRowArray();

            $linePayload = $this->filterPayload('production_routing_lines', [
                'production_routing_id' => $routingId,
                'route_no' => $routeNo,
                'routing_name' => $name,
                'work_center_code' => $workCenter,
                'operation_type' => $type,
                'hour_qty' => $hour,
                'hour_uom' => $hourUom,
                'std_speed' => $speed,
                'speed_uom' => $speedUom,
                'notes' => $notes,
                'active_date' => '2024-07-01',
                'inactive_date' => '9999-12-31',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($existingLine !== null) {
                $this->db->table('production_routing_lines')->where('id', (int) $existingLine['id'])->update($linePayload);
            } else {
                $linePayload += $this->filterPayload('production_routing_lines', [
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->db->table('production_routing_lines')->insert($linePayload);
            }
        }

        return $routingId;
    }

    /** @param array<int,array<int,mixed>> $lines */
    private function upsertBom(string $parentItemCode, int $routingId, float $qtyBatch, string $uom, float $ratio, array $lines): int
    {
        $parent = $this->item($parentItemCode);
        $existing = $this->db->table('production_boms')
            ->where('company_id', $this->companyId)
            ->where('site_code', $this->siteCode)
            ->where('parent_item_code', $parentItemCode)
            ->get(1)
            ->getRowArray();

        $payload = $this->filterPayload('production_boms', [
            'company_id' => $this->companyId,
            'site_id' => $this->siteId,
            'site_code' => $this->siteCode,
            'department_code' => $this->departmentCode,
            'warehouse_code' => $this->warehouseCode,
            'parent_item_id' => $parent['id'] ?? null,
            'parent_item_code' => $parentItemCode,
            'parent_item_name' => $this->itemLabel($parentItemCode),
            'bom_type' => 'standard',
            'routing_id' => $routingId,
            'qty_batch' => $qtyBatch,
            'uom_code' => $uom,
            'ratio_percent' => $ratio,
            'description' => 'Demo BOM ' . $this->itemLabel($parentItemCode),
            'active_date' => '2024-07-01 00:00:00',
            'inactive_date' => '9999-12-31 00:00:00',
            'is_active' => 1,
            'updated_by' => 'seeder',
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ]);

        if ($existing !== null) {
            $this->db->table('production_boms')->where('id', (int) $existing['id'])->update($payload);
            $bomId = (int) $existing['id'];
        } else {
            $payload += $this->filterPayload('production_boms', [
                'created_by' => 'seeder',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('production_boms')->insert($payload);
            $bomId = (int) $this->db->insertID();
        }

        foreach ($lines as $line) {
            [$childNo, $childCode, $type, $qtyUsed, $lineUom, $factor, $description] = $line;
            $child = $this->item($childCode);
            $existingLine = $this->db->table('production_bom_lines')
                ->where('production_bom_id', $bomId)
                ->where('child_no', $childNo)
                ->get(1)
                ->getRowArray();

            $linePayload = $this->filterPayload('production_bom_lines', [
                'production_bom_id' => $bomId,
                'child_no' => $childNo,
                'child_item_id' => $child['id'] ?? null,
                'child_item_code' => $childCode,
                'child_item_name' => $this->itemLabel($childCode),
                'component_type' => $type,
                'qty_used' => $qtyUsed,
                'uom_code' => $lineUom,
                'factor' => $factor,
                'description' => $description,
                'active_date' => '2024-07-01',
                'inactive_date' => '9999-12-31',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($existingLine !== null) {
                $this->db->table('production_bom_lines')->where('id', (int) $existingLine['id'])->update($linePayload);
            } else {
                $linePayload += $this->filterPayload('production_bom_lines', [
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->db->table('production_bom_lines')->insert($linePayload);
            }
        }

        return $bomId;
    }

    private function item(string $itemCode): ?array
    {
        if (! $this->db->tableExists('items')) {
            return null;
        }
        $builder = $this->db->table('items');
        $this->db->fieldExists('item_code', 'items') ? $builder->where('item_code', $itemCode) : $builder->where('code', $itemCode);
        if ($this->companyId !== null && $this->db->fieldExists('company_id', 'items')) {
            $builder->where('company_id', $this->companyId);
        }

        return $builder->get(1)->getRowArray();
    }

    private function itemLabel(string $itemCode): string
    {
        $item = $this->item($itemCode);
        $name = (string) ($item['item_name'] ?? $item['name'] ?? '');

        return trim($itemCode . ($name !== '' ? ' - ' . $name : ''));
    }

    /** @param array<string,mixed> $payload */
    private function filterPayload(string $table, array $payload): array
    {
        if (! $this->db->tableExists($table)) {
            return [];
        }

        return array_intersect_key($payload, array_flip($this->db->getFieldNames($table)));
    }
}
