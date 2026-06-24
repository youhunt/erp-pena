<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;
use App\Services\Production\MrpService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class PlanningController extends BaseController
{
    private const ACTION_STATUSES = ['open', 'in_progress', 'converted', 'closed', 'ignored'];

    private const PLANNED_ACTION_TYPES = [
        'create_purchase_requisition' => 'planned_purchase_requisition',
        'create_work_order' => 'planned_work_order',
        'create_item_master' => 'master_data_task',
        'create_bom' => 'bom_task',
        'review_service_requirement' => 'service_review',
    ];

    public function forecasts(): string
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('production_forecasts')
            ->where('company_id', $this->companyId($tenant));

        if ($tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', 'production_forecasts')) {
            $builder->where('deleted_at', null);
        }

        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $builder->groupStart()->like('item_code', $q)->orLike('item_name', $q)->orLike('forecast_no', $q)->groupEnd();
        }

        return view('production/forecasts/index', [
            'title' => 'Forecast Demand',
            'rows' => $builder->orderBy('forecast_date', 'DESC')->orderBy('item_code', 'ASC')->get(200)->getResultArray(),
            'q' => $q,
        ]);
    }

    public function newForecast(): string
    {
        return view('production/forecasts/form', [
            'title' => 'Create Forecast',
            'items' => $this->items(),
            'sites' => $this->sites(),
            'forecast' => [],
        ]);
    }

    public function storeForecast()
    {
        if (! $this->validate([
            'site_code' => 'required|max_length[20]',
            'item_code' => 'required|max_length[80]',
            'forecast_date' => 'required|valid_date[Y-m-d]',
            'qty' => 'required|decimal',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $tenant = new TenantContext(session());
        $companyId = $this->companyId($tenant);
        $siteCode = strtoupper(trim((string) $this->request->getPost('site_code')));
        $site = $this->siteByCode($siteCode, $companyId);
        if ($site === null) {
            return redirect()->back()->withInput()->with('error', 'Site code tidak valid: ' . $siteCode);
        }

        $itemCode = strtoupper(trim((string) $this->request->getPost('item_code')));
        $item = $this->itemByCode($itemCode, $companyId, (int) $site['id']);
        $now = date('Y-m-d H:i:s');
        $db = Database::connect();

        $db->table('production_forecasts')->insert([
            'company_id' => $companyId,
            'site_id' => (int) $site['id'],
            'site_code' => $siteCode,
            'forecast_no' => 'FC-' . date('Ymd-His'),
            'forecast_date' => $this->request->getPost('forecast_date'),
            'item_code' => $itemCode,
            'item_name' => $item['item_name'] ?? $item['name'] ?? trim((string) $this->request->getPost('item_name')),
            'uom_code' => strtoupper(trim((string) ($this->request->getPost('uom_code') ?: ($item['stockuom'] ?? 'PCS')))),
            'qty' => (float) $this->request->getPost('qty'),
            'source_type' => trim((string) ($this->request->getPost('source_type') ?: 'manual')),
            'status' => 'confirmed',
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()->to('/production/forecasts')->with('message', 'Forecast saved.');
    }

    public function mrp(): string
    {
        $tenant = new TenantContext(session());
        $companyId = $this->companyId($tenant);
        $db = Database::connect();
        $runs = $db->table('production_mrp_runs')
            ->where('company_id', $companyId)
            ->orderBy('id', 'DESC')
            ->get(50)
            ->getResultArray();

        return view('production/mrp/index', [
            'title' => 'MRP Planning',
            'runs' => $runs,
            'items' => $this->items(),
            'defaultFrom' => date('Y-m-01'),
            'defaultTo' => date('Y-m-t'),
        ]);
    }

    public function runMrp()
    {
        if (! $this->validate([
            'from_date' => 'required|valid_date[Y-m-d]',
            'to_date' => 'required|valid_date[Y-m-d]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $tenant = new TenantContext(session());
        try {
            $runId = (new MrpService())->generate(
                $this->companyId($tenant),
                $tenant->activeSiteId(),
                (string) $this->request->getPost('from_date'),
                (string) $this->request->getPost('to_date'),
                $this->request->getPost('item_code') !== '' ? (string) $this->request->getPost('item_code') : null,
                (int) auth()->id()
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/production/mrp/runs/' . $runId)->with('message', 'MRP generated.');
    }

    public function showMrp(int $id): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $tenant = new TenantContext(session());
        $companyId = $this->companyId($tenant);
        $db = Database::connect();
        $run = $db->table('production_mrp_runs')->where('company_id', $companyId)->where('id', $id)->get()->getRowArray();
        if ($run === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ((string) $this->request->getGet('generate_planned_orders') === '1') {
            return $this->generatePlannedOrders($db, $run, $id);
        }

        $actionLineId = (int) ($this->request->getGet('action_line_id') ?? 0);
        $actionStatus = trim((string) ($this->request->getGet('action_status') ?? ''));
        if ($actionLineId > 0 && $actionStatus !== '') {
            return $this->updateMrpActionStatus($db, $id, $actionLineId, $actionStatus);
        }

        $actionFilter = trim((string) ($this->request->getGet('action') ?? ''));
        $statusFilter = trim((string) ($this->request->getGet('status') ?? ''));

        $linesBuilder = $db->table('production_mrp_lines')
            ->where('mrp_run_id', $id);
        if ($actionFilter !== '') {
            $linesBuilder->where('suggested_action', $actionFilter);
        }
        if ($statusFilter !== '' && $db->fieldExists('action_status', 'production_mrp_lines')) {
            $linesBuilder->where('action_status', $statusFilter);
        }

        $lines = $linesBuilder
            ->orderBy('line_type', 'ASC')
            ->orderBy('net_requirement', 'DESC')
            ->get(1000)
            ->getResultArray();

        $plannedOrders = [];
        $hasPlannedOrderTable = $db->tableExists('production_mrp_planned_orders');
        if ($hasPlannedOrderTable) {
            $plannedOrders = $db->table('production_mrp_planned_orders')
                ->where('mrp_run_id', $id)
                ->orderBy('id', 'DESC')
                ->get(500)
                ->getResultArray();
        }

        return view('production/mrp/show', [
            'title' => 'MRP Run ' . ($run['run_no'] ?? '#' . $id),
            'run' => $run,
            'lines' => $lines,
            'plannedOrders' => $plannedOrders,
            'actionFilter' => $actionFilter,
            'statusFilter' => $statusFilter,
            'actionStatuses' => self::ACTION_STATUSES,
            'hasActionColumns' => $db->fieldExists('action_status', 'production_mrp_lines'),
            'hasPlannedOrderTable' => $hasPlannedOrderTable,
        ]);
    }

    private function generatePlannedOrders($db, array $run, int $runId): \CodeIgniter\HTTP\RedirectResponse
    {
        if (! $db->tableExists('production_mrp_planned_orders')) {
            return redirect()->to('/production/mrp/runs/' . $runId . '#planned-orders')->with('error', 'Run database/hosting/2026-06-24_add_mrp_planned_orders.sql first.');
        }
        if (! $db->fieldExists('action_status', 'production_mrp_lines')) {
            return redirect()->to('/production/mrp/runs/' . $runId . '#planned-orders')->with('error', 'Run MRP action plan columns SQL first.');
        }

        $lines = $db->table('production_mrp_lines')
            ->where('mrp_run_id', $runId)
            ->where('net_requirement >', 0)
            ->whereIn('suggested_action', array_keys(self::PLANNED_ACTION_TYPES))
            ->get(1000)
            ->getResultArray();

        $created = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($lines as $line) {
            $lineId = (int) ($line['id'] ?? 0);
            if ($lineId < 1) {
                continue;
            }
            $exists = $db->table('production_mrp_planned_orders')->where('mrp_line_id', $lineId)->countAllResults();
            if ($exists > 0) {
                continue;
            }

            $suggestedAction = (string) ($line['suggested_action'] ?? '');
            $planType = self::PLANNED_ACTION_TYPES[$suggestedAction] ?? 'planning_task';
            $planNo = 'MPO-' . date('YmdHis') . '-' . $lineId;

            $db->table('production_mrp_planned_orders')->insert([
                'company_id' => (int) ($line['company_id'] ?? $run['company_id'] ?? 0),
                'site_id' => $line['site_id'] ?? $run['site_id'] ?? null,
                'mrp_run_id' => $runId,
                'mrp_line_id' => $lineId,
                'plan_no' => $planNo,
                'plan_type' => $planType,
                'suggested_action' => $suggestedAction,
                'item_code' => (string) ($line['component_item_code'] ?? ''),
                'item_name' => (string) ($line['component_item_name'] ?? ''),
                'uom_code' => (string) ($line['uom_code'] ?? ''),
                'qty' => (float) ($line['net_requirement'] ?? 0),
                'status' => 'planned',
                'source_parent_item_code' => (string) ($line['parent_item_code'] ?? ''),
                'target_doc_type' => $planType,
                'target_doc_no' => null,
                'notes' => 'Generated from MRP run ' . (string) ($run['run_no'] ?? $runId),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $db->table('production_mrp_lines')
                ->where('id', $lineId)
                ->where('mrp_run_id', $runId)
                ->update([
                    'action_status' => 'in_progress',
                    'planned_doc_type' => $planType,
                    'planned_doc_no' => $planNo,
                    'action_updated_by' => auth()->id(),
                    'action_updated_at' => $now,
                ]);

            $created++;
        }

        return redirect()->to('/production/mrp/runs/' . $runId . '#planned-orders')->with('message', 'Created ' . $created . ' planned order(s).');
    }

    private function updateMrpActionStatus($db, int $runId, int $lineId, string $status): \CodeIgniter\HTTP\RedirectResponse
    {
        if (! in_array($status, self::ACTION_STATUSES, true)) {
            return redirect()->to('/production/mrp/runs/' . $runId . '#mrp-action-plan')->with('error', 'Invalid MRP action status.');
        }
        if (! $db->fieldExists('action_status', 'production_mrp_lines')) {
            return redirect()->to('/production/mrp/runs/' . $runId . '#mrp-action-plan')->with('error', 'Run database/hosting/2026-06-24_add_mrp_action_plan_columns.sql first.');
        }

        $db->table('production_mrp_lines')
            ->where('id', $lineId)
            ->where('mrp_run_id', $runId)
            ->update([
                'action_status' => $status,
                'action_updated_by' => auth()->id(),
                'action_updated_at' => date('Y-m-d H:i:s'),
            ]);

        return redirect()->to('/production/mrp/runs/' . $runId . '#mrp-action-plan')->with('message', 'MRP action status updated.');
    }

    private function companyId(TenantContext $tenant): int
    {
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            throw new RuntimeException('Active company is required.');
        }
        return (int) $companyId;
    }

    private function sites(): array
    {
        $tenant = new TenantContext(session());
        $companyId = $this->companyId($tenant);
        $builder = Database::connect()->table('sites')->where('company_id', $companyId);
        if ($tenant->activeSiteId() !== null) {
            $builder->where('id', $tenant->activeSiteId());
        }
        return $builder->orderBy('code', 'ASC')->get(200)->getResultArray();
    }

    private function items(): array
    {
        $tenant = new TenantContext(session());
        $companyId = $this->companyId($tenant);
        $builder = Database::connect()->table('items')->where('company_id', $companyId);
        if ($tenant->activeSiteId() !== null) {
            $builder->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if (Database::connect()->fieldExists('deleted_at', 'items')) {
            $builder->where('deleted_at', null);
        }
        return $builder->orderBy('item_code', 'ASC')->get(500)->getResultArray();
    }

    private function siteByCode(string $code, int $companyId): ?array
    {
        return Database::connect()->table('sites')
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->get(1)
            ->getRowArray() ?: null;
    }

    private function itemByCode(string $code, int $companyId, int $siteId): ?array
    {
        $db = Database::connect();
        $builder = $db->table('items')->where('company_id', $companyId)->where('item_code', $code)
            ->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        if ($db->fieldExists('deleted_at', 'items')) {
            $builder->where('deleted_at', null);
        }
        return $builder->orderBy('site_id', 'DESC')->get(1)->getRowArray() ?: null;
    }
}
