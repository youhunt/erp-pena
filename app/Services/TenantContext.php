<?php

namespace App\Services;

use App\Models\CompanyModel;
use App\Models\SiteModel;
use CodeIgniter\Session\Session;

class TenantContext
{
    public function __construct(
        private readonly Session $session,
        private readonly CompanyModel $companies = new CompanyModel(),
        private readonly SiteModel $sites = new SiteModel(),
    ) {
    }

    public function activeCompanyId(): ?int
    {
        $companyId = $this->session->get('active_company_id');

        return $companyId === null ? null : (int) $companyId;
    }

    public function activeSiteId(): ?int
    {
        $siteId = $this->session->get('active_site_id');

        return $siteId === null ? null : (int) $siteId;
    }

    public function switch(int $companyId, ?int $siteId = null): void
    {
        $this->session->set('active_company_id', $companyId);
        $this->session->set('active_site_id', $siteId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accessibleCompanies(int $userId): array
    {
        $rows = db_connect()->table('user_company_access access')
            ->select('companies.*')
            ->join('companies', 'companies.id = access.company_id')
            ->where('access.user_id', $userId)
            ->where('companies.deleted_at', null)
            ->orderBy('access.is_default', 'DESC')
            ->orderBy('companies.code', 'ASC')
            ->get()
            ->getResultArray();

        return $rows ?: $this->companies->where('is_active', 1)->orderBy('code', 'ASC')->findAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accessibleSites(int $userId, ?int $companyId = null): array
    {
        $companyId ??= $this->activeCompanyId();

        if ($companyId === null) {
            return [];
        }

        $rows = db_connect()->table('user_site_access access')
            ->select('sites.*')
            ->join('sites', 'sites.id = access.site_id')
            ->where('access.user_id', $userId)
            ->where('access.company_id', $companyId)
            ->where('sites.deleted_at', null)
            ->orderBy('access.is_default', 'DESC')
            ->orderBy('sites.code', 'ASC')
            ->get()
            ->getResultArray();

        return $rows ?: $this->sites->where('company_id', $companyId)->where('is_active', 1)->orderBy('code', 'ASC')->findAll();
    }

    public function bootstrapDefaultsForUser(int $userId): void
    {
        if ($this->activeCompanyId() !== null) {
            return;
        }

        $db = db_connect();
        $access = $db->table('user_company_access')
            ->where('user_id', $userId)
            ->orderBy('is_default', 'DESC')
            ->get()
            ->getRowArray();

        if ($access === null) {
            $company = $this->companies->where('is_active', 1)->first();
            $site = $company === null ? null : $this->sites->where('company_id', $company['id'])->where('is_active', 1)->first();
            $this->switch((int) ($company['id'] ?? 0), isset($site['id']) ? (int) $site['id'] : null);

            return;
        }

        $site = $db->table('user_site_access')
            ->where('user_id', $userId)
            ->where('company_id', $access['company_id'])
            ->orderBy('is_default', 'DESC')
            ->get()
            ->getRowArray();

        $this->switch((int) $access['company_id'], isset($site['site_id']) ? (int) $site['site_id'] : null);
    }
}
