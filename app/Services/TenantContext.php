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
            ->where('companies.is_active', 1)
            ->orderBy('access.is_default', 'DESC')
            ->orderBy('companies.code', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows !== []) {
            return $rows;
        }

        return $this->isSuperadmin($userId)
            ? $this->companies->where('is_active', 1)->orderBy('code', 'ASC')->findAll()
            : [];
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
            ->where('sites.is_active', 1)
            ->orderBy('access.is_default', 'DESC')
            ->orderBy('sites.code', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows !== []) {
            return $rows;
        }

        return $this->isSuperadmin($userId)
            ? $this->sites->where('company_id', $companyId)->where('is_active', 1)->orderBy('code', 'ASC')->findAll()
            : [];
    }

    public function userCanAccessCompany(int $userId, int $companyId): bool
    {
        if ($companyId < 1) {
            return false;
        }

        if ($this->isSuperadmin($userId)) {
            return $this->companies->where('id', $companyId)->where('is_active', 1)->first() !== null;
        }

        return db_connect()->table('user_company_access')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->countAllResults() > 0;
    }

    public function userCanAccessSite(int $userId, int $companyId, ?int $siteId): bool
    {
        if ($siteId === null) {
            return true;
        }

        $site = $this->sites
            ->where('id', $siteId)
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->first();

        if ($site === null) {
            return false;
        }

        if ($this->isSuperadmin($userId)) {
            return true;
        }

        return db_connect()->table('user_site_access')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->countAllResults() > 0;
    }

    public function bootstrapDefaultsForUser(int $userId): void
    {
        if ($this->activeCompanyId() !== null && $this->userCanAccessCompany($userId, (int) $this->activeCompanyId())) {
            return;
        }

        $companies = $this->accessibleCompanies($userId);
        if ($companies === []) {
            $this->switch(0, null);

            return;
        }

        $companyId = (int) $companies[0]['id'];
        $sites = $this->accessibleSites($userId, $companyId);
        $siteId = isset($sites[0]['id']) ? (int) $sites[0]['id'] : null;

        $this->switch($companyId, $siteId);
    }

    private function isSuperadmin(int $userId): bool
    {
        return db_connect()->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', 'superadmin')
            ->countAllResults() > 0;
    }
}
