<?php

namespace App\Controllers;

use App\Services\TenantContext;
use Config\Database;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $user = auth()->user();
        $tenant = new TenantContext(session());
        $hasTenantAccess = true;
        $recentActivities = [];

        if ($user !== null) {
            $tenant->bootstrapDefaultsForUser((int) $user->id);
            $hasTenantAccess = $tenant->accessibleCompanies((int) $user->id) !== [] && ($tenant->activeCompanyId() ?? 0) > 0;
        }

        $metrics = [
            'Total Sales' => 0,
            'Total Purchase' => 0,
            'Total Invoice' => 0,
            'Pending Approval' => 0,
            'Pending OCR Review' => 0,
            'Stock Alert' => 0,
        ];

        if ($hasTenantAccess) {
            $db = Database::connect();
            $builder = $db->table('audit_logs')
                ->orderBy('created_at', 'DESC')
                ->limit(8);

            if ($tenant->activeCompanyId() !== null && $tenant->activeCompanyId() > 0) {
                $builder->groupStart()
                    ->where('company_id', $tenant->activeCompanyId())
                    ->orWhere('company_id', null)
                    ->groupEnd();
            }

            if ($tenant->activeSiteId() !== null && $tenant->activeSiteId() > 0) {
                $builder->groupStart()
                    ->where('site_id', $tenant->activeSiteId())
                    ->orWhere('site_id', null)
                    ->groupEnd();
            }

            $recentActivities = $builder->get()->getResultArray();
            $metrics['Total Invoice'] = $this->sumTenantAmount('sales_invoices', 'total_amount', $tenant);
        }

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'hasTenantAccess' => $hasTenantAccess,
            'recentActivities' => $recentActivities,
            'metrics' => $metrics,
        ]);
    }

    private function sumTenantAmount(string $table, string $field, TenantContext $tenant): float
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return 0.0;
        }

        $builder = $db->table($table)->selectSum($field, 'amount');
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $table)) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }

        return (float) ($builder->get()->getRowArray()['amount'] ?? 0);
    }
}
