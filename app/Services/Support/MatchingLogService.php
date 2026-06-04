<?php

namespace App\Services\Support;

use Config\Database;
use RuntimeException;

final class MatchingLogService
{
    public function log(
        string $matchType,
        string $leftType,
        int $leftId,
        string $rightType,
        int $rightId,
        float $amount = 0,
        ?string $notes = null,
        ?int $userId = null
    ): int {
        if ($leftId < 1 || $rightId < 1) {
            throw new RuntimeException('Matching log requires both document IDs.');
        }

        $scope = new TenantScope();
        $companyId = $scope->requireCompany();

        $db = Database::connect();
        $db->table('matching_logs')->insert([
            'company_id' => $companyId,
            'site_id' => $scope->optionalSite(),
            'match_type' => $matchType,
            'left_type' => $leftType,
            'left_id' => $leftId,
            'right_type' => $rightType,
            'right_id' => $rightId,
            'matched_amount' => $amount,
            'status' => 'matched',
            'notes' => $notes,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }
}
