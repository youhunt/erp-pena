<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Database;

class ProductionBomRoutingFeederSeeder extends Seeder
{
    private $db;
    private int $companyId = 1;
    private ?int $siteId = null;
    private string $siteCode = 'HO';
    private string $dept = 'GEN';
    private string $whs = '';

    public function run(): void
    {
        $this->db = Database::connect();
        $this->resolveCompanySite();
        $this->seedCostTypes();
        $this->seedWorkCenters();

        foreach ([0, 20, 40, 60, 80] as $offset) {
            $this->seedGroup($offset);
        }
    }

    private function resolveCompanySite(): void
    {
        if ($this->db->tableExists('companies')) {
            $builder = $this->db->table('companies');
            if ($this->db->fieldExists('code', 'companies')) {
                $builder->where('code', 'PENA');
            }
            $company = $builder->get(1)->getRowArray();
            if ($company === null) {
                $company = $this->db->table('companies')->orderBy('id', 'ASC')->get(1)->getRowArray();
            }
            $this->companyId = max(1, (int) ($company['id'] ?? 1));
        }

        if ($this->db->tableExists('sites')) {
            $builder = $this->db->table('sites');
            if ($this->db->fieldExists('code', 'sites')) {
                $builder->where('code', 'HO');
            }
            if ($this->db->fieldExists('company_id', 'sites')) {
                $builder->where('company_id', $this->companyId);
            }
            $site = $builder->get(1)->getRowArray();
            if ($site === null) {
                $site = $this->db->table('sites')->orderBy('id', 'ASC')->get(1)->getRowArray();
            }
            $this->siteId = isset($site['id']) ? (int) $site['id'] : null;
            $this->siteCode = (string) ($site['code'] ?? 'HO');
        }
    }

    private function seedCostTypes(): void
    {
        if (! $this->db->tableExists('costing_cost_types')) {
            return;
        }

        foreach ([
            ['Material', 'Material Cost', 'Material'],
            ['Labor', 'Labor Cost', 'Labor'],
            ['Overhead', 'Overhead Cost', 'Overhead'],
        ] as $row) {
            [$type, $description, $group] = $row;
            $existing = $this->db->table('costing_cost_types')
                ->where('type', $type)
                ->groupStart()->where('company_id', $this->companyId)->orWhere('company_id', null)->groupEnd()
                ->get(1)->getRowArray();

            $payload = $this->filter('costing_cost_types', [
                'company_id' => $this->companyId,
                'type' => $type,
                'description' => $description,
                'cost_group' => $group,
                'is_active' => 1,
                'updated_by' => 'feeder',
                'updated_at' => date('Y-m-d H:i:s'),
                'deleted_at' => null,
            ]);

            if ($existing) {
                $this->db->table('costing_cost_types')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload += $this->filter('costing_cost_types', ['created_by' => 'feeder', 'created_at' => date('Y-m-d H:i:s')]);
                $this->db->table('costing_cost_types')->insert($payload);
            }
        }
    }

    private function seedWorkCenters(): void
    {
        foreach ([
            ['WC001', 'Assembly', 50000],
            ['WC002', 'Quality Control', 45000],
            ['WC003', 'Packing', 35000],
            ['WC004', 'Mixing / Filling', 60000],
            ['WC005', 'Printing / Labeling', 30000],
        ] as $row) {
            [$code, $description, $cost] = $row;
            $existing = $this->db->table('production_work_centers')
                ->where('company_id', $this->companyId)
                ->where('work_center_code', $code)
                ->get(1)->getRowArray();

            $payload = $this->filter('production_work_centers', [
                'company_id' => $this->companyId,
                'site_id' => $this->siteId,
                'site_code' => $this->siteCode,
                'department_code' => $this->dept,
                'warehouse_code' => $this->whs,
                'work_center_code' => $code,
                'description' => $description,
                'machine_code' => $code . '-MCH',
                'notes' => 'Production feeder data for BOM/Routing demo.',
                'speed' => 1,
                'capacity_percent' => 100,
                'qty_labor' => 1,
                'working_hour' => 8,
                'cost_type' => 'Labor',
                'cost_amount' => $cost,
                'cost_uom' => 'Hour',
                'active_date' => '2024-07-01',
                'inactive_date' => '9999-12-31',
                'is_active' => 1,
                'updated_by' => 'feeder',
                'updated_at' => date('Y-m-d H:i:s'),
                'deleted_at' => null,
            ]);

            if ($existing) {
                $this->db->table('production_work_centers')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload += $this->filter('production_work_centers', ['created_by' => 'feeder', 'created_at' => date('Y-m-d H:i:s')]);
                $this->db->table('production_work_centers')->insert($payload);
            }
        }
    }

    private function seedGroup(int $offset): void
    {
        $c = static fn (int $n): string => sprintf('ITEM-%04d', $offset + $n);

        $sensor = $c(16);
        $gear = $c(13);
        $packaging = $c(17);
        $productA = $c(11);
        $productB = $c(12);

        $routingIds = [
            $sensor => $this->routing($sensor, [
                [10, 'Assembly Sensor', 'WC001', 10, 'Sensor assembly'],
                [20, 'QC Sensor', 'WC002', 5, 'Sensor inspection'],
            ]),
            $gear => $this->routing($gear, [
                [10, 'Gear Assembly', 'WC001', 15, 'Gear assembly'],
                [20, 'Gear Inspection', 'WC002', 8, 'Gear inspection'],
            ]),
            $packaging => $this->routing($packaging, [
                [10, 'Packing Material Prep', 'WC003', 7, 'Prepare packaging material'],
                [20, 'Final Pack Set', 'WC003', 5, 'Final packaging set'],
            ]),
            $productA => $this->routing($productA, [
                [10, 'Final Assembly A', 'WC001', 20, 'Final assembly A'],
                [20, 'Mixing / Filling A', 'WC004', 12, 'Mixing/filling A'],
                [30, 'Final QC A', 'WC002', 10, 'Final QC A'],
                [40, 'Final Packing A', 'WC003', 8, 'Final packing A'],
            ]),
            $productB => $this->routing($productB, [
                [10, 'Final Assembly B', 'WC001', 25, 'Final assembly B'],
                [20, 'Mixing / Filling B', 'WC004', 15, 'Mixing/filling B'],
                [30, 'Final QC B', 'WC002', 10, 'Final QC B'],
                [40, 'Final Packing B', 'WC003', 8, 'Final packing B'],
            ]),
        ];

        $this->bom($sensor, $routingIds[$sensor], 1, 'PCS', 100, [
            [10, $c(15), 'Main Child', 1, 'PCS', 1, 'Kabel power sensor'],
            [20, $c(4), 'Main Child', 1, 'PCS', 1, 'Label barcode sensor'],
            [30, $c(3), 'Main Child', 0.05, 'LTR', 1, 'Tinta printer sensor'],
        ]);
        $this->bom($gear, $routingIds[$gear], 1, 'PCS', 95, [
            [10, $c(14), 'Main Child', 2, 'PCS', 1, 'Bearing standard'],
            [20, $sensor, 'Main Child', 1, 'PCS', 1, 'Sensor module'],
            [30, $c(15), 'Alt 1', 1, 'PCS', 1, 'Alternative kabel power'],
        ]);
        $this->bom($packaging, $routingIds[$packaging], 1, 'SET', 100, [
            [10, $c(5), 'Main Child', 1, 'PCS', 1, 'Karton box'],
            [20, $c(6), 'Main Child', 1, 'MTR', 1, 'Plastik wrap'],
            [30, $c(18), 'Main Child', 1, 'PCS', 1, 'Sticker produk'],
            [40, $c(19), 'Main Child', 1, 'PCS', 1, 'Manual book'],
            [50, $c(20), 'Main Child', 0.1, 'PCS', 1, 'Pallet kayu'],
        ]);
        $this->bom($productA, $routingIds[$productA], 1, 'PCS', 98, [
            [10, $gear, 'Main Child', 1, 'PCS', 1, 'Sparepart gear'],
            [20, $packaging, 'Main Child', 1, 'SET', 1, 'Packaging set'],
            [30, $c(7), 'Main Child', 1, 'PCS', 1, 'Botol plastik'],
            [40, $c(8), 'Main Child', 1, 'PCS', 1, 'Tutup botol'],
            [50, $c(9), 'Main Child', 0.25, 'LTR', 1, 'Bahan kimia A'],
            [60, $c(10), 'Main Child', 0.15, 'LTR', 1, 'Bahan kimia B'],
        ]);
        $this->bom($productB, $routingIds[$productB], 1, 'PCS', 97, [
            [10, $gear, 'Main Child', 1, 'PCS', 1.1, 'Sparepart gear with factor'],
            [20, $packaging, 'Main Child', 1, 'SET', 1, 'Packaging set'],
            [30, $c(1), 'Main Child', 2, 'PCS', 1, 'Kertas A4'],
            [40, $c(2), 'Main Child', 1, 'PCS', 1, 'Pulpen hitam'],
            [50, $c(9), 'Main Child', 0.3, 'LTR', 1, 'Bahan kimia A'],
            [60, $c(10), 'Alt 1', 0.2, 'LTR', 1, 'Alternative bahan kimia B'],
        ]);
    }

    /** @param array<int,array<int,mixed>> $lines */
    private function routing(string $itemCode, array $lines): int
    {
        $item = $this->item($itemCode);
        $existing = $this->db->table('production_routings')
            ->where('company_id', $this->companyId)
            ->where('site_code', $this->siteCode)
            ->where('item_code', $itemCode)
            ->get(1)->getRowArray();

        $payload = $this->filter('production_routings', [
            'company_id' => $this->companyId,
            'site_id' => $this->siteId,
            'site_code' => $this->siteCode,
            'department_code' => $this->dept,
            'warehouse_code' => $this->whs,
            'item_id' => $item['id'] ?? null,
            'item_code' => $itemCode,
            'description' => 'Routing ' . $this->itemLabel($itemCode),
            'is_active' => 1,
            'updated_by' => 'feeder',
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ]);

        if ($existing) {
            $this->db->table('production_routings')->where('id', (int) $existing['id'])->update($payload);
            $routingId = (int) $existing['id'];
        } else {
            $payload += $this->filter('production_routings', ['created_by' => 'feeder', 'created_at' => date('Y-m-d H:i:s')]);
            $this->db->table('production_routings')->insert($payload);
            $routingId = (int) $this->db->insertID();
        }

        foreach ($lines as $line) {
            [$routeNo, $name, $wc, $hour, $notes] = $line;
            $linePayload = $this->filter('production_routing_lines', [
                'production_routing_id' => $routingId,
                'route_no' => $routeNo,
                'routing_name' => $name,
                'work_center_code' => $wc,
                'operation_type' => 'Main',
                'hour_qty' => $hour,
                'hour_uom' => 'Minute',
                'std_speed' => 1,
                'speed_uom' => 'PCS',
                'notes' => $notes,
                'active_date' => '2024-07-01',
                'inactive_date' => '9999-12-31',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $existingLine = $this->db->table('production_routing_lines')
                ->where('production_routing_id', $routingId)
                ->where('route_no', $routeNo)
                ->get(1)->getRowArray();
            if ($existingLine) {
                $this->db->table('production_routing_lines')->where('id', (int) $existingLine['id'])->update($linePayload);
            } else {
                $linePayload += $this->filter('production_routing_lines', ['created_at' => date('Y-m-d H:i:s')]);
                $this->db->table('production_routing_lines')->insert($linePayload);
            }
        }

        return $routingId;
    }

    /** @param array<int,array<int,mixed>> $lines */
    private function bom(string $parentCode, int $routingId, float $qtyBatch, string $uom, float $ratio, array $lines): int
    {
        $parent = $this->item($parentCode);
        $existing = $this->db->table('production_boms')
            ->where('company_id', $this->companyId)
            ->where('site_code', $this->siteCode)
            ->where('parent_item_code', $parentCode)
            ->get(1)->getRowArray();

        $payload = $this->filter('production_boms', [
            'company_id' => $this->companyId,
            'site_id' => $this->siteId,
            'site_code' => $this->siteCode,
            'department_code' => $this->dept,
            'warehouse_code' => $this->whs,
            'parent_item_id' => $parent['id'] ?? null,
            'parent_item_code' => $parentCode,
            'parent_item_name' => $this->itemLabel($parentCode),
            'bom_type' => 'standard',
            'routing_id' => $routingId,
            'qty_batch' => $qtyBatch,
            'uom_code' => $uom,
            'ratio_percent' => $ratio,
            'description' => 'Demo BOM ' . $this->itemLabel($parentCode),
            'active_date' => '2024-07-01 00:00:00',
            'inactive_date' => '9999-12-31 00:00:00',
            'is_active' => 1,
            'updated_by' => 'feeder',
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ]);

        if ($existing) {
            $this->db->table('production_boms')->where('id', (int) $existing['id'])->update($payload);
            $bomId = (int) $existing['id'];
        } else {
            $payload += $this->filter('production_boms', ['created_by' => 'feeder', 'created_at' => date('Y-m-d H:i:s')]);
            $this->db->table('production_boms')->insert($payload);
            $bomId = (int) $this->db->insertID();
        }

        foreach ($lines as $line) {
            [$childNo, $childCode, $type, $qty, $lineUom, $factor, $description] = $line;
            $child = $this->item($childCode);
            $linePayload = $this->filter('production_bom_lines', [
                'production_bom_id' => $bomId,
                'child_no' => $childNo,
                'child_item_id' => $child['id'] ?? null,
                'child_item_code' => $childCode,
                'child_item_name' => $this->itemLabel($childCode),
                'component_type' => $type,
                'qty_used' => $qty,
                'uom_code' => $lineUom,
                'factor' => $factor,
                'description' => $description,
                'active_date' => '2024-07-01',
                'inactive_date' => '9999-12-31',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $existingLine = $this->db->table('production_bom_lines')
                ->where('production_bom_id', $bomId)
                ->where('child_no', $childNo)
                ->get(1)->getRowArray();
            if ($existingLine) {
                $this->db->table('production_bom_lines')->where('id', (int) $existingLine['id'])->update($linePayload);
            } else {
                $linePayload += $this->filter('production_bom_lines', ['created_at' => date('Y-m-d H:i:s')]);
                $this->db->table('production_bom_lines')->insert($linePayload);
            }
        }

        return $bomId;
    }

    private function item(string $code): ?array
    {
        if (! $this->db->tableExists('items')) {
            return null;
        }
        $builder = $this->db->table('items');
        $this->db->fieldExists('item_code', 'items') ? $builder->where('item_code', $code) : $builder->where('code', $code);
        if ($this->db->fieldExists('company_id', 'items')) {
            $builder->where('company_id', $this->companyId);
        }
        return $builder->get(1)->getRowArray();
    }

    private function itemLabel(string $code): string
    {
        $item = $this->item($code);
        $name = (string) ($item['item_name'] ?? $item['name'] ?? '');
        return trim($code . ($name !== '' ? ' - ' . $name : ''));
    }

    /** @param array<string,mixed> $payload */
    private function filter(string $table, array $payload): array
    {
        return array_intersect_key($payload, array_flip($this->db->getFieldNames($table)));
    }
}
