<?php

namespace App\Controllers;

use App\Services\TenantContext;
use Config\Database;

class AuditLogController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('audit_logs');
        $keyword = trim((string) $this->request->getGet('q'));
        $module = trim((string) $this->request->getGet('module'));
        $action = trim((string) $this->request->getGet('action'));

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

        if ($module !== '') {
            $builder->where('module', $module);
        }

        if ($action !== '') {
            $builder->where('action', $action);
        }

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('module', $keyword)
                ->orLike('action', $keyword)
                ->orLike('table_name', $keyword)
                ->orLike('record_id', $keyword)
                ->orLike('record_code', $keyword)
                ->orLike('description', $keyword)
                ->groupEnd();
        }

        $logs = $builder->orderBy('created_at', 'DESC')->limit(500)->get()->getResultArray();

        return view('audit_logs/index', [
            'title' => 'Audit Logs',
            'logs' => $logs,
            'filters' => [
                'q' => $keyword,
                'module' => $module,
                'action' => $action,
            ],
            'modules' => $db->table('audit_logs')->select('module')->distinct()->orderBy('module', 'ASC')->get()->getResultArray(),
            'actions' => $db->table('audit_logs')->select('action')->distinct()->orderBy('action', 'ASC')->get()->getResultArray(),
        ]);
    }
}
