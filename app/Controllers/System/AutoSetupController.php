<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use App\Models\CompanyModel;
use App\Models\SiteModel;
use App\Services\Setup\CompanyAutoSetupService;
use App\Services\Setup\SiteBootstrapService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;

class AutoSetupController extends BaseController
{
    public function index(): string
    {
        $this->assertAllowed();

        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();

        return view('system/auto_setup/index', [
            'title' => 'ERP Auto Setup',
            'company' => $companyId !== null ? (new CompanyModel())->find($companyId) : null,
            'site' => $siteId !== null ? (new SiteModel())->find($siteId) : null,
            'summary' => $this->summary($companyId, $siteId),
        ]);
    }

    public function run()
    {
        $this->assertAllowed();

        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();

        if ($companyId === null) {
            return redirect()->to(site_url('system/auto-setup'))->with('error', 'Active company belum dipilih. Pilih company dulu, lalu jalankan Auto Setup.');
        }

        (new CompanyAutoSetupService())->run((int) $companyId, auth()->id());
        if ($siteId !== null) {
            (new SiteBootstrapService())->bootstrapSite((int) $siteId, auth()->id());
        }

        return redirect()->to(site_url('system/auto-setup'))->with('message', 'ERP Auto Setup selesai dijalankan untuk active company/site.');
    }

    private function assertAllowed(): void
    {
        $user = auth()->user();
        if ($user === null || (! $user->can('setup.master.manage') && ! $user->inGroup('superadmin'))) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    private function summary(?int $companyId, ?int $siteId): array
    {
        $db = Database::connect();
        if ($companyId === null) {
            return [];
        }

        return [
            'COA' => $this->count($db, 'chart_accounts', ['company_id' => $companyId]),
            'GL Book' => $this->count($db, 'gl_books', ['company_id' => $companyId]),
            'Posting Profile' => $this->count($db, 'gl_posting_profiles', ['company_id' => $companyId]),
            'UOM' => $this->count($db, 'uoms', ['company_id' => $companyId]),
            'Currency' => $this->count($db, 'currencies', []),
            'Transaction Code' => $this->count($db, 'transaction_codes', []),
            'Cash/Bank' => $this->count($db, 'cash_bank_accounts', ['company_id' => $companyId]),
            'Department' => $siteId !== null ? $this->count($db, 'departments', ['company_id' => $companyId, 'site_id' => $siteId]) : 0,
            'Warehouse' => $siteId !== null ? $this->count($db, 'warehouses', ['company_id' => $companyId, 'site_id' => $siteId]) : 0,
            'Location' => $siteId !== null ? $this->count($db, 'locations', ['company_id' => $companyId, 'site_id' => $siteId]) : 0,
        ];
    }

    private function count($db, string $table, array $where): int
    {
        if (! $db->tableExists($table)) {
            return 0;
        }

        $builder = $db->table($table);
        foreach ($where as $column => $value) {
            if ($db->fieldExists($column, $table)) {
                $builder->where($column, $value);
            }
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }

        return (int) $builder->countAllResults();
    }
}
