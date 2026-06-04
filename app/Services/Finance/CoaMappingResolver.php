<?php

namespace App\Services\Finance;

use App\Services\Support\TenantScope;
use Config\Database;
use RuntimeException;

final class CoaMappingResolver
{
    public function account(string $mappingType, string $mappingKey): array
    {
        $scope = new TenantScope();
        $row = Database::connect()->table('coa_mappings')
            ->where('company_id', $scope->requireCompany())
            ->where('mapping_type', trim($mappingType))
            ->where('mapping_key', trim($mappingKey))
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException('COA mapping not found.');
        }

        return [
            'account_code' => (string) $row['account_code'],
            'account_name' => $row['account_name'] ?? null,
        ];
    }
}
