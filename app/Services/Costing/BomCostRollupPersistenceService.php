<?php

namespace App\Services\Costing;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

class BomCostRollupPersistenceService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /** @param array<string,mixed> $calculation */
    public function save(?int $companyId, ?int $siteId, array $calculation, mixed $userId = null): void
    {
        if (! $this->db->tableExists('costing_item_costs')) {
            return;
        }

        $items = $calculation['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return;
        }

        $this->db->transStart();
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemCode = trim((string) ($item['item_code'] ?? ''));
            if ($itemCode === '') {
                continue;
            }

            $existing = $this->findExistingItemCost($companyId, $siteId, $itemCode);
            $thisItemCost = $existing !== null ? (float) ($existing['this_item_cost'] ?? 0) : (float) ($item['this_item_cost'] ?? 0);
            $bomCost = (float) ($item['bom_cost'] ?? 0);
            $workCenterCost = $existing !== null ? (float) ($existing['work_center_cost'] ?? $existing['wc_cost'] ?? 0) : (float) ($item['work_center_cost'] ?? 0);
            $totalCost = $thisItemCost + $bomCost + $workCenterCost;

            $payload = $this->filterPayload('costing_item_costs', [
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
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($existing !== null) {
                $this->db->table('costing_item_costs')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload += $this->filterPayload('costing_item_costs', [
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->db->table('costing_item_costs')->insert($payload);
            }

            $this->updateLegacyItemCostTable($companyId, $siteId, $itemCode, $bomCost, $totalCost);
        }
        $this->db->transComplete();
    }

    private function findExistingItemCost(?int $companyId, ?int $siteId, string $itemCode): ?array
    {
        $builder = $this->db->table('costing_item_costs')->where('item_code', $itemCode);
        if ($this->db->fieldExists('deleted_at', 'costing_item_costs')) {
            $builder->where('deleted_at', null);
        }
        if ($companyId !== null && $this->db->fieldExists('company_id', 'costing_item_costs')) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $this->db->fieldExists('site_id', 'costing_item_costs')) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }

        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    /** @param array<string,mixed> $payload */
    private function filterPayload(string $table, array $payload): array
    {
        if (! $this->db->tableExists($table)) {
            return [];
        }

        return array_intersect_key($payload, array_flip($this->db->getFieldNames($table)));
    }

    private function updateLegacyItemCostTable(?int $companyId, ?int $siteId, string $itemCode, float $bomCost, float $totalCost): void
    {
        foreach (['item_cost', 'item_costs'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $payload = [];
            foreach (['BomCost', 'BOMCost', 'bom_cost'] as $field) {
                if ($this->db->fieldExists($field, $table)) {
                    $payload[$field] = $bomCost;
                }
            }
            foreach (['TotalCost', 'total_cost'] as $field) {
                if ($this->db->fieldExists($field, $table)) {
                    $payload[$field] = $totalCost;
                }
            }
            if ($payload === []) {
                continue;
            }

            $builder = $this->db->table($table);
            if ($this->db->fieldExists('ItemCode', $table)) {
                $builder->where('ItemCode', $itemCode);
            } elseif ($this->db->fieldExists('item_code', $table)) {
                $builder->where('item_code', $itemCode);
            } elseif ($this->db->fieldExists('code', $table)) {
                $builder->where('code', $itemCode);
            } else {
                continue;
            }

            if ($companyId !== null && $this->db->fieldExists('company_id', $table)) {
                $builder->where('company_id', $companyId);
            }
            if ($siteId !== null && $this->db->fieldExists('site_id', $table)) {
                $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
            }
            $builder->update($payload);
        }
    }
}
