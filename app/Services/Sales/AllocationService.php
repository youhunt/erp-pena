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

        $status = (string) ($so['document_status'] ?? $so['status'] ?? 'draft');
        if (! in_array($status, ['approved', 'partial_reserved'], true)) {
            throw new RuntimeException('Only approved or partially reserved SO can be allocated. Current status: ' . $status);
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
                'shipto' => $header['shipto'] ?? null,
                'dept' => $header['dept'] ?? null,
                'whs' => $header['whs'] ?? null,
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
            foreach ($lines as $line) {
                $ordered = (float) ($line['qty_ordered'] ?? $line['qty'] ?? 0);
                $reserved = (float) ($line['qty_reserved'] ?? 0);
                $toAllocate = max(0, $ordered - $reserved);
                if ($toAllocate <= 0) {
                    continue;
                }

                $stockInfo = $this->stockInfo((int) $so['company_id'], $so['site_id'] ?? null, (string) ($line['item_code'] ?? ''));
                if ($toAllocate > $stockInfo['available']) {
                    throw new RuntimeException('Insufficient available stock for item ' . ($line['item_code'] ?? '-') . '. Required: ' . $toAllocate . ', available: ' . $stockInfo['available']);
                }

                $stock->reserve([
                    'company_id' => $so['company_id'],
                    'site_id' => $so['site_id'] ?? null,
                    'warehouse_id' => null,
                    'location_id' => null,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'] ?? '',
                    'uom_code' => $line['uom_code'] ?? 'PCS',
                    'qty' => $toAllocate,
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
                    'line' => (string) ($line['line_no'] ?? ''),
                    'soprefix' => substr((string) ($so['so_no'] ?? 'SO'), 0, 5),
                    'salesorder' => $so['so_no'] ?? null,
                    'transcode' => 'SO',
                    'soline' => (string) ($line['line_no'] ?? ''),
                    'itemcode' => $line['item_code'] ?? '',
                    'itemname' => $line['item_name'] ?? null,
                    'soqty' => $ordered,
                    'souom' => $line['uom_code'] ?? null,
                    'whs' => $header['whs'] ?? null,
                    'loc' => $header['loc'] ?? null,
                    'batchno' => $header['batchno'] ?? null,
                    'stockqty' => $stockInfo['on_hand'],
                    'stockuom' => $line['uom_code'] ?? null,
                    'availableqty' => $stockInfo['available'],
                    'availableuom' => $line['uom_code'] ?? null,
                    'allocateqty' => $toAllocate,
                    'allocateuom' => $line['uom_code'] ?? null,
                    'shipto' => $header['shipto'] ?? null,
                    'description' => 'Allocated from SO',
                    'created_by' => (string) ($userId ?? 'system'),
                    'updated_by' => (string) ($userId ?? 'system'),
                    'active' => 1,
                ]);

                $lineModel->update((int) $line['id'], [
                    'qty_reserved' => $reserved + $toAllocate,
                    'line_status' => 'reserved',
                ]);
                $allocatedAny = true;
            }

            if (! $allocatedAny) {
                throw new RuntimeException('No outstanding SO line can be allocated.');
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
                'description' => 'Allocation order posted and stock reserved.',
            ]);

            return $allocationId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function stockInfo(int $companyId, mixed $siteId, string $itemCode): array
    {
        $db = Database::connect();
        if (! $db->tableExists('inventory_stock_balances')) {
            return ['on_hand' => 0.0, 'reserved' => 0.0, 'available' => 0.0];
        }

        $builder = $db->table('inventory_stock_balances')
            ->select('SUM(qty_on_hand) AS on_hand, SUM(qty_reserved) AS reserved, SUM(qty_available) AS available')
            ->where('company_id', $companyId)
            ->where('item_code', $itemCode);

        $siteId === null ? $builder->where('site_id', null) : $builder->where('site_id', $siteId);
        $row = $builder->get()->getRowArray() ?: [];

        return [
            'on_hand' => (float) ($row['on_hand'] ?? 0),
            'reserved' => (float) ($row['reserved'] ?? 0),
            'available' => (float) ($row['available'] ?? 0),
        ];
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
