<?php

namespace App\Controllers;

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

        $companyId = (int) $this->request->getPost('company_id');
        $siteId = $this->request->getPost('site_id') ? (int) $this->request->getPost('site_id') : null;

        if ($siteId !== null) {
            $site = db_connect()->table('sites')
                ->where('id', $siteId)
                ->where('company_id', $companyId)
                ->get()
                ->getRowArray();

            $siteId = $site === null ? null : $siteId;
        }

        (new TenantContext(session()))->switch($companyId, $siteId);

        return redirect()->back()->with('message', 'Active company/site has been changed.');
    }
}
