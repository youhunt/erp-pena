<?php

namespace App\Controllers\Costing;

use App\Controllers\BaseController;
use App\Services\Costing\BomCostRollupService;
use App\Services\TenantContext;
use Config\Database;

class CostCalculationController extends BaseController
{
    public function calculate()
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();
        $itemCode = trim((string) $this->request->getGet('item_code'));
        $calculation = ['rows' => [], 'items' => [], 'total_cost' => 0.0, 'this_item_cost' => 0.0, 'bom_cost' => 0.0, 'work_center_cost' => 0.0];

        if ($itemCode !== '') {
            $calculation = (new BomCostRollupService($db))->calculate($companyId, $siteId, $itemCode);
        }

        if ((string) $this->request->getGet('action') === 'save' && $itemCode !== '') {
            if (! $db->tableExists('costing_item_costs')) {
                return redirect()->to('/modules/calculate-cost?item_code=' . rawurlencode($itemCode))->with('error', 'Run costing SQL installer first. Table costing_item_costs does not exist.');
            }

            $this->saveItemCostRollup($db, $companyId, $siteId, $calculation);
            return redirect()->to('/modules/item-cost')->with('message', 'BOM Cost roll-up saved to Item Cost from bottom level to parent.');
        }

        return view('costing/calculate/index', [
            'title' => 'Calculate Cost',
            'itemCode' => $itemCode,
            'calculation' => $calculation,
            'hasTable' => $db->tableExists('costing_item_costs'),
        ]);
    }

    /** @param array<string, mixed> $calculation */
    private function saveItemCostRollup($db, ?int $companyId, ?int $siteId, array $calculation): void
    {
        $items = $calculation['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return;
        }

        $db->transStart();

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemCode = trim((string) ($item['item_code'] ?? ''));
            if ($itemCode === '') {
                continue;
            }

            $existing = $this->findExistingItemCost($db, $companyId, $siteId, $itemCode);
            $thisItemCost = $existing !== null ? (float) ($existing['this_item_cost'] ?? 0) : (float) ($item['this_item_cost'] ?? 0);
            $bomCost = (float) ($item['bom_cost'] ?? 0);
            $workCenterCost = $existing !== null ? (float) ($existing['work_center_cost'] ?? $existing['wc_cost'] ?? 0) : (float) ($item['work_center_cost'] ?? 0);
            $totalCost = $thisItemCost + $bomCost + $workCenterCost;

            $payload = $this->filterPayload($db, 'costing_item_costs', [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'site_code' => $existing['site_code'] ?? '',
                'item_code' => $itemCode,
                'item_name' => $item['item_name'] ?? $existing['item_name'] ?? '',
                'department_code' => $existing['department_code'] ?? '',
                'warehouse_code' => $existing['warehouse_code'] ?? '',
                'description' => $existing['description'] ?? 'Calculated BOM Cost Roll-up',
                'this_item_cost' => $thisItemCost,
                'bom_cost' => $bomCost,
                'work_center_cost' => $workCenterCost,
                'wc_cost' => $workCenterCost,
                'total_cost' => $totalCost,
                'status' => 'calculated',
                'updated_by' => auth()->id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($existing !== null) {
                $db->table('costing_item_costs')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload += $this->filterPayload($db, 'costing_item_costs', [
                    'created_by' => auth()->id(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $db->table('costing_item_costs')->insert($payload);
            }

            $this->updateLegacyItemCostTable($db, $companyId, $siteId, $itemCode, $bomCost, $totalCost);
        }

        $db->transComplete();
    }

    private function findExistingItemCost($db, ?int $companyId, ?int $siteId, string $itemCode): ?array
    {
        $builder = $db->table('costing_item_costs')->where('item_code', $itemCode);
        if ($db->fieldExists('deleted_at', 'costing_item_costs')) {
            $builder->where('deleted_at', null);
        }
        if ($companyId !== null && $db->fieldExists('company_id', 'costing_item_costs')) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $db->fieldExists('site_id', 'costing_item_costs')) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }

        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    /** @param array<string,mixed> $payload */
    private function filterPayload($db, string $table, array $payload): array
    {
        if (! $db->tableExists($table)) {
            return [];
        }

        return array_intersect_key($payload, array_flip($db->getFieldNames($table)));
    }

    private function updateLegacyItemCostTable($db, ?int $companyId, ?int $siteId, string $itemCode, float $bomCost, float $totalCost): void
    {
        foreach (['item_cost', 'item_costs'] as $table) {
            if (! $db->tableExists($table)) {
                continue;
            }

            $payload = [];
            foreach (['BomCost', 'BOMCost', 'bom_cost'] as $field) {
                if ($db->fieldExists($field, $table)) {
                    $payload[$field] = $bomCost;
                }
            }
            foreach (['TotalCost', 'total_cost'] as $field) {
                if ($db->fieldExists($field, $table)) {
                    $payload[$field] = $totalCost;
                }
            }
            if ($payload === []) {
                continue;
            }

            $builder = $db->table($table);
            $itemField = $db->fieldExists('ItemCode', $table) ? 'ItemCode' : ($db->fieldExists('item_code', $table) ? 'item_code' : 'code');
            $builder->where($itemField, $itemCode);
            if ($companyId !== null && $db->fieldExists('company_id', $table)) {
                $builder->where('company_id', $companyId);
            }
            if ($siteId !== null && $db->fieldExists('site_id', $table)) {
                $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
            }
            $builder->update($payload);
        }
    }
}
