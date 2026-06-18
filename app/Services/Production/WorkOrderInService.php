<?php

namespace App\Services\Production;

use App\Models\ProductionWorkOrderModel;
use App\Models\ProductionWorkOrderOutputModel;
use App\Services\AuditLogService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Inventory\InventoryStockService;
use Config\Database;
use RuntimeException;
use Throwable;

class WorkOrderInService
{
    public function post(array $header, ?int $userId = null): int
    {
        foreach (['company_id', 'wo_no', 'wo_date', 'finished_item_code', 'qty_good'] as $field) {
            if (! isset($header[$field]) || $header[$field] === '') {
                throw new RuntimeException($field . ' is required.');
            }
        }

        $qtyGood = (float) ($header['qty_good'] ?? 0);
        if ($qtyGood <= 0) {
            throw new RuntimeException('Good quantity must be greater than zero.');
        }
        $period = new PeriodCloseService();
        $period->assertOpen('production', (int) $header['company_id'], (string) ($header['wo_date'] ?? date('Y-m-d')), ! empty($header['site_id']) ? (int) $header['site_id'] : null);
        $period->assertOpen('inventory', (int) $header['company_id'], (string) ($header['wo_date'] ?? date('Y-m-d')), ! empty($header['site_id']) ? (int) $header['site_id'] : null);

        $db = Database::connect();
        $db->transBegin();

        try {
            $woModel = new ProductionWorkOrderModel();
            $outputModel = new ProductionWorkOrderOutputModel();
            $stock = new InventoryStockService();

            $woModel->insert($header + [
                'production_type' => 'work_order_in',
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $woId = (int) $woModel->getInsertID();
            if ($woId < 1) {
                throw new RuntimeException('Failed to create work order in header.');
            }

            $movementId = $stock->stockIn([
                'company_id' => $header['company_id'],
                'site_id' => $header['site_id'] ?? null,
                'warehouse_id' => $header['warehouse_id'] ?? null,
                'location_id' => $header['location_id'] ?? null,
                'item_id' => $header['finished_item_id'] ?? null,
                'item_code' => $header['finished_item_code'],
                'item_name' => $header['finished_item_name'] ?? null,
                'uom_code' => $header['uom_code'] ?? 'PCS',
                'qty' => $qtyGood,
                'unit_cost' => (float) ($header['unit_cost'] ?? 0),
                'movement_date' => $header['wo_date'] ?? date('Y-m-d'),
                'movement_type' => 'work_order_in',
                'reference_type' => 'production_work_order',
                'reference_id' => $woId,
                'reference_no' => $header['wo_no'],
                'notes' => 'Finished good receipt from work order in.',
            ], $userId);

            $outputModel->insert([
                'production_work_order_id' => $woId,
                'line_no' => 10,
                'item_id' => $header['finished_item_id'] ?? null,
                'item_code' => $header['finished_item_code'],
                'item_name' => $header['finished_item_name'] ?? null,
                'qty_good' => $qtyGood,
                'qty_reject' => (float) ($header['qty_reject'] ?? 0),
                'uom_code' => $header['uom_code'] ?? 'PCS',
                'unit_cost' => (float) ($header['unit_cost'] ?? 0),
                'warehouse_id' => $header['warehouse_id'] ?? null,
                'location_id' => $header['location_id'] ?? null,
                'inventory_movement_id' => $movementId,
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post work order in.');
            }

            $db->transCommit();

            (new AuditLogService())->log('production.work_order', 'work_order_in.post', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'production_work_orders',
                'record_id' => $woId,
                'record_code' => $header['wo_no'],
                'description' => 'Work Order In posted and finished good stock increased.',
                'new_values' => $header + ['inventory_movement_id' => $movementId],
            ]);

            return $woId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }
}
