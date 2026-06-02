<?php

namespace App\Controllers;

use App\Services\TenantContext;
use Config\Database;

class AuditLogController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $builder = Database::connect()->table('audit_logs');

        if ($tenant->activeCompanyId() !== null) {
            $builder->groupStart()
                ->where('company_id', $tenant->activeCompanyId())
                ->orWhere('company_id', null)
                ->groupEnd();
        }

        if ($tenant->activeSiteId() !== null) {
            $builder->groupStart()
                ->where('site_id', $tenant->activeSiteId())
                ->orWhere('site_id', null)
                ->groupEnd();
        }

        return view('audit_logs/index', [
            'title' => 'Audit Logs',
            'logs' => $builder->orderBy('created_at', 'DESC')->limit(200)->get()->getResultArray(),
        ]);
    }
}
