<?php

namespace App\Services\Sales;

use App\Models\AllocationLineModel;
use App\Models\AllocationOrderModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\AuditLogService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Inventory\InventoryStockService;
use Config\Database;
use RuntimeException;
use Throwable;

class AllocationService
{
    public function allocateFromSalesOrder(int $salesOrderId, array $header = [], ?int $userId = null): int
    {
        $soModel = new SalesOrderModel();
        $lineModel = new SalesOrderLineModel();
        $so = $soModel->find($salesOrderId);
        if ($so === null) {
            throw new RuntimeException('Sales order not found.');
        }

        $status = strtolower((string) ($so['document_status'] ?? $so['status'] ?? 'draft'));
        if (in_array($status, ['closed', 'cancelled', 'canceled', 'void'], true)) {
            throw new RuntimeException('Closed/cancelled Sales Order cannot be allocated. Current status: ' . $status);
        }
        $this->assertPeriodOpen($so, $header);

        $lines = $lineModel->where('sales_order_id', $salesOrderId)->orderBy('line_no', 'ASC')->findAll();
        if ($lines === []) {
            throw new RuntimeException('Sales order has no lines.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $allocNo = trim((string) ($header['allocnumb'] ?? 'ALC-' . date('Ymd-His')));
            if ($allocNo === '') {
                $allocNo = 'ALC-' . date('Ymd-His');
            }

            $allocationModel = new AllocationOrderModel();
            $allocationModel->insert([
                'company_id' => $so['company_id'],
                'site_id' => $so['site_id'] ?? null,
                'sales_order_id' => $so['id'],
                'allocnumb' => $allocNo,
                'allocdate' => $header['allocdate'] ?? date('Y-m-d'),
                'site' => $so['site'] ?? (string) ($so['site_id'] ?? ''),
                'customer' => $so['customer_code'] ?? $so['customer'] ?? null,
                'customern' => $so['customer_name'] ?? null,
                'shipdate' => $header['shipdate'] ?? null,
                'shipto' => $header['shipto'] ?? $this->firstValue($lines, 'shipto'),
                'dept' => $header['dept'] ?? null,
                'whs' => $header['whs'] ?? $this->firstValue($lines, 'whs'),
                'remarks' => $header['remarks'] ?? null,
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => (string) ($userId ?? 'system'),
                'updated_by' => (string) ($userId ?? 'system'),
                'active' => 1,
            ]);
            $allocationId = (int) $allocationModel->getInsertID();
            if ($allocationId < 1) {
                throw new RuntimeException('Failed to create allocation order header.');
            }

            $stock = new InventoryStockService();
            $allocationLineModel = new AllocationLineModel();
            $allocatedAny = false;
            $allocationLineNo = 10;

            foreach ($lines as $line) {
                $ordered = $this->lineOrderedQty($line);
                $allocatedBefore = $this->lineAllocatedQty($line);
                $toAllocate = max(0.0, $ordered - $allocatedBefore);
                if ($toAllocate <= 0) {
                    continue;
                }

                $stockRows = $this->availableInventoryRows($so, $line, $header);
                $totalAvailable = array_sum(array_map(static fn (array $row): float => (float) $row['qty_available'], $stockRows));
                if ($totalAvailable <= 0) {
                    continue;
                }

                $remaining = $toAllocate;
                foreach ($stockRows as $stockRow) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $allocateQty = min($remaining, (float) $stockRow['qty_available']);
                    if ($allocateQty <= 0) {
                        continue;
                    }

                    $stock->reserve([
                        'company_id' => $so['company_id'],
                        'site_id' => $so['site_id'] ?? null,
                        'warehouse_id' => $stockRow['warehouse_id'] ?? null,
                        'location_id' => $stockRow['location_id'] ?? null,
                        'item_id' => $line['item_id'] ?? null,
                        'item_code' => $line['item_code'] ?? '',
                        'batch_no' => $stockRow['batch_no'] ?? '',
                        'uom_code' => $line['uom_code'] ?? $stockRow['uom_code'] ?? 'PCS',
                        'qty' => $allocateQty,
                    ], $userId);

                    $allocationLineModel->insert([
                        'company_id' => $so['company_id'],
                        'site_id' => $so['site_id'] ?? null,
                        'allocationorder_id' => $allocationId,
                        'sales_order_id' => $so['id'],
                        'sales_order_line_id' => $line['id'],
                        'allocate' => $allocNo,
                        'site' => $so['site'] ?? (string) ($so['site_id'] ?? ''),
                        'customer' => $so['customer_code'] ?? $so['customer'] ?? null,
                        'customern' => $so['customer_name'] ?? null,
                        'line' => (string) $allocationLineNo,
                        'soprefix' => $this->soPrefix($so),
                        'salesorder' => $so['so_no'] ?? $so['document_no'] ?? null,
                        'transcode' => $line['trans_code'] ?? $line['transcode'] ?? 'SO',
                        'soline' => (string) ($line['so_line'] ?? $line['line_no'] ?? ''),
                        'itemcode' => $line['item_code'] ?? '',
                        'itemname' => $line['item_name'] ?? null,
                        'soqty' => $ordered,
                        'souom' => $line['uom_code'] ?? null,
                        'whs' => $stockRow['warehouse_code'] ?? $header['whs'] ?? $line['whs'] ?? null,
                        'loc' => $stockRow['location_code'] ?? $header['loc'] ?? null,
                        'batchno' => $stockRow['batch_no'] ?? $header['batchno'] ?? null,
                        'stockqty' => $stockRow['qty_on_hand'] ?? 0,
                        'stockuom' => $stockRow['uom_code'] ?? $line['uom_code'] ?? null,
                        'availableqty' => $stockRow['qty_available'] ?? 0,
                        'availableuom' => $stockRow['uom_code'] ?? $line['uom_code'] ?? null,
                        'allocateqty' => $allocateQty,
                        'allocateuom' => $line['uom_code'] ?? $stockRow['uom_code'] ?? null,
                        'shipto' => $header['shipto'] ?? $line['shipto'] ?? null,
                        'description' => 'Allocated from SO using Inventory Location/Batch',
                        'created_by' => (string) ($userId ?? 'system'),
                        'updated_by' => (string) ($userId ?? 'system'),
                        'active' => 1,
                    ]);

                    $this->syncBatchAllocation($so, $line, $stockRow, $allocateQty, $userId);
                    $remaining -= $allocateQty;
                    $allocationLineNo += 10;
                    $allocatedAny = true;
                }

                $allocatedNow = $toAllocate - max(0.0, $remaining);
                if ($allocatedNow > 0) {
                    $newAllocated = $allocatedBefore + $allocatedNow;
                    $newAvailableSo = max(0.0, $ordered - $newAllocated);
                    $this->updateSalesOrderLineAllocation((int) $line['id'], $newAllocated, $newAvailableSo, $userId);
                }
            }

            if (! $allocatedAny) {
                throw new RuntimeException('No outstanding SO line can be allocated. Check SO available qty, stock availability, batch expiry date, warehouse/location, and SO status.');
            }

            $soModel->update($salesOrderId, [
                'status' => 'reserved',
                'document_status' => 'reserved',
                'reserved_at' => date('Y-m-d H:i:s'),
                'reserved_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post allocation order.');
            }
            $db->transCommit();

            (new AuditLogService())->log('sales.allocation', 'allocation.post', [
                'company_id' => $so['company_id'] ?? null,
                'site_id' => $so['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'allocationorder',
                'record_id' => $allocationId,
                'record_code' => $allocNo,
                'description' => 'Allocation order posted and stock reserved from inventory location/batch.',
            ]);

            return $allocationId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function allocationPreviewRows(array $so, array $lines, array $header = []): array
    {
        $rows = [];
        foreach ($lines as $line) {
            $ordered = $this->lineOrderedQty($line);
            $allocatedBefore = $this->lineAllocatedQty($line);
            $toAllocate = max(0.0, $ordered - $allocatedBefore);
            $stockRows = $this->availableInventoryRows($so, $line, $header);
            $rows[] = [
                'line' => $line,
                'ordered' => $ordered,
                'allocated' => $allocatedBefore,
                'available_so' => $toAllocate,
                'stock_rows' => $stockRows,
                'stock_available' => array_sum(array_map(static fn (array $row): float => (float) $row['qty_available'], $stockRows)),
            ];
        }

        return $rows;
    }

    /** @return array<int, array<string,mixed>> */
    private function availableInventoryRows(array $so, array $line, array $header): array
    {
        $db = Database::connect();
        if (! $db->tableExists('inventory_stock_balances')) {
            return [];
        }

        $builder = $db->table('inventory_stock_balances b')
            ->select('b.id, b.company_id, b.site_id, b.warehouse_id, b.location_id, b.item_id, b.item_code, b.batch_no, b.uom_code, b.qty_on_hand, b.qty_reserved, b.qty_available, w.code AS warehouse_code, l.code AS location_code, bm.expiry_date')
            ->join('warehouses w', 'w.id = b.warehouse_id', 'left')
            ->join('locations l', 'l.id = b.location_id', 'left')
            ->join('batch_masters bm', 'bm.company_id = b.company_id AND bm.item_code = b.item_code AND bm.batch_no = b.batch_no AND (bm.site_id = b.site_id OR bm.site_id IS NULL)', 'left')
            ->where('b.company_id', (int) $so['company_id'])
            ->where('b.item_code', (string) ($line['item_code'] ?? ''))
            ->where('b.qty_available >', 0);

        empty($so['site_id']) ? $builder->where('b.site_id', null) : $builder->where('b.site_id', (int) $so['site_id']);

        $warehouseCode = trim((string) ($header['whs'] ?? $line['whs'] ?? ''));
        if ($warehouseCode !== '') {
            $builder->where('w.code', $warehouseCode);
        }

        $locationCode = trim((string) ($header['loc'] ?? ''));
        if ($locationCode !== '') {
            $builder->where('l.code', $locationCode);
        }

        $batchNo = trim((string) ($header['batchno'] ?? ''));
        if ($batchNo !== '') {
            $builder->where('b.batch_no', $batchNo);
        }

        if ($db->tableExists('batch_masters')) {
            $today = date('Y-m-d');
            $builder->groupStart()
                ->where('bm.expiry_date >=', $today)
                ->orWhere('bm.expiry_date', null)
                ->orWhere('bm.expiry_date', '0000-00-00')
                ->orWhere('b.batch_no', '')
                ->groupEnd();
        }

        return $builder
            ->orderBy('CASE WHEN bm.expiry_date IS NULL OR bm.expiry_date = "0000-00-00" THEN 1 ELSE 0 END', 'ASC', false)
            ->orderBy('bm.expiry_date', 'ASC')
            ->orderBy('b.id', 'ASC')
            ->get(500)
            ->getResultArray();
    }

    private function updateSalesOrderLineAllocation(int $lineId, float $allocatedQty, float $availableSoQty, ?int $userId): void
    {
        $db = Database::connect();
        $payload = [
            'qty_reserved' => $allocatedQty,
            'line_status' => $availableSoQty <= 0 ? 'reserved' : 'partial_reserved',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        foreach (['allocation_qty', 'allocationqty', 'allocated_qty'] as $field) {
            if ($db->fieldExists($field, 'sales_order_lines')) {
                $payload[$field] = $allocatedQty;
            }
        }
        foreach (['available_so_qty', 'availablesoqty', 'available_soqqty', 'qty_outstanding'] as $field) {
            if ($db->fieldExists($field, 'sales_order_lines')) {
                $payload[$field] = $availableSoQty;
            }
        }
        if ($db->fieldExists('updated_by', 'sales_order_lines')) {
            $payload['updated_by'] = $userId;
        }

        $db->table('sales_order_lines')->where('id', $lineId)->update($payload);
    }

    private function syncBatchAllocation(array $so, array $line, array $stockRow, float $allocateQty, ?int $userId): void
    {
        $db = Database::connect();
        if (! $db->tableExists('batch_masters') || trim((string) ($stockRow['batch_no'] ?? '')) === '') {
            return;
        }

        $payload = [];
        foreach (['allocation_qty', 'allocate_qty', 'allocated_qty'] as $field) {
            if ($db->fieldExists($field, 'batch_masters')) {
                $payload[$field] = 'RAW_SQL:' . $field . ' + ' . $allocateQty;
            }
        }
        foreach (['available_qty'] as $field) {
            if ($db->fieldExists($field, 'batch_masters')) {
                $payload[$field] = 'RAW_SQL:GREATEST(0, ' . $field . ' - ' . $allocateQty . ')';
            }
        }
        if ($db->fieldExists('updated_by', 'batch_masters')) {
            $payload['updated_by'] = $userId;
        }
        if ($db->fieldExists('updated_at', 'batch_masters')) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }

        if ($payload === []) {
            return;
        }

        $builder = $db->table('batch_masters')
            ->where('company_id', (int) $so['company_id'])
            ->where('item_code', (string) ($line['item_code'] ?? ''))
            ->where('batch_no', (string) ($stockRow['batch_no'] ?? ''));
        empty($so['site_id']) ? $builder->where('site_id', null) : $builder->where('site_id', (int) $so['site_id']);

        $set = [];
        foreach ($payload as $field => $value) {
            if (is_string($value) && str_starts_with($value, 'RAW_SQL:')) {
                $set[$field] = substr($value, 8);
                unset($payload[$field]);
            }
        }
        foreach ($set as $field => $expr) {
            $builder->set($field, $expr, false);
        }
        if ($payload !== []) {
            $builder->set($payload);
        }
        $builder->update();
    }

    private function lineOrderedQty(array $line): float
    {
        return (float) ($line['so_stock_qty'] ?? $line['sostockqty'] ?? $line['qty_ordered'] ?? $line['qty'] ?? 0);
    }

    private function lineAllocatedQty(array $line): float
    {
        return (float) ($line['allocation_qty'] ?? $line['allocationqty'] ?? $line['allocated_qty'] ?? $line['qty_reserved'] ?? 0);
    }

    private function firstValue(array $rows, string $field): ?string
    {
        foreach ($rows as $row) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function soPrefix(array $so): string
    {
        $documentNo = (string) ($so['so_no'] ?? $so['document_no'] ?? 'SO');
        if (str_contains($documentNo, '-')) {
            return substr($documentNo, 0, (int) strpos($documentNo, '-'));
        }
        if (str_contains($documentNo, '/')) {
            return substr($documentNo, 0, (int) strpos($documentNo, '/'));
        }

        return substr($documentNo, 0, 5);
    }

    private function assertPeriodOpen(array $salesOrder, array $header): void
    {
        $date = (string) ($header['allocdate'] ?? $salesOrder['so_date'] ?? $salesOrder['document_date'] ?? date('Y-m-d'));
        $companyId = (int) ($salesOrder['company_id'] ?? 0);
        $siteId = ! empty($salesOrder['site_id']) ? (int) $salesOrder['site_id'] : null;
        $period = new PeriodCloseService();
        $period->assertOpen('sales', $companyId, $date, $siteId);
        $period->assertOpen('inventory', $companyId, $date, $siteId);
    }
}
