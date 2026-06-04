<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductionDemoSeeder extends Seeder
{
    private string $now;

    public function run(): void
    {
        if (! $this->db->tableExists('production_boms')) {
            return;
        }

        $this->now = date('Y-m-d H:i:s');
        $company = $this->db->table('companies')->where('code', 'PENA')->get()->getRowArray();
        $site = $this->db->table('sites')->where('code', 'HO')->get()->getRowArray();
        $companyId = (int) ($company['id'] ?? 1);
        $siteId = (int) ($site['id'] ?? 1);

        $this->seedWorkCenter($companyId, $siteId);
        $this->seedBom($companyId, $siteId);
        $this->seedRouting($companyId, $siteId);
    }

    private function seedWorkCenter(int $companyId, int $siteId): void
    {
        $this->upsert('production_work_centers', [
            'company_id' => $companyId,
            'site_code' => 'HO',
            'department_code' => 'GEN',
            'warehouse_code' => 'MAIN',
            'work_center_code' => 'WC-ASSY',
        ], [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'site_code' => 'HO',
            'department_code' => 'GEN',
            'warehouse_code' => 'MAIN',
            'work_center_code' => 'WC-ASSY',
            'description' => 'Assembly Work Center',
            'machine_code' => 'MC-ASSY',
            'notes' => 'Demo production work center.',
            'speed' => 10,
            'capacity_percent' => 100,
            'qty_labor' => 2,
            'working_hour' => 8,
            'cost_type' => 'Labor',
            'cost_amount' => 30000,
            'cost_uom' => 'Hour',
            'active_date' => date('Y-m-d'),
            'is_active' => 1,
            'created_by' => null,
            'updated_by' => null,
        ]);
    }

    private function seedBom(int $companyId, int $siteId): void
    {
        $parent = $this->item('ITEM-0001');
        $bomId = $this->upsert('production_boms', [
            'company_id' => $companyId,
            'site_code' => 'HO',
            'department_code' => 'GEN',
            'warehouse_code' => 'MAIN',
            'parent_item_code' => 'ITEM-0001',
        ], [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'site_code' => 'HO',
            'department_code' => 'GEN',
            'warehouse_code' => 'MAIN',
            'parent_item_id' => $parent['id'] ?? null,
            'parent_item_code' => 'ITEM-0001',
            'parent_item_name' => $parent['item_name'] ?? $parent['name'] ?? 'ITEM-0001',
            'bom_type' => 'standard',
            'qty_batch' => 1,
            'uom_code' => 'PCS',
            'ratio_percent' => 100,
            'description' => 'Demo BOM for production flow.',
            'active_date' => $this->now,
            'is_active' => 1,
            'created_by' => null,
            'updated_by' => null,
        ]);

        foreach ([
            [10, 'ITEM-0002', 2, 'PCS'],
            [20, 'ITEM-0003', 1, 'PCS'],
            [30, 'ITEM-0005', 1, 'PCS'],
        ] as [$no, $code, $qty, $uom]) {
            $item = $this->item($code);
            $this->upsert('production_bom_lines', [
                'production_bom_id' => $bomId,
                'child_no' => $no,
            ], [
                'production_bom_id' => $bomId,
                'child_no' => $no,
                'child_item_id' => $item['id'] ?? null,
                'child_item_code' => $code,
                'child_item_name' => $item['item_name'] ?? $item['name'] ?? $code,
                'component_type' => 'material',
                'qty_used' => $qty,
                'uom_code' => $uom,
                'factor' => 1,
                'description' => 'Demo component.',
                'active_date' => $this->now,
            ]);
        }
    }

    private function seedRouting(int $companyId, int $siteId): void
    {
        $item = $this->item('ITEM-0001');
        $routingId = $this->upsert('production_routings', [
            'company_id' => $companyId,
            'site_code' => 'HO',
            'item_code' => 'ITEM-0001',
        ], [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'site_code' => 'HO',
            'department_code' => 'GEN',
            'warehouse_code' => 'MAIN',
            'item_id' => $item['id'] ?? null,
            'item_code' => 'ITEM-0001',
            'description' => 'Demo routing for assembly.',
            'is_active' => 1,
            'created_by' => null,
            'updated_by' => null,
        ]);

        $this->upsert('production_routing_lines', [
            'production_routing_id' => $routingId,
            'route_no' => '10',
        ], [
            'production_routing_id' => $routingId,
            'route_no' => '10',
            'routing_name' => 'Assembly',
            'work_center_code' => 'WC-ASSY',
            'operation_type' => 'process',
            'hour_qty' => 1,
            'hour_uom' => 'Hour',
            'std_speed' => 10,
            'speed_uom' => 'Unit/Hour',
            'notes' => 'Demo routing step.',
        ]);
    }

    private function item(string $code): ?array
    {
        return $this->db->table('items')->where('item_code', $code)->get()->getRowArray();
    }

    private function upsert(string $table, array $where, array $data): int
    {
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
}
