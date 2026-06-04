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
}
