<?php

namespace App\Services\Sales;

use App\Models\AllocationLineModel;
use App\Models\AllocationOrderModel;
use App\Models\GlEntryLineModel;
use App\Models\InventoryStockMovementModel;
use App\Models\SalesDeliveryLineModel;
use App\Models\SalesDeliveryModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\AuditLogService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Finance\PostingProfileService;
use App\Services\Inventory\InventoryStockService;
use App\Services\Support\PostingIntegrityGuard;
use App\Services\Support\TransactionDocumentGuard;
use Config\Database;
use RuntimeException;
use Throwable;

class AllocationDeliveryService
{
    /**
     * @param array<string,mixed> $header
     * @param array<int,array<string,mixed>> $lines
     */
    public function postFromAllocation(int $allocationId, array $header, array $lines, ?int $userId = null): int
    {
        if (empty($header['company_id']) || empty($header['sales_order_id']) || empty($header['delivery_no'])) {
            throw new RuntimeException('Company, SO, and delivery number are required.');
        }
        if ($allocationId < 1) {
            throw new RuntimeException('Allocation order is required before delivery.');
        }
        if ($lines === []) {
            throw new RuntimeException('At least one allocation delivery line is required.');
        }

        $db = Database::connect();
        $allocation = (new AllocationOrderModel())->find($allocationId);
        if ($allocation === null) {
            throw new RuntimeException('Allocation order not found.');
        }
        if ((int) ($allocation['sales_order_id'] ?? 0) !== (int) $header['sales_order_id']) {
            throw new RuntimeException('Allocation order does not belong to selected Sales Order.');
        }
        if (! in_array((string) ($allocation['status'] ?? 'posted'), ['posted', 'partial_delivered'], true)) {
            throw new RuntimeException('Only posted or partial delivered allocation can be delivered. Current status: ' . ($allocation['status'] ?? '-'));
        }

        $soModel = new SalesOrderModel();
        $so = $soModel->find((int) $header['sales_order_id']);
        if ($so === null) {
            throw new RuntimeException('Sales order not found.');
        }
        (new TransactionDocumentGuard())->assertSameTenant($so, $header, 'Sales order');
        (new TransactionDocumentGuard())->assertSameTenant($allocation, $header, 'Allocation order');

        $soStatus = (string) ($so['document_status'] ?? $so['status'] ?? 'draft');
        if (! in_array($soStatus, ['reserved', 'partial_reserved', 'approved', 'partial_delivered'], true)) {
            throw new RuntimeException('Only approved/reserved/partial delivered SO can be delivered from allocation. Current status: ' . $soStatus);
        }

        $this->assertDocumentNumberAvailable($header);
        $this->assertPeriodOpen('sales', $header, 'delivery_date');
        $this->assertPeriodOpen('inventory', $header, 'delivery_date');

        $db->transBegin();

        try {
            $deliveryModel = new SalesDeliveryModel();
            $deliveryLineModel = new SalesDeliveryLineModel();
            $allocationLineModel = new AllocationLineModel();
            $soLineModel = new SalesOrderLineModel();
            $stock = new InventoryStockService();
            $movementModel = new InventoryStockMovementModel();
            $totalCogs = 0.0;
            $postedLineCount = 0;

            $deliveryHeader = $this->filterPayload('sales_deliveries', array_replace($header, [
                'allocation_order_id' => $allocationId,
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
            $deliveryModel->insert($deliveryHeader);
            $deliveryId = (int) $deliveryModel->getInsertID();
            if ($deliveryId < 1) {
                throw new RuntimeException('Failed to create sales delivery header.');
            }

            foreach ($lines as $postedLine) {
                $allocationLineId = (int) ($postedLine['allocationline_id'] ?? $postedLine['allocation_line_id'] ?? 0);
                $qtyDeliver = $this->toNumber($postedLine['qty_delivered'] ?? 0);
                if ($allocationLineId < 1 || $qtyDeliver <= 0) {
                    continue;
                }

                $allocationLine = $allocationLineModel->find($allocationLineId);
                if ($allocationLine === null || (int) ($allocationLine['allocationorder_id'] ?? 0) !== $allocationId) {
                    throw new RuntimeException('Allocation line is not valid for this allocation order.');
                }

                $allocatedQty = $this->toNumber($allocationLine['allocateqty'] ?? 0);
                $deliveredBefore = $this->toNumber($allocationLine['delivered_qty'] ?? 0);
                $remainingAllocation = max(0.0, $allocatedQty - $deliveredBefore);
                if ($qtyDeliver > $remainingAllocation) {
                    throw new RuntimeException('Delivery qty cannot exceed allocated remaining qty for item ' . ($allocationLine['itemcode'] ?? '-') . '. Remaining allocation: ' . number_format($remainingAllocation, 6));
                }

                $soLine = $soLineModel->find((int) ($allocationLine['sales_order_line_id'] ?? 0));
                if ($soLine === null || (int) ($soLine['sales_order_id'] ?? 0) !== (int) $so['id']) {
                    throw new RuntimeException('SO line from allocation is not valid.');
                }
                $outstanding = $this->currentOutstanding($soLine);
                if ($qtyDeliver > $outstanding) {
                    throw new RuntimeException('Delivery qty cannot exceed SO outstanding qty for item ' . ($soLine['item_code'] ?? '-') . '. Outstanding: ' . number_format($outstanding, 6));
                }

                $itemCode = trim((string) ($allocationLine['itemcode'] ?? $soLine['item_code'] ?? ''));
                if ($itemCode === '') {
                    throw new RuntimeException('Allocation line item code is required.');
                }

                $warehouseId = $this->warehouseIdByCode((int) $header['company_id'], $header['site_id'] ?? null, (string) ($allocationLine['whs'] ?? $allocation['whs'] ?? ''));
                $locationId = $this->locationIdByCode((int) $header['company_id'], $header['site_id'] ?? null, (string) ($allocationLine['loc'] ?? ''));
                if ($warehouseId === null || $locationId === null) {
                    throw new RuntimeException('Allocation line ' . ($allocationLine['line'] ?? '#') . ' does not have valid warehouse/location for delivery.');
                }

                $stockPayload = [
                    'company_id' => $header['company_id'],
                    'site_id' => $header['site_id'] ?? null,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'item_id' => $soLine['item_id'] ?? null,
                    'item_code' => $itemCode,
                    'batch_no' => trim((string) ($allocationLine['batchno'] ?? '')),
                    'item_name' => $allocationLine['itemname'] ?? $soLine['item_name'] ?? null,
                    'uom_code' => $allocationLine['allocateuom'] ?? $soLine['uom_code'] ?? 'PCS',
                    'qty' => $qtyDeliver,
                    'unit_cost' => 0,
                    'movement_date' => $header['delivery_date'] ?? date('Y-m-d'),
                    'movement_type' => 'sales_delivery',
                    'reference_type' => 'sales_delivery',
                    'reference_id' => $deliveryId,
                    'reference_no' => $header['delivery_no'],
                    'notes' => 'Stock out from allocation ' . ($allocation['allocnumb'] ?? ''),
                ];

                $stock->releaseReservation($stockPayload, $userId);
                $movementId = $stock->stockOut($stockPayload, $userId);
                $movement = $movementModel->find($movementId);
                $totalCogs += (float) ($movement['stock_value'] ?? 0);

                $deliveryLinePayload = $this->filterPayload('sales_delivery_lines', [
                    'sales_delivery_id' => $deliveryId,
                    'sales_order_id' => $so['id'],
                    'sales_order_line_id' => $soLine['id'],
                    'allocationline_id' => $allocationLineId,
                    'stock_movement_id' => $movementId,
                    'line_no' => $soLine['line_no'] ?? $soLine['so_line'] ?? $allocationLine['line'] ?? 0,
                    'item_id' => $soLine['item_id'] ?? null,
                    'item_code' => $itemCode,
                    'batch_no' => trim((string) ($allocationLine['batchno'] ?? '')),
                    'item_name' => $allocationLine['itemname'] ?? $soLine['item_name'] ?? null,
                    'qty_delivered' => $qtyDeliver,
                    'reversed_qty' => 0,
                    'uom_code' => $allocationLine['allocateuom'] ?? $soLine['uom_code'] ?? 'PCS',
                    'unit_price' => $soLine['unit_price'] ?? 0,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                ]);
                $deliveryLineModel->insert($deliveryLinePayload);
                $deliveryLineId = (int) $deliveryLineModel->getInsertID();

                $newDeliveredAllocation = $deliveredBefore + $qtyDeliver;
                $allocationLineModel->update($allocationLineId, $this->filterPayload('allocationline', [
                    'delivered_qty' => $newDeliveredAllocation,
                    'delivery_line_id' => $deliveryLineId,
                    'updated_by' => (string) ($userId ?? 'system'),
                ]));

                $postedLineCount++;
            }

            if ($postedLineCount < 1) {
                throw new RuntimeException('No allocation line can be delivered. Please fill Deliver Now qty greater than zero.');
            }

            $this->recalculateSoDeliveryQuantities((int) $so['id'], $userId);
            $cogsAmount = round($totalCogs, 2);
            $glEntryId = $this->postCogsGl($header, $deliveryId, $cogsAmount, $userId);
            (new PostingIntegrityGuard())->assertGlEntryForAmount($cogsAmount, $glEntryId, 'Sales delivery from allocation');

            if ($glEntryId !== null) {
                $deliveryModel->update($deliveryId, ['gl_entry_id' => $glEntryId]);
            }

            $this->refreshSoStatus((int) $so['id'], $userId);
            $this->refreshAllocationStatus($allocationId, $deliveryId, $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post delivery from allocation.');
            }
            $db->transCommit();

            (new AuditLogService())->log('sales.delivery', 'delivery.post_from_allocation', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'sales_deliveries',
                'record_id' => $deliveryId,
                'record_code' => $header['delivery_no'],
                'description' => 'Sales delivery posted from allocation order, reserved stock released, stock decreased, and COGS GL posted when applicable.',
                'new_values' => ['allocation_id' => $allocationId, 'lines' => $lines, 'cogs_amount' => $cogsAmount, 'gl_entry_id' => $glEntryId],
            ]);

            return $deliveryId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException($exception->getMessage());
        }
    }

    private function refreshAllocationStatus(int $allocationId, int $deliveryId, ?int $userId): void
    {
        $lineModel = new AllocationLineModel();
        $lines = $lineModel->where('allocationorder_id', $allocationId)->findAll();
        $allocated = 0.0;
        $delivered = 0.0;
        foreach ($lines as $line) {
            $allocated += $this->toNumber($line['allocateqty'] ?? 0);
            $delivered += $this->toNumber($line['delivered_qty'] ?? 0);
        }

        $status = $delivered <= 0 ? 'posted' : ($delivered >= $allocated ? 'delivered' : 'partial_delivered');
        (new AllocationOrderModel())->update($allocationId, $this->filterPayload('allocationorder', [
            'status' => $status,
            'delivery_id' => $deliveryId,
            'delivered_at' => $status === 'posted' ? null : date('Y-m-d H:i:s'),
            'delivered_by' => $userId,
            'updated_by' => (string) ($userId ?? 'system'),
        ]));
    }

    private function recalculateSoDeliveryQuantities(int $soId, ?int $userId = null): void
    {
        if ($soId < 1) {
            return;
        }

        $db = Database::connect();
        $lineModel = new SalesOrderLineModel();
        $lines = $lineModel->where('sales_order_id', $soId)->findAll();
        foreach ($lines as $line) {
            $deliveredRow = $db->table('sales_delivery_lines sdl')
                ->select('COALESCE(SUM(sdl.qty_delivered - COALESCE(sdl.reversed_qty, 0)), 0) AS qty_delivered', false)
                ->join('sales_deliveries sd', 'sd.id = sdl.sales_delivery_id', 'inner')
                ->where('sdl.sales_order_line_id', (int) $line['id'])
                ->where('sd.status', 'posted')
                ->where('sd.reversed_at', null)
                ->get()
                ->getRowArray();

            $qtyDelivered = round((float) ($deliveredRow['qty_delivered'] ?? 0), 4);
            $qtyOrdered = round($this->toNumber($line['qty_ordered'] ?? $line['qty'] ?? 0), 4);
            $qtyOutstanding = max(0.0, round($qtyOrdered - $qtyDelivered, 4));
            $qtyReserved = max(0.0, min($this->toNumber($line['qty_reserved'] ?? 0), $qtyOutstanding));
            $payload = $this->filterPayload('sales_order_lines', [
                'qty_delivered' => $qtyDelivered,
                'qty_outstanding' => $qtyOutstanding,
                'qty_reserved' => $qtyReserved,
                'allocation_qty' => $qtyReserved,
                'available_so_qty' => $qtyOutstanding,
                'line_status' => $qtyDelivered <= 0 ? 'open' : ($qtyOutstanding <= 0 ? 'delivered' : 'partial_delivered'),
                'updated_by' => $userId,
            ]);
            $lineModel->update($line['id'], $payload);
        }
    }

    private function refreshSoStatus(int $soId, ?int $userId = null): void
    {
        if ($soId < 1) {
            return;
        }

        $lines = (new SalesOrderLineModel())->where('sales_order_id', $soId)->findAll();
        $totalOutstanding = 0.0;
        $totalDelivered = 0.0;
        $totalReserved = 0.0;
        foreach ($lines as $line) {
            $totalOutstanding += (float) ($line['qty_outstanding'] ?? 0);
            $totalDelivered += (float) ($line['qty_delivered'] ?? 0);
            $totalReserved += (float) ($line['qty_reserved'] ?? 0);
        }

        $status = $totalOutstanding <= 0 ? 'delivered' : ($totalDelivered > 0 ? 'partial_delivered' : ($totalReserved > 0 ? 'reserved' : 'approved'));
        (new SalesOrderModel())->update($soId, $this->filterPayload('sales_orders', [
            'status' => $status,
            'document_status' => $status,
            'updated_by' => $userId,
        ]));
    }

    private function postCogsGl(array $header, int $deliveryId, float $cogsAmount, ?int $userId): ?int
    {
        if ($cogsAmount <= 0) {
            return null;
        }

        $companyId = (int) $header['company_id'];
        $profile = new PostingProfileService();

        return (new GeneralLedgerService())->post([
            'company_id' => $companyId,
            'site_id' => $header['site_id'] ?? null,
            'journal_no' => 'GL-' . $header['delivery_no'],
            'journal_date' => $header['delivery_date'] ?? date('Y-m-d'),
            'source_module' => 'sales',
            'source_type' => 'sales_delivery_cogs',
            'source_id' => $deliveryId,
            'source_no' => $header['delivery_no'],
            'description' => 'COGS posting for delivery ' . $header['delivery_no'],
            'currency_code' => $header['currency_code'] ?? 'IDR',
        ], [
            [
                'account_no' => $profile->account($companyId, 'sales', 'cogs', '5000'),
                'description' => 'Cost of Goods Sold',
                'debit' => $cogsAmount,
                'credit' => 0,
            ],
            [
                'account_no' => $profile->account($companyId, 'sales', 'inventory', '1300'),
                'description' => 'Inventory',
                'debit' => 0,
                'credit' => $cogsAmount,
            ],
        ], $userId);
    }

    private function assertPeriodOpen(string $module, array $document, string $dateField): void
    {
        (new PeriodCloseService())->assertOpen(
            $module,
            (int) ($document['company_id'] ?? 0),
            (string) ($document[$dateField] ?? date('Y-m-d')),
            ! empty($document['site_id']) ? (int) $document['site_id'] : null
        );
    }

    private function assertDocumentNumberAvailable(array $header): void
    {
        $existing = (new SalesDeliveryModel())
            ->where('company_id', (int) $header['company_id'])
            ->where('delivery_no', (string) $header['delivery_no'])
            ->first();

        if ($existing !== null) {
            throw new RuntimeException('Delivery order number already exists and cannot be posted again: ' . $header['delivery_no'] . '.');
        }
    }

    private function warehouseIdByCode(int $companyId, mixed $siteId, string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $db = Database::connect();
        $builder = $db->table('warehouses')->where('code', $code);
        if ($db->fieldExists('company_id', 'warehouses')) {
            $builder->where('company_id', $companyId);
        }
        if ($db->fieldExists('site_id', 'warehouses')) {
            empty($siteId) ? $builder->where('site_id', null) : $builder->where('site_id', (int) $siteId);
        }
        if ($db->fieldExists('deleted_at', 'warehouses')) {
            $builder->where('deleted_at', null);
        }
        $row = $builder->get(1)->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function locationIdByCode(int $companyId, mixed $siteId, string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $db = Database::connect();
        $builder = $db->table('locations')->where('code', $code);
        if ($db->fieldExists('company_id', 'locations')) {
            $builder->where('company_id', $companyId);
        }
        if ($db->fieldExists('site_id', 'locations') && ! empty($siteId)) {
            $builder->groupStart()->where('site_id', (int) $siteId)->orWhere('site_id', null)->groupEnd();
        }
        if ($db->fieldExists('deleted_at', 'locations')) {
            $builder->where('deleted_at', null);
        }
        $row = $builder->get(1)->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function currentOutstanding(array $soLine): float
    {
        if (array_key_exists('qty_outstanding', $soLine) && $soLine['qty_outstanding'] !== null && $soLine['qty_outstanding'] !== '') {
            return max(0.0, $this->toNumber($soLine['qty_outstanding']));
        }

        $ordered = $this->toNumber($soLine['qty_ordered'] ?? $soLine['qty'] ?? 0);
        $delivered = $this->toNumber($soLine['qty_delivered'] ?? 0);
        return max(0.0, $ordered - $delivered);
    }

    private function toNumber(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        return (float) $value;
    }

    /** @param array<string,mixed> $payload */
    private function filterPayload(string $table, array $payload): array
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return [];
        }

        return array_intersect_key($payload, array_flip($db->getFieldNames($table)));
    }
}
