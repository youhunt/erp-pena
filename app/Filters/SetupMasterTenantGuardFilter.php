<?php

namespace App\Filters;

use App\Services\TenantContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class SetupMasterTenantGuardFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return null;
        }

        $path = trim((string) $request->getUri()->getPath(), '/');
        if (! str_starts_with($path, 'setup/')) {
            return null;
        }

        if (str_contains($path, '/sync') || str_contains($path, '/import') || str_contains($path, '/delete')) {
            return null;
        }

        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return null;
        }

        $db = Database::connect();
        $companyCode = (string) ($db->table('companies')->select('code')->where('id', $companyId)->get()->getRowArray()['code'] ?? '');
        $siteId = $tenant->activeSiteId();
        $siteCode = '';
        if ($siteId !== null && $siteId > 0) {
            $siteCode = (string) ($db->table('sites')->select('code')->where('id', $siteId)->get()->getRowArray()['code'] ?? '');
        }

        $post = $request->getPost() ?? [];
        $post['company'] = $companyCode;
        $post['site'] = $siteCode;

        if (method_exists($request, 'setGlobal')) {
            $request->setGlobal('post', $post);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
