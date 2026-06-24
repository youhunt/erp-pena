<?php

namespace App\Services\Production;

use Config\Database;
use RuntimeException;

class MrpService
{
    /**
     * Generate material requirement from production_forecasts and active BOM.
     */
    public function generate(int $companyId, ?int $siteId, string $fromDate, string $toDate, ?string $itemCode, int $userId): int
    {
        if ($companyId < 1) {
            throw new RuntimeException('Active company is required.');
        }
        if ($fromDate === '' || $toDate === '') {
            throw new RuntimeException('Forecast date range is required.');
        }

        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $runNo = 'MRP-' . date('Ymd-His');

        $forecastBuilder = $db->table('production_forecasts')
            ->select('item_code, item_name, uom_code, SUM(qty) AS demand_qty')
            ->where('company_id', $companyId)
            ->where('forecast_date >=', $fromDate)
            ->where('forecast_date <=', $toDate)
            ->whereIn('status', ['draft', 'confirmed', 'approved']);

        if ($siteId !== null && $siteId > 0) {
            $forecastBuilder->where('site_id', $siteId);
        }
        if ($itemCode !== null && trim($itemCode) !== '') {
            $forecastBuilder->where('item_code', strtoupper(trim($itemCode)));
        }
        if ($db->fieldExists('deleted_at', 'production_forecasts')) {
            $forecastBuilder->where('deleted_at', null);
        }

        $demands = $forecastBuilder
            ->groupBy('item_code, item_name, uom_code')
            ->orderBy('item_code', 'ASC')
            ->get()
            ->getResultArray();

        $db->transBegin();
        try {
            $db->table('production_mrp_runs')->insert([
                'company_id' => $companyId,
                'site_id' => $siteId,
                'run_no' => $runNo,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'item_code_filter' => $itemCode !== null && trim($itemCode) !== '' ? strtoupper(trim($itemCode)) : null,
                'source' => 'forecast',
                'status' => 'generated',
                'demand_count' => count($demands),
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $runId = (int) $db->insertID();

            $requirements = [];
            $missingBom = [];
            foreach ($demands as $demand) {
                $parentCode = strtoupper(trim((string) ($demand['item_code'] ?? '')));
                $parentName = trim((string) ($demand['item_name'] ?? ''));
                $demandQty = (float) ($demand['demand_qty'] ?? 0);
                if ($parentCode === '' || $demandQty <= 0) {
                    continue;
                }

                $bom = $this->activeBom($db, $companyId, $siteId, $parentCode);
                if ($bom === null) {
                    $missingBom[$parentCode] = [
                        'parent_item_code' => $parentCode,
                        'parent_item_name' => $parentName,
                        'demand_qty' => ($missingBom[$parentCode]['demand_qty'] ?? 0) + $demandQty,
                    ];
                    continue;
                }

                $qtyBatch = max((float) ($bom['qty_batch'] ?? 1), 0.000001);
                $lines = $db->table('production_bom_lines')
                    ->where('production_bom_id', (int) $bom['id'])
                    ->orderBy('child_no', 'ASC')
                    ->get()
                    ->getResultArray();

                foreach ($lines as $line) {
                    $componentCode = strtoupper(trim((string) ($line['child_item_code'] ?? '')));
                    if ($componentCode === '') {
                        continue;
                    }
                    $componentName = trim((string) ($line['child_item_name'] ?? ''));
                    $uom = trim((string) ($line['uom_code'] ?? $bom['uom_code'] ?? 'PCS'));
                    $qtyUsed = (float) ($line['qty_used'] ?? 0);
                    $factor = (float) ($line['factor'] ?? 1);
                    $gross = ($demandQty / $qtyBatch) * $qtyUsed * ($factor > 0 ? $factor : 1);
                    if ($gross <= 0) {
                        continue;
                    }

                    $key = $componentCode . '|' . $uom;
                    if (! isset($requirements[$key])) {
                        $requirements[$key] = [
                            'component_item_code' => $componentCode,
                            'component_item_name' => $componentName,
                            'uom_code' => $uom,
                            'gross_requirement' => 0.0,
                            'parent_items' => [],
                        ];
                    }
                    $requirements[$key]['gross_requirement'] += $gross;
                    $requirements[$key]['parent_items'][$parentCode] = $parentCode;
                }
            }

            $lineNo = 10;
            foreach ($requirements as $row) {
                $stockAvailable = $this->availableStock($db, $companyId, $siteId, $row['component_item_code']);
                $gross = round((float) $row['gross_requirement'], 6);
                $net = max($gross - $stockAvailable, 0);
                $action = $net > 0 ? 'plan_purchase_or_produce' : 'covered_by_stock';

                $db->table('production_mrp_lines')->insert([
                    'mrp_run_id' => $runId,
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                    'line_no' => $lineNo,
                    'line_type' => 'material',
                    'parent_item_code' => implode(',', array_values($row['parent_items'])),
                    'component_item_code' => $row['component_item_code'],
                    'component_item_name' => $row['component_item_name'],
                    'uom_code' => $row['uom_code'],
                    'gross_requirement' => $gross,
                    'stock_available' => round($stockAvailable, 6),
                    'net_requirement' => round($net, 6),
                    'suggested_action' => $action,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $lineNo += 10;
            }

            foreach ($missingBom as $row) {
                $db->table('production_mrp_lines')->insert([
                    'mrp_run_id' => $runId,
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                    'line_no' => $lineNo,
                    'line_type' => 'missing_bom',
                    'parent_item_code' => $row['parent_item_code'],
                    'component_item_code' => $row['parent_item_code'],
                    'component_item_name' => $row['parent_item_name'],
                    'uom_code' => '',
                    'gross_requirement' => round((float) $row['demand_qty'], 6),
                    'stock_available' => 0,
                    'net_requirement' => round((float) $row['demand_qty'], 6),
                    'suggested_action' => 'create_bom',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $lineNo += 10;
            }

            $summary = $db->table('production_mrp_lines')
                ->select('COUNT(*) AS line_count, SUM(gross_requirement) AS gross_qty, SUM(net_requirement) AS net_qty')
                ->where('mrp_run_id', $runId)
                ->get()
                ->getRowArray() ?: [];

            $db->table('production_mrp_runs')->where('id', $runId)->update([
                'line_count' => (int) ($summary['line_count'] ?? 0),
                'gross_qty' => (float) ($summary['gross_qty'] ?? 0),
                'net_qty' => (float) ($summary['net_qty'] ?? 0),
                'updated_at' => $now,
                'updated_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new RuntimeException('MRP generation failed.');
        }

        $db->transCommit();
        return $runId;
    }

    private function activeBom($db, int $companyId, ?int $siteId, string $parentCode): ?array
    {
        $builder = $db->table('production_boms')
            ->where('company_id', $companyId)
            ->where('parent_item_code', $parentCode)
            ->where('is_active', 1);

        if ($siteId !== null && $siteId > 0) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if ($db->fieldExists('deleted_at', 'production_boms')) {
            $builder->where('deleted_at', null);
        }

        return $builder->orderBy('site_id', 'DESC')->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    private function availableStock($db, int $companyId, ?int $siteId, string $itemCode): float
    {
        if (! $db->tableExists('inventory_stock_balances')) {
            return 0.0;
        }

        $builder = $db->table('inventory_stock_balances')
            ->select('SUM(qty_available) AS qty_available')
            ->where('company_id', $companyId)
            ->where('item_code', $itemCode);
        if ($siteId !== null && $siteId > 0) {
            $builder->where('site_id', $siteId);
        }

        $row = $builder->get()->getRowArray();
        return round((float) ($row['qty_available'] ?? 0), 6);
    }
}
