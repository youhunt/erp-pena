<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockBalanceModel;
use App\Models\InventoryStockMovementModel;
use App\Services\AuditLogService;
use Config\Database;
use RuntimeException;
use Throwable;

class InventoryStockService
{
    public function stockIn(array $data, ?int $userId = null): int
    {
        return $this->move($data + ['direction' => 'in'], $userId);
    }

    public function stockOut(array $data, ?int $userId = null): int
    {
        return $this->move($data + ['direction' => 'out'], $userId);
    }

    public function adjust(array $data, ?int $userId = null): int
    {
        $qty = (float) ($data['qty'] ?? 0);
        if ($qty === 0.0) {
            throw new RuntimeException('Adjustment quantity cannot be zero.');
        }

        return $this->move($data + ['direction' => $qty > 0 ? 'in' : 'out', 'qty' => abs($qty)], $userId);
    }

    public function reserve(array $data, ?int $userId = null): void
    {
        $this->changeReservation($data, abs((float) ($data['qty'] ?? 0)), $userId, 'stock.reserve');
    }

    public function releaseReservation(array $data, ?int $userId = null): void
    {
        $this->changeReservation($data, -abs((float) ($data['qty'] ?? 0)), $userId, 'stock.release_reservation');
    }

    public function move(array $data, ?int $userId = null): int
    {
        $this->validateMovement($data);

        $db = Database::connect();
        $db->transBegin();

        try {
            $balanceModel = new InventoryStockBalanceModel();
            $movementModel = new InventoryStockMovementModel();
            $balance = $this->findOrCreateBalance($balanceModel, $data);

            $direction = (string) $data['direction'];
            $qty = abs((float) $data['qty']);
            $unitCost = (float) ($data['unit_cost'] ?? 0);
            $stockValue = round($qty * $unitCost, 2);

            $oldQty = (float) $balance['qty_on_hand'];
            $oldValue = (float) $balance['stock_value'];
            $newQty = $direction === 'in' ? $oldQty + $qty : $oldQty - $qty;

            if ($newQty < 0) {
                throw new RuntimeException('Insufficient stock for item ' . $data['item_code'] . '. Current stock: ' . $oldQty);
            }

            $newValue = $direction === 'in' ? $oldValue + $stockValue : $oldValue - (($oldQty > 0 ? $oldValue / $oldQty : $unitCost) * $qty);
            $newValue = max(0.0, round($newValue, 2));
            $avgCost = $newQty > 0 ? round($newValue / $newQty, 6) : 0.0;
            $reserved = (float) $balance['qty_reserved'];

            $balanceModel->update($balance['id'], [
                'qty_on_hand' => $newQty,
                'qty_available' => $newQty - $reserved,
                'avg_cost' => $avgCost,
                'stock_value' => $newValue,
            ]);

            $movementId = (int) $movementModel->insert([
                'company_id' => $data['company_id'],
                'site_id' => $data['site_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'item_id' => $data['item_id'] ?? null,
                'item_code' => $data['item_code'],
                'item_name' => $data['item_name'] ?? null,
                'uom_code' => $data['uom_code'] ?? 'PCS',
                'movement_date' => $data['movement_date'] ?? date('Y-m-d H:i:s'),
                'movement_type' => $data['movement_type'] ?? ($direction === 'in' ? 'stock_in' : 'stock_out'),
                'direction' => $direction,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'stock_value' => $stockValue,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ], true);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post stock movement.');
            }

            $db->transCommit();

            (new AuditLogService())->log('inventory.stock', 'stock.move', [
                'company_id' => $data['company_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'inventory_stock_movements',
                'record_id' => $movementId,
                'record_code' => $data['reference_no'] ?? $data['item_code'],
                'description' => 'Inventory stock movement posted.',
                'new_values' => [
                    'movement' => $data + ['qty' => $qty, 'direction' => $direction],
                    'balance' => ['old_qty' => $oldQty, 'new_qty' => $newQty, 'avg_cost' => $avgCost],
                ],
            ]);

            return $movementId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    private function validateMovement(array $data): void
    {
        foreach (['company_id', 'item_code', 'qty', 'direction'] as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                throw new RuntimeException($field . ' is required for stock movement.');
            }
        }

        if (! in_array($data['direction'], ['in', 'out'], true)) {
            throw new RuntimeException('Stock movement direction must be in or out.');
        }

        if ((float) $data['qty'] <= 0) {
            throw new RuntimeException('Stock movement quantity must be greater than zero.');
        }
    }

    private function findOrCreateBalance(InventoryStockBalanceModel $model, array $data): array
    {
        $where = [
            'company_id' => $data['company_id'],
            'site_id' => $data['site_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'item_code' => $data['item_code'],
        ];

        $query = $model->where('company_id', $where['company_id'])
            ->where('item_code', $where['item_code']);

        foreach (['site_id', 'warehouse_id', 'location_id'] as $field) {
            $where[$field] === null ? $query->where($field, null) : $query->where($field, $where[$field]);
        }

        $balance = $query->first();
        if ($balance !== null) {
            return $balance;
        }

        $id = (int) $model->insert([
            'company_id' => $data['company_id'],
            'site_id' => $data['site_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'item_id' => $data['item_id'] ?? null,
            'item_code' => $data['item_code'],
            'uom_code' => $data['uom_code'] ?? 'PCS',
            'qty_on_hand' => 0,
            'qty_reserved' => 0,
            'qty_available' => 0,
            'avg_cost' => 0,
            'stock_value' => 0,
        ], true);

        return $model->find($id);
    }

    private function changeReservation(array $data, float $delta, ?int $userId, string $action): void
    {
        if (empty($data['company_id']) || empty($data['item_code']) || $delta === 0.0) {
            throw new RuntimeException('Company, item code, and reservation quantity are required.');
        }

        $model = new InventoryStockBalanceModel();
        $balance = $this->findOrCreateBalance($model, $data);
        $reserved = max(0.0, (float) $balance['qty_reserved'] + $delta);
        $onHand = (float) $balance['qty_on_hand'];

        if ($reserved > $onHand) {
            throw new RuntimeException('Reserved stock cannot exceed on-hand stock.');
        }

        $model->update($balance['id'], [
            'qty_reserved' => $reserved,
            'qty_available' => $onHand - $reserved,
        ]);

        (new AuditLogService())->log('inventory.stock', $action, [
            'company_id' => $data['company_id'] ?? null,
            'site_id' => $data['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'inventory_stock_balances',
            'record_id' => (int) $balance['id'],
            'record_code' => $data['item_code'],
            'description' => 'Inventory stock reservation changed.',
            'new_values' => ['delta' => $delta, 'reserved' => $reserved, 'available' => $onHand - $reserved],
        ]);
    }
}
