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

    public function issueMaterials(int $workOrderId, ?int $userId = null): void
    {
        $woModel = new ProductionWorkOrderModel();
        $componentModel = new ProductionWorkOrderComponentModel();
        $workOrder = $woModel->find($workOrderId);
        if ($workOrder === null) {
            throw new RuntimeException('Work order not found.');
        }

        $status = (string) ($workOrder['status'] ?? 'draft');
        if (! in_array($status, ['allocated', 'partial_issued'], true)) {
            throw new RuntimeException('Only allocated or partially issued work order can be issued. Current status: ' . $status);
        }

        $components = $componentModel->where('production_work_order_id', $workOrderId)->orderBy('line_no', 'ASC')->findAll();
        if ($components === []) {
            throw new RuntimeException('Work order has no component lines.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $stock = new InventoryStockService();
            $issuedAny = false;
            $warehouseId = $this->warehouseIdByCode((string) ($workOrder['warehouse_code'] ?? ''), (int) $workOrder['company_id'], $workOrder['site_id'] ?? null);
            $locationId = $this->defaultLocationId((int) $workOrder['company_id'], $workOrder['site_id'] ?? null);

            foreach ($components as $component) {
                $allocatedQty = (float) ($component['allocated_qty'] ?? 0);
                $issuedQty = (float) ($component['issued_qty'] ?? 0);
                $toIssue = max(0.0, $allocatedQty - $issuedQty);
                if ($toIssue <= 0) {
                    continue;
                }

                $stockPayload = [
                    'company_id' => $workOrder['company_id'],
                    'site_id' => $workOrder['site_id'] ?? null,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'item_id' => $component['component_item_id'] ?? null,
                    'item_code' => $component['component_item_code'],
                    'item_name' => $component['component_item_name'] ?? null,
                    'uom_code' => $component['uom_code'] ?? 'PCS',
                    'qty' => $toIssue,
                    'movement_type' => 'production_issue',
                    'reference_type' => 'production_work_order',
                    'reference_id' => $workOrderId,
                    'reference_no' => $workOrder['wo_no'] ?? null,
                    'notes' => 'Work order material issue.',
                ];

                $stock->releaseReservation($stockPayload, $userId);
                $stock->stockOut($stockPayload, $userId);

                $newIssued = $issuedQty + $toIssue;
                $bookingQty = (float) ($component['booking_qty'] ?? $component['qty_used'] ?? 0);
                $componentModel->update($component['id'], [
                    'issued_qty' => $newIssued,
                    'line_status' => $newIssued >= $bookingQty ? 'issued' : 'partial_issued',
                ]);
                $issuedAny = true;
            }

            if (! $issuedAny) {
                throw new RuntimeException('No allocated component quantity can be issued.');
            }

            $this->refreshIssueStatus($workOrderId, $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to issue work order material.');
            }
            $db->transCommit();

            (new AuditLogService())->log('production.wo', 'wo.issue_material', [
                'company_id' => $workOrder['company_id'] ?? null,
                'site_id' => $workOrder['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'production_work_orders',
                'record_id' => $workOrderId,
                'record_code' => $workOrder['wo_no'] ?? null,
                'description' => 'Work order components issued to production.',
            ]);
        } catch (RuntimeException $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function receiveFinishedGoods(int $workOrderId, ?float $qty = null, ?int $userId = null): void
    {
        $woModel = new ProductionWorkOrderModel();
        $workOrder = $woModel->find($workOrderId);
        if ($workOrder === null) {
            throw new RuntimeException('Work order not found.');
        }

        $status = (string) ($workOrder['status'] ?? 'draft');
        if (! in_array($status, ['material_issued', 'partial_finished'], true)) {
            throw new RuntimeException('Only material issued or partially finished work order can be received. Current status: ' . $status);
        }

        $standardQty = (float) ($workOrder['std_qty_finished'] ?? $workOrder['wo_qty'] ?? 0);
        $actualQty = (float) ($workOrder['act_qty_finished'] ?? 0);
        $remainingQty = max(0.0, $standardQty - $actualQty);
        $receiveQty = $qty === null ? $remainingQty : (float) $qty;

        if ($receiveQty <= 0) {
            throw new RuntimeException('Finished good receive quantity must be greater than zero.');
        }
        if ($receiveQty > $remainingQty) {
            throw new RuntimeException(sprintf(
                'Finished good receive quantity exceeds remaining quantity. Remaining: %s.',
                number_format($remainingQty, 6)
            ));
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $item = $this->itemByCode((string) $workOrder['parent_item_code']);
            $warehouseId = $this->warehouseIdByCode((string) ($workOrder['warehouse_code'] ?? ''), (int) $workOrder['company_id'], $workOrder['site_id'] ?? null);
            $locationId = $this->defaultLocationId((int) $workOrder['company_id'], $workOrder['site_id'] ?? null);

            (new InventoryStockService())->stockIn([
                'company_id' => $workOrder['company_id'],
                'site_id' => $workOrder['site_id'] ?? null,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'item_id' => $workOrder['parent_item_id'] ?? $item['id'] ?? null,
                'item_code' => $workOrder['parent_item_code'],
                'item_name' => $workOrder['parent_item_name'] ?? $item['item_name'] ?? $item['name'] ?? null,
                'uom_code' => $item['stockuom'] ?? $item['stock_uom'] ?? 'PCS',
                'qty' => $receiveQty,
                'unit_cost' => (float) ($item['standard_cost'] ?? $item['item_price'] ?? 0),
                'movement_type' => 'production_receipt',
                'reference_type' => 'production_work_order',
                'reference_id' => $workOrderId,
                'reference_no' => $workOrder['wo_no'] ?? null,
                'notes' => 'Work order finished good receipt.',
            ], $userId);

            $newActualQty = $actualQty + $receiveQty;
            $woModel->update($workOrderId, [
                'act_qty_finished' => $newActualQty,
                'status' => $newActualQty >= $standardQty ? 'finished' : 'partial_finished',
                'updated_by' => $userId,
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to receive work order finished good.');
            }
            $db->transCommit();

            (new AuditLogService())->log('production.wo', 'wo.receive_finished', [
                'company_id' => $workOrder['company_id'] ?? null,
                'site_id' => $workOrder['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'production_work_orders',
                'record_id' => $workOrderId,
                'record_code' => $workOrder['wo_no'] ?? null,
                'description' => 'Work order finished good received to inventory.',
                'new_values' => ['received_qty' => $receiveQty, 'act_qty_finished' => $newActualQty],
            ]);
        } catch (RuntimeException $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function issueAndReceive(int $workOrderId, ?float $receiveQty = null, ?int $userId = null): void
    {
        $workOrder = (new ProductionWorkOrderModel())->find($workOrderId);
        if ($workOrder === null) {
            throw new RuntimeException('Work order not found.');
        }

        $status = (string) ($workOrder['status'] ?? 'draft');
        if (! in_array($status, ['allocated', 'partial_issued', 'material_issued', 'partial_finished'], true)) {
            throw new RuntimeException('Only allocated, issued, or partially finished work order can be processed as In Out. Current status: ' . $status);
        }

        if (in_array($status, ['allocated', 'partial_issued'], true)) {
            $this->issueMaterials($workOrderId, $userId);
        }

        $this->receiveFinishedGoods($workOrderId, $receiveQty, $userId);

        (new AuditLogService())->log('production.wo', 'wo.issue_receive', [
            'company_id' => $workOrder['company_id'] ?? null,
            'site_id' => $workOrder['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'production_work_orders',
            'record_id' => $workOrderId,
            'record_code' => $workOrder['wo_no'] ?? null,
            'description' => 'Work order material issue and finished good receipt processed together.',
        ]);
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

    private function refreshIssueStatus(int $workOrderId, ?int $userId): void
    {
        $components = (new ProductionWorkOrderComponentModel())->where('production_work_order_id', $workOrderId)->findAll();
        $required = 0.0;
        $issued = 0.0;
        foreach ($components as $component) {
            $required += (float) ($component['booking_qty'] ?? 0);
            $issued += (float) ($component['issued_qty'] ?? 0);
        }

        $status = $issued >= $required && $required > 0 ? 'material_issued' : 'partial_issued';
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

    private function itemByCode(string $code): ?array
    {
        if ($code === '') {
            return null;
        }

        $db = Database::connect();
        if (! $db->tableExists('items')) {
            return null;
        }

        $builder = $db->table('items');
        $db->fieldExists('item_code', 'items') ? $builder->where('item_code', $code) : $builder->where('code', $code);

        return $builder->get()->getRowArray();
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
