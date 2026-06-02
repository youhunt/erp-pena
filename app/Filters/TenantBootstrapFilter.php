<?php

namespace App\Filters;

use App\Services\TenantContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class TenantBootstrapFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! function_exists('auth') || ! auth()->loggedIn()) {
            return null;
        }

        $userId = (int) auth()->id();
        if ($userId < 1) {
            return null;
        }

        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();

        if ($companyId === null || $companyId < 1 || ! $tenant->userCanAccessCompany($userId, $companyId)) {
            $tenant->bootstrapDefaultsForUser($userId);

            return null;
        }

        if (! $tenant->userCanAccessSite($userId, $companyId, $siteId)) {
            $sites = $tenant->accessibleSites($userId, $companyId);
            $tenant->switch($companyId, isset($sites[0]['id']) ? (int) $sites[0]['id'] : null);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
