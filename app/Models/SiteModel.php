<?php

namespace App\Models;

use App\Services\Setup\CompanyBootstrapService;
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
    protected $afterInsert = ['runCompanyBootstrap'];
    protected $afterUpdate = ['runCompanyBootstrap'];

    protected function runCompanyBootstrap(array $data): array
    {
        $companyIds = [];
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
            $row = $this->builder()->where('id', $siteId)->get(1)->getRowArray();
            if (! empty($row['company_id'])) {
                $companyIds[] = (int) $row['company_id'];
            }
        }

        foreach (array_unique(array_filter($companyIds)) as $companyId) {
            (new CompanyBootstrapService())->bootstrapCompany((int) $companyId);
        }

        return $data;
    }
}
