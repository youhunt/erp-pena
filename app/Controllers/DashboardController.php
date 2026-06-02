<?php

namespace App\Controllers;

use App\Services\TenantContext;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $user = auth()->user();
        $tenant = new TenantContext(session());
        $hasTenantAccess = true;

        if ($user !== null) {
            $tenant->bootstrapDefaultsForUser((int) $user->id);
            $hasTenantAccess = $tenant->accessibleCompanies((int) $user->id) !== [] && ($tenant->activeCompanyId() ?? 0) > 0;
        }

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'hasTenantAccess' => $hasTenantAccess,
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
