<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Models\PeriodCloseModel;
use App\Services\Finance\PeriodCloseService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class PeriodCloseController extends BaseController
{
    public function index(?string $module = null): string
    {
        $tenant = new TenantContext(session());
        $module = $module !== null ? strtolower($module) : null;
        $model = new PeriodCloseModel();
        $this->scope($model, $tenant);
        if ($module !== null && array_key_exists($module, PeriodCloseService::modules())) {
            $model->where('module_code', $module);
        }

        return view('finance/period_close/index', [
            'title' => 'Period Close',
            'module' => $module,
            'modules' => PeriodCloseService::modules(),
            'periods' => $model->orderBy('period', 'DESC')->orderBy('module_code', 'ASC')->findAll(200),
        ]);
    }

    public function create(?string $module = null): string
    {
        $module = $module !== null && array_key_exists($module, PeriodCloseService::modules()) ? $module : 'gl';

        return view('finance/period_close/form', [
            'title' => 'Close Period',
            'module' => $module,
            'modules' => PeriodCloseService::modules(),
        ]);
    }

    public function store()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (! $this->validate([
            'module_code' => 'required|max_length[40]',
            'period' => 'required|regex_match[/^\d{4}-\d{2}$/]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $id = (new PeriodCloseService())->close([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'module_code' => (string) $this->request->getPost('module_code'),
                'period' => (string) $this->request->getPost('period'),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/period-close/' . $id)->with('message', 'Period closed.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new PeriodCloseModel();
        $this->scope($model, $tenant);
        $period = $model->find($id);
        if ($period === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/period_close/show', [
            'title' => 'Period Close Detail',
            'period' => $period,
            'modules' => PeriodCloseService::modules(),
            'canReopen' => $tenant->activeSiteId() === null
                || (int) ($period['site_id'] ?? 0) === (int) $tenant->activeSiteId(),
        ]);
    }

    public function reopen(int $id)
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->with('error', 'Active company is required.');
        }

        $model = new PeriodCloseModel();
        $this->scope($model, $tenant);
        $period = $model->find($id);
        if ($period === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ($tenant->activeSiteId() !== null && empty($period['site_id'])) {
            return redirect()->back()->with('error', 'Switch to All Sites before reopening a company-wide period.');
        }

        try {
            (new PeriodCloseService())->reopen(
                $id,
                $companyId,
                ! empty($period['site_id']) ? (int) $period['site_id'] : null,
                auth()->id()
            );
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->to('/period-close/' . $id)->with('message', 'Period reopened.');
    }

    private function scope($model, TenantContext $tenant): void
    {
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        }
    }
}
