<?php

namespace App\Services\Costing;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

class BomCostRollupService
{
    private BaseConnection $db;
    private ?int $companyId = null;
    private ?int $siteId = null;
    /** @var array<string, bool> */
    private array $visited = [];
    /** @var array<string, array<string, mixed>> */
    private array $itemResults = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>, items:array<string,array<string,mixed>>, total_cost:float, this_item_cost:float, bom_cost:float, work_center_cost:float}
     */
    public function calculate(?int $companyId, ?int $siteId, string $itemCode): array
    {
        $this->companyId = $companyId;
        $this->siteId = $siteId;
        $this->visited = [];
        $this->itemResults = [];

        $result = $this->calculateItem(trim($itemCode), 0, null, null);
        $result['items'] = $this->itemResults;

        return $result;
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>, total_cost:float, this_item_cost:float, bom_cost:float, work_center_cost:float}
     */
    private function calculateItem(string $itemCode, int $depth, ?array $incomingLine, ?array $parentBom): array
    {
        $key = strtoupper($itemCode);
        if ($itemCode === '') {
            return $this->emptyResult();
        }
        if (isset($this->visited[$key])) {
            return $this->emptyResult('Loop BOM detected');
        }

        $this->visited[$key] = true;

        $thisItemCost = $this->baseItemCost($itemCode);
        $bom = $this->activeBom($itemCode);
        $rows = [];
        $bomCost = 0.0;
        $qtyBatch = (float) ($bom['qty_batch'] ?? $parentBom['qty_batch'] ?? 1);
        $ratioPercent = (float) ($bom['ratio_percent'] ?? 100);
        if ($ratioPercent <= 0) {
            $ratioPercent = 100.0;
        }

        $mainLineSubtotal = 0.0;
        $childDisplayRows = [];

        if ($bom !== null && $this->db->tableExists('production_bom_lines')) {
            $lines = $this->db->table('production_bom_lines')
                ->where('production_bom_id', (int) $bom['id'])
                ->orderBy('child_no', 'ASC')
                ->get(1000)
                ->getResultArray();

            foreach ($lines as $line) {
                $childCode = trim((string) ($line['child_item_code'] ?? ''));
                if ($childCode === '') {
                    continue;
                }

                $componentType = trim((string) ($line['component_type'] ?? ''));
                $isMainChild = $this->isMainChild($componentType);
                $qtyUsed = (float) ($line['qty_used'] ?? 1);
                $factor = $this->factor($line['factor'] ?? null);
                $childCalc = $this->calculateItem($childCode, $depth + 1, $line, $bom);
                $childEffectiveCost = (float) ($childCalc['total_cost'] ?? 0);
                $lineContribution = $isMainChild ? ($childEffectiveCost * $qtyUsed * $factor) : 0.0;

                if ($isMainChild) {
                    $mainLineSubtotal += $lineContribution;
                }

                $childDisplayRows[] = [
                    'depth' => $depth + 1,
                    'row_type' => 'component',
                    'bom_no' => (string) ($bom['parent_item_code'] ?? $itemCode),
                    'item_code' => $childCode,
                    'item_name' => $this->itemName($childCode, (string) ($line['child_item_name'] ?? '')),
                    'qty_batch' => (float) ($bom['qty_batch'] ?? 1),
                    'qty_used' => $qtyUsed,
                    'uom_code' => (string) ($line['uom_code'] ?? ''),
                    'ratio_percent' => $ratioPercent,
                    'factor' => $factor,
                    'component_type' => $componentType !== '' ? $componentType : 'Main Child',
                    'this_item_cost' => (float) ($childCalc['this_item_cost'] ?? 0),
                    'bom_cost' => (float) ($childCalc['bom_cost'] ?? 0),
                    'total_cost' => $lineContribution,
                    'effective_item_cost' => $childEffectiveCost,
                    'notes' => $isMainChild ? 'Main Child' : 'Alternative ignored for parent cost',
                ];

                foreach (($childCalc['rows'] ?? []) as $childRow) {
                    $childDisplayRows[] = $childRow;
                }
            }

            $bomCost = $mainLineSubtotal / ($ratioPercent / 100);
        }

        $workCenterCost = 0.0;
        $totalCost = $thisItemCost + $bomCost + $workCenterCost;
        $itemName = $this->itemName($itemCode, '');

        $itemRow = [
            'depth' => $depth,
            'row_type' => 'item',
            'bom_no' => $itemCode,
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'qty_batch' => $qtyBatch,
            'qty_used' => $incomingLine === null ? null : (float) ($incomingLine['qty_used'] ?? 1),
            'uom_code' => (string) ($bom['uom_code'] ?? $incomingLine['uom_code'] ?? ''),
            'ratio_percent' => $ratioPercent,
            'factor' => $incomingLine === null ? null : $this->factor($incomingLine['factor'] ?? null),
            'component_type' => $incomingLine === null ? 'Parent' : (string) ($incomingLine['component_type'] ?? 'Main Child'),
            'this_item_cost' => $thisItemCost,
            'bom_cost' => $bomCost,
            'work_center_cost' => $workCenterCost,
            'total_cost' => $totalCost,
            'effective_item_cost' => $totalCost,
            'notes' => $bom !== null ? 'Update to Item Cost BOM Cost' : 'Leaf/Base Item Cost',
        ];

        $rows[] = $itemRow;
        foreach ($childDisplayRows as $childDisplayRow) {
            $rows[] = $childDisplayRow;
        }

        $this->itemResults[$key] = [
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'this_item_cost' => $thisItemCost,
            'bom_cost' => $bomCost,
            'work_center_cost' => $workCenterCost,
            'total_cost' => $totalCost,
            'qty_batch' => $qtyBatch,
            'uom_code' => (string) ($bom['uom_code'] ?? ''),
            'ratio_percent' => $ratioPercent,
            'has_bom' => $bom !== null,
        ];

        unset($this->visited[$key]);

        return [
            'rows' => $rows,
            'total_cost' => $totalCost,
            'this_item_cost' => $thisItemCost,
            'bom_cost' => $bomCost,
            'work_center_cost' => $workCenterCost,
        ];
    }

    /** @return array{rows:array<int,array<string,mixed>>,total_cost:float,this_item_cost:float,bom_cost:float,work_center_cost:float,warning?:string} */
    private function emptyResult(string $warning = ''): array
    {
        $result = [
            'rows' => [],
            'total_cost' => 0.0,
            'this_item_cost' => 0.0,
            'bom_cost' => 0.0,
            'work_center_cost' => 0.0,
        ];
        if ($warning !== '') {
            $result['warning'] = $warning;
        }

        return $result;
    }

    private function activeBom(string $itemCode): ?array
    {
        if (! $this->db->tableExists('production_boms')) {
            return null;
        }

        $builder = $this->db->table('production_boms')->where('parent_item_code', $itemCode);
        if ($this->companyId !== null && $this->db->fieldExists('company_id', 'production_boms')) {
            $builder->where('company_id', $this->companyId);
        }
        if ($this->siteId !== null && $this->db->fieldExists('site_id', 'production_boms')) {
            $builder->groupStart()->where('site_id', $this->siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if ($this->db->fieldExists('is_active', 'production_boms')) {
            $builder->where('is_active', 1);
        }
        if ($this->db->fieldExists('deleted_at', 'production_boms')) {
            $builder->where('deleted_at', null);
        }

        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    private function baseItemCost(string $itemCode): float
    {
        if (! $this->db->tableExists('costing_item_costs')) {
            return 0.0;
        }

        $builder = $this->db->table('costing_item_costs')->where('item_code', $itemCode);
        if ($this->db->fieldExists('deleted_at', 'costing_item_costs')) {
            $builder->where('deleted_at', null);
        }
        if ($this->companyId !== null && $this->db->fieldExists('company_id', 'costing_item_costs')) {
            $builder->where('company_id', $this->companyId);
        }
        if ($this->siteId !== null && $this->db->fieldExists('site_id', 'costing_item_costs')) {
            $builder->groupStart()->where('site_id', $this->siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }

        $row = $builder->orderBy('id', 'DESC')->get(1)->getRowArray();
        return (float) ($row['this_item_cost'] ?? 0);
    }

    private function itemName(string $itemCode, string $fallback): string
    {
        if ($fallback !== '') {
            return $fallback;
        }
        if (! $this->db->tableExists('items') || $itemCode === '') {
            return '';
        }

        $builder = $this->db->table('items');
        $this->db->fieldExists('item_code', 'items') ? $builder->where('item_code', $itemCode) : $builder->where('code', $itemCode);
        if ($this->companyId !== null && $this->db->fieldExists('company_id', 'items')) {
            $builder->where('company_id', $this->companyId);
        }
        $row = $builder->get(1)->getRowArray();

        return (string) ($row['item_name'] ?? $row['name'] ?? '');
    }

    private function isMainChild(string $componentType): bool
    {
        $type = strtolower(trim($componentType));
        if ($type === '') {
            return true;
        }
        if (str_starts_with($type, 'alt') || str_contains($type, 'alternatif') || str_contains($type, 'alternative')) {
            return false;
        }

        return in_array($type, ['main', 'main child', 'material', 'child'], true) || str_contains($type, 'main');
    }

    private function factor(mixed $value): float
    {
        $factor = (float) ($value ?: 1);
        return $factor == 0.0 ? 1.0 : $factor;
    }
}
