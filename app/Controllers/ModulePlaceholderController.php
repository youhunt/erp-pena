<?php

namespace App\Controllers;

use App\Services\TenantContext;
use Config\Database;

class ModulePlaceholderController extends BaseController
{
    public function show(string $slug): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $slug = strtolower(trim($slug));

        if ($slug === 'forecast') {
            return redirect()->to('/production/forecasts');
        }
        if ($slug === 'mrp') {
            return redirect()->to('/production/mrp');
        }
        if ($slug === 'planned-released') {
            return $this->plannedReleased();
        }
        if ($slug === 'mps') {
            return $this->mps();
        }
        if ($slug === 'cost-type') {
            return $this->costTypes();
        }
        if ($slug === 'item-cost') {
            return $this->itemCosts();
        }
        if ($slug === 'calculate-cost') {
            return $this->calculateCost();
        }

        $title = $this->titleFromSlug($slug);

        return view('modules/placeholder', [
            'title' => $title,
            'slug' => $slug,
        ]);
    }

    public function mpsPage(): string
    {
        return $this->mps();
    }

    public function plannedReleasedPage(): string
    {
        return $this->plannedReleased();
    }

    private function costTypes(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();

        if (! $db->tableExists('costing_cost_types')) {
            return view('costing/cost_types/index', [
                'title' => 'Cost Type',
                'rows' => [],
                'hasTable' => false,
            ]);
        }

        $action = (string) $this->request->getGet('action');
        if ($action === 'save') {
            $type = trim((string) $this->request->getGet('type'));
            $description = trim((string) $this->request->getGet('description'));
            $group = trim((string) $this->request->getGet('cost_group')) ?: 'Material';
            $id = (int) ($this->request->getGet('id') ?? 0);

            if ($type === '' || ! in_array($group, ['Material', 'Labor', 'Overhead'], true)) {
                return redirect()->to('/modules/cost-type')->with('error', 'Cost Type and Cost Group are required.');
            }

            $payload = [
                'company_id' => $companyId,
                'type' => $type,
                'description' => $description,
                'cost_group' => $group,
                'is_active' => 1,
                'updated_by' => auth()->id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($id > 0) {
                $db->table('costing_cost_types')->where('id', $id)->update($payload);
            } else {
                $payload['created_by'] = auth()->id();
                $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('costing_cost_types')->insert($payload);
            }
            return redirect()->to('/modules/cost-type')->with('message', 'Cost Type saved.');
        }

        if ($action === 'delete') {
            $id = (int) ($this->request->getGet('id') ?? 0);
            if ($id > 0) {
                $db->table('costing_cost_types')->where('id', $id)->update([
                    'is_active' => 0,
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'updated_by' => auth()->id(),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            return redirect()->to('/modules/cost-type')->with('message', 'Cost Type disabled.');
        }

        $builder = $db->table('costing_cost_types')->where('deleted_at', null);
        if ($companyId !== null) {
            $builder->groupStart()->where('company_id', $companyId)->orWhere('company_id', null)->groupEnd();
        }

        return view('costing/cost_types/index', [
            'title' => 'Cost Type',
            'rows' => $builder->orderBy('cost_group', 'ASC')->orderBy('type', 'ASC')->get(500)->getResultArray(),
            'hasTable' => true,
        ]);
    }

    private function itemCosts(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();

        if (! $db->tableExists('costing_item_costs')) {
            return view('costing/item_costs/index', [
                'title' => 'Item Cost',
                'rows' => [],
                'hasTable' => false,
            ]);
        }

        if ((string) $this->request->getGet('action') === 'save') {
            $itemCode = trim((string) $this->request->getGet('item_code'));
            if ($itemCode === '') {
                return redirect()->to('/modules/item-cost')->with('error', 'Item Code is required.');
            }

            $payload = [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'site_code' => trim((string) $this->request->getGet('site_code')),
                'item_code' => $itemCode,
                'item_name' => trim((string) $this->request->getGet('item_name')),
                'department_code' => trim((string) $this->request->getGet('department_code')),
                'warehouse_code' => trim((string) $this->request->getGet('warehouse_code')),
                'description' => trim((string) $this->request->getGet('description')),
                'this_item_cost' => (float) ($this->request->getGet('this_item_cost') ?: 0),
                'updated_by' => auth()->id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $existing = $db->table('costing_item_costs')
                ->where('company_id', $companyId)
                ->where('site_id', $siteId)
                ->where('item_code', $itemCode)
                ->where('department_code', $payload['department_code'])
                ->where('warehouse_code', $payload['warehouse_code'])
                ->where('deleted_at', null)
                ->get(1)->getRowArray();

            if ($existing !== null) {
                $db->table('costing_item_costs')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['status'] = 'draft';
                $payload['created_by'] = auth()->id();
                $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('costing_item_costs')->insert($payload);
            }

            return redirect()->to('/modules/item-cost')->with('message', 'Item Cost saved.');
        }

        $builder = $db->table('costing_item_costs')->where('deleted_at', null);
        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null) {
            $builder->where('site_id', $siteId);
        }

        return view('costing/item_costs/index', [
            'title' => 'Item Cost',
            'rows' => $builder->orderBy('item_code', 'ASC')->get(500)->getResultArray(),
            'hasTable' => true,
        ]);
    }

    private function calculateCost(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();
        $itemCode = trim((string) $this->request->getGet('item_code'));
        $calculation = ['rows' => [], 'total_cost' => 0.0, 'this_item_cost' => 0.0, 'bom_cost' => 0.0];

        if ($itemCode !== '') {
            $calculation = $this->calculateBomCost($db, $companyId, $siteId, $itemCode);
        }

        if ((string) $this->request->getGet('action') === 'save' && $itemCode !== '') {
            if (! $db->tableExists('costing_item_costs') || ! $db->tableExists('costing_item_cost_lines')) {
                return redirect()->to('/modules/calculate-cost?item_code=' . rawurlencode($itemCode))->with('error', 'Run costing SQL installer first.');
            }
            $this->saveCalculation($db, $companyId, $siteId, $itemCode, $calculation);
            return redirect()->to('/modules/item-cost')->with('message', 'Calculated cost saved to Item Cost.');
        }

        return view('costing/calculate/index', [
            'title' => 'Calculate Cost',
            'itemCode' => $itemCode,
            'calculation' => $calculation,
            'hasTable' => $db->tableExists('costing_item_costs') && $db->tableExists('costing_item_cost_lines'),
        ]);
    }

    private function calculateBomCost($db, ?int $companyId, ?int $siteId, string $itemCode, int $depth = 0, array &$visited = []): array
    {
        $key = strtoupper($itemCode);
        if (isset($visited[$key])) {
            return ['rows' => [], 'total_cost' => 0.0, 'this_item_cost' => 0.0, 'bom_cost' => 0.0, 'warning' => 'Loop BOM detected'];
        }
        $visited[$key] = true;

        $thisCost = $this->baseItemCost($db, $companyId, $siteId, $itemCode);
        $rows = [];
        $bomCost = 0.0;
        $bom = $this->activeBom($db, $companyId, $siteId, $itemCode);

        if ($bom !== null) {
            $lines = $db->table('production_bom_lines')
                ->where('production_bom_id', (int) $bom['id'])
                ->orderBy('child_no', 'ASC')
                ->get(500)
                ->getResultArray();

            foreach ($lines as $line) {
                $childCode = (string) ($line['child_item_code'] ?? '');
                $childCalc = $this->calculateBomCost($db, $companyId, $siteId, $childCode, $depth + 1, $visited);
                $qtyUsed = (float) ($line['qty_used'] ?? 1);
                $factor = (float) ($line['factor'] ?? 1);
                if ($factor == 0.0) {
                    $factor = 1.0;
                }
                $lineTotal = (float) ($childCalc['total_cost'] ?? 0) * $qtyUsed * $factor;
                $bomCost += $lineTotal;
                $rows[] = [
                    'depth' => $depth + 1,
                    'bom_no' => (string) ($bom['parent_item_code'] ?? $itemCode),
                    'item_code' => $childCode,
                    'item_name' => (string) ($line['child_item_name'] ?? ''),
                    'qty_batch' => (float) ($bom['qty_batch'] ?? 1),
                    'qty_used' => $qtyUsed,
                    'uom_code' => (string) ($line['uom_code'] ?? ''),
                    'ratio_percent' => (float) ($bom['ratio_percent'] ?? 100),
                    'factor' => $factor,
                    'this_item_cost' => (float) ($childCalc['this_item_cost'] ?? 0),
                    'bom_cost' => (float) ($childCalc['bom_cost'] ?? 0),
                    'total_cost' => $lineTotal,
                    'notes' => (string) ($line['component_type'] ?? ''),
                ];
                foreach (($childCalc['rows'] ?? []) as $childRow) {
                    $rows[] = $childRow;
                }
            }
        }

        unset($visited[$key]);
        return [
            'rows' => $rows,
            'total_cost' => $thisCost + $bomCost,
            'this_item_cost' => $thisCost,
            'bom_cost' => $bomCost,
            'work_center_cost' => 0.0,
        ];
    }

    private function activeBom($db, ?int $companyId, ?int $siteId, string $itemCode): ?array
    {
        if ($itemCode === '' || ! $db->tableExists('production_boms')) {
            return null;
        }
        $builder = $db->table('production_boms')->where('parent_item_code', $itemCode);
        if ($companyId !== null && $db->fieldExists('company_id', 'production_boms')) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $db->fieldExists('site_id', 'production_boms')) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if ($db->fieldExists('is_active', 'production_boms')) {
            $builder->where('is_active', 1);
        }
        if ($db->fieldExists('deleted_at', 'production_boms')) {
            $builder->where('deleted_at', null);
        }
        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    private function baseItemCost($db, ?int $companyId, ?int $siteId, string $itemCode): float
    {
        if ($itemCode === '' || ! $db->tableExists('costing_item_costs')) {
            return 0.0;
        }
        $builder = $db->table('costing_item_costs')->where('item_code', $itemCode)->where('deleted_at', null);
        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        $row = $builder->orderBy('id', 'DESC')->get(1)->getRowArray();
        return $row !== null ? (float) ($row['this_item_cost'] ?? 0) : 0.0;
    }

    private function saveCalculation($db, ?int $companyId, ?int $siteId, string $itemCode, array $calculation): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $db->table('costing_item_costs')
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->where('item_code', $itemCode)
            ->where('deleted_at', null)
            ->get(1)->getRowArray();
        $payload = [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'item_code' => $itemCode,
            'this_item_cost' => (float) ($calculation['this_item_cost'] ?? 0),
            'bom_cost' => (float) ($calculation['bom_cost'] ?? 0),
            'work_center_cost' => 0,
            'total_cost' => (float) ($calculation['total_cost'] ?? 0),
            'status' => 'calculated',
            'calculated_at' => $now,
            'updated_by' => auth()->id(),
            'updated_at' => $now,
        ];
        if ($existing !== null) {
            $itemCostId = (int) $existing['id'];
            $db->table('costing_item_costs')->where('id', $itemCostId)->update($payload);
            $db->table('costing_item_cost_lines')->where('item_cost_id', $itemCostId)->delete();
        } else {
            $payload['created_by'] = auth()->id();
            $payload['created_at'] = $now;
            $db->table('costing_item_costs')->insert($payload);
            $itemCostId = (int) $db->insertID();
        }
        foreach (($calculation['rows'] ?? []) as $line) {
            $db->table('costing_item_cost_lines')->insert([
                'item_cost_id' => $itemCostId,
                'bom_no' => $line['bom_no'] ?? null,
                'child_item_code' => $line['item_code'] ?? '',
                'child_item_name' => $line['item_name'] ?? '',
                'qty_batch' => $line['qty_batch'] ?? null,
                'qty_used' => $line['qty_used'] ?? null,
                'uom_code' => $line['uom_code'] ?? null,
                'ratio_percent' => $line['ratio_percent'] ?? null,
                'factor' => $line['factor'] ?? null,
                'this_item_cost' => $line['this_item_cost'] ?? 0,
                'bom_cost' => $line['bom_cost'] ?? 0,
                'work_center_cost' => 0,
                'total_cost' => $line['total_cost'] ?? 0,
                'notes' => $line['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function plannedReleased(): string
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();
        $status = trim((string) ($this->request->getGet('status') ?: ''));
        $type = trim((string) ($this->request->getGet('type') ?: ''));

        $rows = [];
        $summary = [
            'total' => 0,
            'planned' => 0,
            'prepared' => 0,
            'approved' => 0,
            'converted' => 0,
            'cancelled' => 0,
        ];
        $typeSummary = [];

        if ($db->tableExists('production_mrp_planned_orders')) {
            $base = $db->table('production_mrp_planned_orders po')
                ->select('po.*, r.run_no, r.from_date, r.to_date')
                ->join('production_mrp_runs r', 'r.id = po.mrp_run_id', 'left');

            if ($companyId !== null) {
                $base->where('po.company_id', $companyId);
            }
            if ($siteId !== null) {
                $base->where('po.site_id', $siteId);
            }
            if ($status !== '') {
                $base->where('po.status', $status);
            }
            if ($type !== '') {
                $base->where('po.plan_type', $type);
            }

            $rows = $base
                ->orderBy('po.status', 'ASC')
                ->orderBy('po.id', 'DESC')
                ->get(500)
                ->getResultArray();

            foreach ($rows as $row) {
                $summary['total']++;
                $s = (string) ($row['status'] ?? 'planned');
                if (array_key_exists($s, $summary)) {
                    $summary[$s]++;
                }
                $t = (string) ($row['plan_type'] ?? 'planning_task');
                $typeSummary[$t] = ($typeSummary[$t] ?? 0) + 1;
            }
            ksort($typeSummary);
        }

        return view('production/planned_released/index', [
            'title' => 'Planned Released',
            'rows' => $rows,
            'summary' => $summary,
            'typeSummary' => $typeSummary,
            'status' => $status,
            'type' => $type,
            'hasTable' => $db->tableExists('production_mrp_planned_orders'),
        ]);
    }

    private function mps(): string
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();
        $fromDate = (string) ($this->request->getGet('from_date') ?: date('Y-m-01'));
        $toDate = (string) ($this->request->getGet('to_date') ?: date('Y-m-t'));

        $rows = [];
        $summary = [
            'items' => 0,
            'qty' => 0.0,
            'with_bom' => 0,
            'without_bom' => 0,
        ];

        if ($db->tableExists('production_forecasts')) {
            $builder = $db->table('production_forecasts')
                ->select('item_code, MAX(item_name) AS item_name, MAX(uom_code) AS uom_code, SUM(qty) AS forecast_qty, MIN(forecast_date) AS first_date, MAX(forecast_date) AS last_date')
                ->where('forecast_date >=', $fromDate)
                ->where('forecast_date <=', $toDate)
                ->whereIn('status', ['draft', 'confirmed', 'approved']);

            if ($companyId !== null) {
                $builder->where('company_id', $companyId);
            }
            if ($siteId !== null) {
                $builder->where('site_id', $siteId);
            }
            if ($db->fieldExists('deleted_at', 'production_forecasts')) {
                $builder->where('deleted_at', null);
            }

            $rows = $builder
                ->groupBy('item_code')
                ->orderBy('item_code', 'ASC')
                ->get(500)
                ->getResultArray();

            foreach ($rows as $index => $row) {
                $hasBom = $this->hasActiveBom($db, $companyId, $siteId, (string) ($row['item_code'] ?? ''));
                $rows[$index]['has_bom'] = $hasBom;
                $rows[$index]['mps_qty'] = (float) ($row['forecast_qty'] ?? 0);
                $rows[$index]['suggested_action'] = $hasBom ? 'ready_for_mrp' : 'create_bom';
                $summary['qty'] += (float) ($row['forecast_qty'] ?? 0);
                $hasBom ? $summary['with_bom']++ : $summary['without_bom']++;
            }
            $summary['items'] = count($rows);
        }

        return view('production/mps/index', [
            'title' => 'Master Production Schedule',
            'rows' => $rows,
            'summary' => $summary,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }

    private function hasActiveBom($db, ?int $companyId, ?int $siteId, string $itemCode): bool
    {
        if ($itemCode === '' || ! $db->tableExists('production_boms')) {
            return false;
        }

        $builder = $db->table('production_boms')
            ->where('parent_item_code', $itemCode);

        if ($companyId !== null && $db->fieldExists('company_id', 'production_boms')) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $db->fieldExists('site_id', 'production_boms')) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if ($db->fieldExists('status', 'production_boms')) {
            $builder->whereIn('status', ['active', 'approved', 'released']);
        }
        if ($db->fieldExists('deleted_at', 'production_boms')) {
            $builder->where('deleted_at', null);
        }

        return $builder->countAllResults() > 0;
    }

    private function titleFromSlug(string $slug): string
    {
        $title = str_replace('-', ' ', trim($slug));

        return ucwords($title);
    }
}
