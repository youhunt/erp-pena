<?php

namespace App\Services\Production;

use App\Models\ProductionBomLineModel;
use App\Models\ProductionBomModel;
use App\Models\ProductionRoutingLineModel;
use App\Models\ProductionRoutingModel;
use App\Models\ProductionWorkCenterModel;
use App\Models\ProductionWorkOrderComponentModel;
use App\Models\ProductionWorkOrderModel;
use App\Models\ProductionWorkOrderRoutingModel;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryStockService;
use Config\Database;
use RuntimeException;

class WorkOrderService
{
    public function create(array $header, ?int $userId = null): int
    {
        foreach (['company_id', 'wo_no', 'wo_date', 'site_code', 'department_code', 'parent_item_code', 'wo_qty'] as $field) {
            if (! isset($header[$field]) || $header[$field] === '') {
                throw new RuntimeException($field . ' is required.');
            }
        }

        $bom = (new ProductionBomModel())
            ->where('company_id', $header['company_id'])
            ->where('site_code', $header['site_code'])
            ->where('parent_item_code', $header['parent_item_code'])
            ->first();
        if ($bom === null) {
            throw new RuntimeException('BOM not found for parent item ' . $header['parent_item_code'] . '.');
        }

        $routing = (new ProductionRoutingModel())
            ->where('company_id', $header['company_id'])
            ->where('site_code', $header['site_code'])
            ->where('item_code', $header['parent_item_code'])
            ->first();

        $db = Database::connect();
        $db->transBegin();

        try {
            $woModel = new ProductionWorkOrderModel();
            $componentModel = new ProductionWorkOrderComponentModel();
            $routingModel = new ProductionWorkOrderRoutingModel();

            $woQty = (float) $header['wo_qty'];
            $batchQty = max(1.0, (float) ($bom['qty_batch'] ?? 1));
            $scale = $woQty / $batchQty;

            $woId = (int) $woModel->insert($header + [
                'bom_id' => $bom['id'],
                'routing_id' => $routing['id'] ?? null,
                'batch_qty' => $batchQty,
                'std_qty_finished' => $woQty,
                'act_qty_finished' => 0,
                'status' => 'draft',
                'created_by' => $userId,
                'updated_by' => $userId,
            ], true);

            foreach ((new ProductionBomLineModel())->where('production_bom_id', $bom['id'])->orderBy('child_no', 'ASC')->findAll() as $line) {
                $qty = round((float) ($line['qty_used'] ?? 0) * $scale, 12);
                $componentModel->insert([
                    'production_work_order_id' => $woId,
                    'line_no' => $line['child_no'],
                    'component_item_id' => $line['child_item_id'] ?? null,
                    'component_item_code' => $line['child_item_code'],
                    'component_item_name' => $line['child_item_name'] ?? null,
                    'qty_used' => $qty,
                    'uom_code' => $line['uom_code'],
                    'warehouse_code' => $header['warehouse_code'] ?? $bom['warehouse_code'] ?? null,
                    'location_code' => null,
                    'batch_no' => null,
                    'booking_qty' => $qty,
                    'allocated_qty' => 0,
                    'issued_qty' => 0,
                    'line_status' => 'open',
                ]);
            }

            if ($routing !== null) {
                foreach ((new ProductionRoutingLineModel())->where('production_routing_id', $routing['id'])->orderBy('route_no', 'ASC')->findAll() as $line) {
                    $workCenter = (new ProductionWorkCenterModel())
                        ->where('company_id', $header['company_id'])
                        ->where('work_center_code', $line['work_center_code'])
                        ->first();
                    $routingModel->insert([
                        'production_work_order_id' => $woId,
                        'line_no' => (int) $line['route_no'],
                        'routing_name' => $line['routing_name'] ?? null,
                        'work_center_code' => $line['work_center_code'],
                        'work_center_name' => $workCenter['description'] ?? $line['work_center_code'],
                        'hour_qty' => round((float) ($line['hour_qty'] ?? 0) * $scale, 8),
                        'uom_code' => $line['hour_uom'] ?? 'Hour',
                    ]);
                }
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to save work order.');
            }
            $db->transCommit();

            (new AuditLogService())->log('production.wo', 'wo.create', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'production_work_orders',
                'record_id' => $woId,
                'record_code' => $header['wo_no'],
                'description' => 'Production work order created from BOM and routing.',
            ]);

            return $woId;
        } catch (RuntimeException $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function allocate(int $workOrderId, ?int $userId = null): void
    {
        $woModel = new ProductionWorkOrderModel();
        $componentModel = new ProductionWorkOrderComponentModel();
        $workOrder = $woModel->find($workOrderId);
        if ($workOrder === null) {
            throw new RuntimeException('Work order not found.');
        }

        $status = (string) ($workOrder['status'] ?? 'draft');
        if (! in_array($status, ['draft', 'partial_allocated'], true)) {
            throw new RuntimeException('Only draft or partially allocated work order can be allocated. Current status: ' . $status);
        }

        $components = $componentModel->where('production_work_order_id', $workOrderId)->orderBy('line_no', 'ASC')->findAll();
        if ($components === []) {
            throw new RuntimeException('Work order has no component lines.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $stock = new InventoryStockService();
            $allocatedAny = false;
            $warehouseId = $this->warehouseIdByCode((string) ($workOrder['warehouse_code'] ?? ''), (int) $workOrder['company_id'], $workOrder['site_id'] ?? null);
            $locationId = $this->defaultLocationId((int) $workOrder['company_id'], $workOrder['site_id'] ?? null);

            foreach ($components as $component) {
                $bookingQty = (float) ($component['booking_qty'] ?? $component['qty_used'] ?? 0);
                $allocatedQty = (float) ($component['allocated_qty'] ?? 0);
                $toAllocate = max(0.0, $bookingQty - $allocatedQty);
                if ($toAllocate <= 0) {
                    continue;
                }

                $availableQty = $this->availableStockQty(
                    (int) $workOrder['company_id'],
                    $workOrder['site_id'] ?? null,
                    $warehouseId,
                    $locationId,
                    (string) $component['component_item_code']
                );
                if ($availableQty < $toAllocate) {
                    throw new RuntimeException(sprintf(
                        'Insufficient component stock for %s. Required: %s, available: %s.',
                        $component['component_item_code'],
                        number_format($toAllocate, 6),
                        number_format($availableQty, 6)
                    ));
                }

                $stock->reserve([
                    'company_id' => $workOrder['company_id'],
                    'site_id' => $workOrder['site_id'] ?? null,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'item_id' => $component['component_item_id'] ?? null,
                    'item_code' => $component['component_item_code'],
                    'uom_code' => $component['uom_code'] ?? 'PCS',
                    'qty' => $toAllocate,
                ], $userId);

                $newAllocated = $allocatedQty + $toAllocate;
                $componentModel->update($component['id'], [
                    'allocated_qty' => $newAllocated,
                    'line_status' => $newAllocated >= $bookingQty ? 'allocated' : 'partial_allocated',
                ]);
                $allocatedAny = true;
            }

            if (! $allocatedAny) {
                throw new RuntimeException('No component quantity can be allocated.');
            }

            $this->refreshAllocationStatus($workOrderId, $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to allocate work order.');
            }
            $db->transCommit();

            (new AuditLogService())->log('production.wo', 'wo.allocate', [
                'company_id' => $workOrder['company_id'] ?? null,
                'site_id' => $workOrder['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'production_work_orders',
                'record_id' => $workOrderId,
                'record_code' => $workOrder['wo_no'] ?? null,
                'description' => 'Work order components allocated to inventory reservation.',
            ]);
        } catch (RuntimeException $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function refreshAllocationStatus(int $workOrderId, ?int $userId): void
    {
        $components = (new ProductionWorkOrderComponentModel())->where('production_work_order_id', $workOrderId)->findAll();
        $required = 0.0;
        $allocated = 0.0;
        foreach ($components as $component) {
            $required += (float) ($component['booking_qty'] ?? 0);
            $allocated += (float) ($component['allocated_qty'] ?? 0);
        }

        $status = $allocated >= $required && $required > 0 ? 'allocated' : 'partial_allocated';
        (new ProductionWorkOrderModel())->update($workOrderId, [
            'status' => $status,
            'updated_by' => $userId,
        ]);
    }

    private function warehouseIdByCode(string $code, int $companyId, mixed $siteId): ?int
    {
        if ($code === '') {
            return null;
        }

        $builder = Database::connect()->table('warehouses')
            ->where('company_id', $companyId)
            ->where('code', $code);

        if ($siteId !== null) {
            $builder->where('site_id', $siteId);
        }

        $row = $builder->get()->getRowArray();

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    private function defaultLocationId(int $companyId, mixed $siteId): ?int
    {
        $db = Database::connect();
        if (! $db->tableExists('locations')) {
            return null;
        }

        $builder = $db->table('locations')->where('company_id', $companyId);
        if ($siteId !== null && $db->fieldExists('site_id', 'locations')) {
            $builder->where('site_id', $siteId);
        }
        if ($db->fieldExists('is_active', 'locations')) {
            $builder->where('is_active', 1);
        }

        $row = $builder->orderBy($db->fieldExists('code', 'locations') ? 'code' : 'id', 'ASC')->get()->getRowArray();

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    private function availableStockQty(int $companyId, mixed $siteId, ?int $warehouseId, ?int $locationId, string $itemCode): float
    {
        $db = Database::connect();
        if (! $db->tableExists('inventory_stock_balances')) {
            return 0.0;
        }

        $builder = $db->table('inventory_stock_balances')
            ->where('company_id', $companyId)
            ->where('item_code', $itemCode);

        foreach ([
            'site_id' => $siteId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
        ] as $field => $value) {
            $value === null ? $builder->where($field, null) : $builder->where($field, $value);
        }

        $row = $builder->get()->getRowArray();
        if ($row === null) {
            return 0.0;
        }

        if (array_key_exists('qty_available', $row)) {
            return (float) $row['qty_available'];
        }

        return (float) ($row['qty_on_hand'] ?? 0) - (float) ($row['qty_reserved'] ?? 0);
    }
}
