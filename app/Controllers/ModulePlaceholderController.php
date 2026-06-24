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

        $title = $this->titleFromSlug($slug);

        return view('modules/placeholder', [
            'title' => $title,
            'slug' => $slug,
        ]);
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
