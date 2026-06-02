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

        if ($hasTenantAccess) {
            $builder = Database::connect()->table('audit_logs')
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
        }

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'hasTenantAccess' => $hasTenantAccess,
            'recentActivities' => $recentActivities,
            'metrics' => [
                'Total Sales' => 0,
                'Total Purchase' => 0,
                'Total Invoice' => 0,
                'Pending Approval' => 0,
                'Pending OCR Review' => 0,
                'Stock Alert' => 0,
            ],
        ]);
    }
}
