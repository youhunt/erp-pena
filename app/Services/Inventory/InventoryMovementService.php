<?php

namespace App\Services\Inventory;

use App\Services\Support\TenantScope;
use Config\Database;
use RuntimeException;

final class InventoryMovementService
{
    /**
     * @param array<string, mixed> $header
     * @param list<array<string, mixed>> $lines
     */
    public function post(array $header, array $lines, ?int $userId = null): int
    {
        if ($lines === []) {
            throw new RuntimeException('Inventory movement requires at least one line.');
        }

        $scope = new TenantScope();
        $companyId = (int) ($header['company_id'] ?? $scope->requireCompany());
        $siteId = $header['site_id'] ?? $scope->optionalSite();
        $movementType = trim((string) ($header['movement_type'] ?? 'adjustment'));

        $db = Database::connect();
        $db->transStart();

        $db->table('inventory_movements')->insert([
            'company_id' => $companyId,
            'site_id' => $siteId,
            'movement_no' => trim((string) ($header['movement_no'] ?? $this->generateNo('IM'))),
            'movement_date' => (string) ($header['movement_date'] ?? date('Y-m-d')),
            'movement_type' => $movementType,
            'source_type' => $header['source_type'] ?? null,
            'source_id' => $header['source_id'] ?? null,
            'warehouse_id' => $header['warehouse_id'] ?? null,
            'status' => $header['status'] ?? 'posted',
            'notes' => $header['notes'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $movementId = (int) $db->insertID();
        $validLines = 0;

        foreach ($lines as $index => $line) {
            $qtyIn = (float) ($line['qty_in'] ?? 0);
            $qtyOut = (float) ($line['qty_out'] ?? 0);

            if ($qtyIn < 0 || $qtyOut < 0) {
                throw new RuntimeException('Inventory quantity cannot be negative.');
            }

            if ($qtyIn > 0 && $qtyOut > 0) {
                throw new RuntimeException('Inventory line cannot contain both qty in and qty out.');
            }

            if ($qtyIn <= 0 && $qtyOut <= 0) {
                continue;
            }

            $unitCost = (float) ($line['unit_cost'] ?? 0);
            $amount = (float) ($line['amount'] ?? (($qtyIn > 0 ? $qtyIn : $qtyOut) * $unitCost));

            $db->table('inventory_movement_lines')->insert([
                'inventory_movement_id' => $movementId,
                'line_no' => (int) ($line['line_no'] ?? ($index + 1)),
                'item_id' => $line['item_id'] ?? null,
                'item_code' => $line['item_code'] ?? null,
                'item_name' => trim((string) ($line['item_name'] ?? $line['item_code'] ?? 'Item')),
                'uom_code' => trim((string) ($line['uom_code'] ?? 'PCS')),
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'source_line_id' => $line['source_line_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $validLines++;
        }

        if ($validLines < 1) {
            throw new RuntimeException('Inventory movement requires at least one valid quantity line.');
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new RuntimeException('Failed to post inventory movement.');
        }

        return $movementId;
    }

    private function generateNo(string $prefix): string
    {
        return $prefix . '-' . date('Ymd-His');
    }
}
