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

    public function showMrp(int $id): string
    {
        $tenant = new TenantContext(session());
        $companyId = $this->companyId($tenant);
        $db = Database::connect();
        $run = $db->table('production_mrp_runs')->where('company_id', $companyId)->where('id', $id)->get()->getRowArray();
        if ($run === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $lines = $db->table('production_mrp_lines')
            ->where('mrp_run_id', $id)
            ->orderBy('line_type', 'ASC')
            ->orderBy('net_requirement', 'DESC')
            ->get(1000)
            ->getResultArray();

        return view('production/mrp/show', [
            'title' => 'MRP Run ' . ($run['run_no'] ?? '#' . $id),
            'run' => $run,
            'lines' => $lines,
        ]);
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
