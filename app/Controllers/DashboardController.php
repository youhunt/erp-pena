<?php

namespace App\Controllers;

use App\Services\TenantContext;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $user = auth()->user();

        if ($user !== null) {
            (new TenantContext(session()))->bootstrapDefaultsForUser((int) $user->id);
        }

        return view('dashboard/index', [
            'title' => 'Dashboard',
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
