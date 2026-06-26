<?php

namespace App\Models;

use App\Services\Setup\CompanyBootstrapService;
use CodeIgniter\Model;

class CompanyModel extends Model
{
    protected $table = 'companies';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'code',
        'name',
        'legal_name',
        'tax_number',
        'base_currency',
        'address',
        'is_active',
        'created_by',
        'updated_by',
    ];
    protected $useTimestamps = true;
    protected $afterInsert = ['runCoreDefaultsBootstrap'];
    protected $afterUpdate = ['runCoreDefaultsBootstrap'];

    protected function runCoreDefaultsBootstrap(array $data): array
    {
        $ids = $data['id'] ?? [];
        if (! is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $id) {
            $companyId = (int) $id;
            if ($companyId > 0) {
                (new CompanyBootstrapService())->bootstrapCompany($companyId);
            }
        }

        return $data;
    }
}
