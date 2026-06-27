<?php

namespace App\Models;

use App\Services\Setup\CompanyBootstrapService;
use App\Services\Setup\SiteBootstrapService;
use CodeIgniter\Model;

class SiteModel extends Model
{
    protected $table = 'sites';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'company_id',
        'code',
        'name',
        'address',
        'is_active',
        'created_by',
        'updated_by',
    ];
    protected $useTimestamps = true;
    protected $afterInsert = ['runCompanyAndSiteBootstrap'];
    protected $afterUpdate = ['runCompanyAndSiteBootstrap'];

    protected function runCompanyAndSiteBootstrap(array $data): array
    {
        $companyIds = [];
        $siteIds = [];
        if (! empty($data['data']['company_id'])) {
            $companyIds[] = (int) $data['data']['company_id'];
        }

        $ids = $data['id'] ?? [];
        if (! is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $id) {
            $siteId = (int) $id;
            if ($siteId < 1) {
                continue;
            }
            $siteIds[] = $siteId;
            $row = $this->builder()->where('id', $siteId)->get(1)->getRowArray();
            if (! empty($row['company_id'])) {
                $companyIds[] = (int) $row['company_id'];
            }
        }

        foreach (array_unique(array_filter($companyIds)) as $companyId) {
            (new CompanyBootstrapService())->bootstrapCompany((int) $companyId);
        }

        foreach (array_unique(array_filter($siteIds)) as $siteId) {
            (new SiteBootstrapService())->bootstrapSite((int) $siteId);
        }

        return $data;
    }
}
