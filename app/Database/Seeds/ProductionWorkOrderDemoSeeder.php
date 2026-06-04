<?php

namespace App\Database\Seeds;

use App\Services\Production\WorkOrderService;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class ProductionWorkOrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->db->tableExists('production_work_orders')) {
            return;
        }

        $company = $this->db->table('companies')->where('code', 'PENA')->get()->getRowArray();
        $site = $this->db->table('sites')->where('code', 'HO')->get()->getRowArray();
        $companyId = (int) ($company['id'] ?? 1);

        $existing = $this->db->table('production_work_orders')
            ->where('company_id', $companyId)
            ->where('wo_no', 'WO-DEMO-001')
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return;
        }

        $item = $this->db->table('items')->where('item_code', 'ITEM-0001')->get()->getRowArray();

        try {
            (new WorkOrderService())->create([
                'company_id' => $companyId,
                'site_id' => (int) ($site['id'] ?? 1),
                'wo_code' => 'WO',
                'wo_no' => 'WO-DEMO-001',
                'wo_date' => date('Y-m-d'),
                'site_code' => 'HO',
                'department_code' => 'GEN',
                'warehouse_code' => 'MAIN',
                'work_center_code' => 'WC-ASSY',
                'parent_item_id' => $item['id'] ?? null,
                'parent_item_code' => 'ITEM-0001',
                'parent_item_name' => $item['item_name'] ?? $item['name'] ?? 'ITEM-0001',
                'wo_qty' => 10,
                'description' => 'Demo work order generated from sample BOM and routing.',
            ], null);
        } catch (RuntimeException) {
            return;
        }
    }
}
