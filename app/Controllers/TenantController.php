<?php

namespace App\Controllers;

use App\Services\AuditLogService;
use App\Services\TenantContext;

class TenantController extends BaseController
{
    public function switch()
    {
        $rules = [
            'company_id' => 'required|is_natural_no_zero',
            'site_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userId = (int) auth()->id();
        $companyId = (int) $this->request->getPost('company_id');
        $siteId = $this->request->getPost('site_id') ? (int) $this->request->getPost('site_id') : null;
        $tenant = new TenantContext(session());

        if (! $tenant->userCanAccessCompany($userId, $companyId)) {
            return redirect()->back()->with('error', 'You do not have access to the selected company.');
        }

        if (! $tenant->userCanAccessSite($userId, $companyId, $siteId)) {
            return redirect()->back()->with('error', 'You do not have access to the selected site.');
        }

        $tenant->switch($companyId, $siteId);

        (new AuditLogService())->log('tenant', 'tenant.switch', [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'description' => 'User switched active company/site.',
            'new_values' => [
                'company_id' => $companyId,
                'site_id' => $siteId,
            ],
        ]);

        session()->setFlashdata('message', 'Active company/site has been changed.');
        session()->close();

        return redirect()->to(previous_url() ?: site_url('dashboard'));
    }
}
