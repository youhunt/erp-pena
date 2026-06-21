<?php

namespace App\Services\Sales;

use App\Models\GlEntryLineModel;
use App\Models\InventoryStockMovementModel;
use App\Models\SalesDeliveryLineModel;
use App\Models\SalesDeliveryModel;
use App\Models\SalesInvoiceModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\AuditLogService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Finance\PostingProfileService;
use App\Services\Inventory\InventoryStockService;
use App\Services\Support\TransactionDocumentGuard;
use Config\Database;
use RuntimeException;
use Throwable;

class SalesDeliveryService
{
    public function post(array $header, array $lines, ?int $userId = null): int
    {
        if (empty($header['company_id']) || empty($header['sales_order_id']) || empty($header['delivery_no'])) {
            throw new RuntimeException('Company, SO, and delivery number are required.');
        }
        if ($lines === []) {
            throw new RuntimeException('At least one delivery line is required.');
        }
        $this->assertDocumentNumberAvailable($header);
        $this->assertStorageLocation($header);
        $this->assertPeriodOpen('sales', $header, 'delivery_date');
        $this->assertPeriodOpen('inventory', $header, 'delivery_date');

        $soModel = new SalesOrderModel();
        $so = $soModel->find((int) $header['sales_order_id']);
        if ($so === null) {
            throw new RuntimeException('Sales order not found.');
        }
        (new TransactionDocumentGuard())->assertSameTenant($so, $header, 'Sales order');

        $soStatus = (string) ($so['document_status'] ?? $so['status'] ?? 'draft');
        if (! in_array($soStatus, ['approved', 'reserved', 'partial_delivered'], true)) {
            throw new RuntimeException('Only approved, reserved, or partially delivered SO can be delivered. Current status: ' . $soStatus);
        }

        $header = array_replace($header, [
            'company_id' => $so['company_id'],
            'site_id' => $so['site_id'] ?? null,
            'sales_order_id' => $so['id'],
            'so_no' => $so['so_no'] ?? $so['document_no'] ?? null,
            'customer_id' => $so['customer_id'] ?? null,
            'customer_code' => $so['customer_code'] ?? $so['customer'] ?? null,
            'customer_name' => $so['customer_name'] ?? null,
        ]);

        $db = Database::connect();
        $db->transBegin();

        try {
            $deliveryModel = new SalesDeliveryModel();
            $deliveryLineModel = new SalesDeliveryLineModel();
            $soLineModel = new SalesOrderLineModel();
            $stock = new InventoryStockService();
            $movementModel = new InventoryStockMovementModel();
            $totalCogs = 0.0;
            $postedLineCount = 0;
            $glWarning = null;

            $deliveryModel->insert(array_replace($header, [
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
            $deliveryId = (int) $deliveryModel->getInsertID();
            if ($deliveryId < 1) {
                throw new RuntimeException('Failed to create sales delivery header.');
            }

            foreach ($lines as $line) {
                $soLine = $soLineModel->find((int) ($line['sales_order_line_id'] ?? 0));
                if ($soLine === null) {
                    throw new RuntimeException('SO line not found.');
                }
                if ((int) ($soLine['sales_order_id'] ?? 0) !== (int) $so['id']) {
                    throw new RuntimeException('Delivery line does not belong to selected SO.');
                }

                $qtyDeliver = $this->toNumber($line['qty_delivered'] ?? 0);
                $outstanding = $this->currentOutstanding($soLine);
                if ($qtyDeliver <= 0) {
                    continue;
                }
                if ($qtyDeliver > $outstanding) {
                    throw new RuntimeException('Delivery qty cannot exceed outstanding qty for item ' . ($soLine['item_code'] ?? '-') . '. Outstanding: ' . $outstanding);
                }

                $itemCode = trim((string) ($soLine['item_code'] ?? ''));
                if ($itemCode === '') {
                    throw new RuntimeException('SO line item code is required before delivery can be posted.');
                }

                $reservedBefore = max(0.0, $this->toNumber($soLine['qty_reserved'] ?? 0));
                if ($reservedBefore > 0) {
                    $stock->releaseReservation([
                        'company_id' => $header['company_id'],
                        'site_id' => $header['site_id'] ?? null,
                        'warehouse_id' => null,
                        'location_id' => null,
                        'item_id' => $soLine['item_id'] ?? null,
                        'item_code' => $itemCode,
                        'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                        'uom_code' => $soLine['uom_code'] ?? 'PCS',
                        'qty' => min($qtyDeliver, $reservedBefore),
                    ], $userId);
                }

                $movementId = $stock->stockOut([
                    'company_id' => $header['company_id'],
                    'site_id' => $header['site_id'] ?? null,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'location_id' => $header['location_id'] ?? null,
                    'item_id' => $soLine['item_id'] ?? null,
                    'item_code' => $itemCode,
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $soLine['item_name'] ?? null,
                    'uom_code' => $soLine['uom_code'] ?? 'PCS',
                    'qty' => $qtyDeliver,
                    'unit_cost' => 0,
                    'movement_date' => $header['delivery_date'] ?? date('Y-m-d'),
                    'movement_type' => 'sales_delivery',
                    'reference_type' => 'sales_delivery',
                    'reference_id' => $deliveryId,
                    'reference_no' => $header['delivery_no'],
                    'notes' => 'Stock out from SO ' . ($so['so_no'] ?? ''),
                ], $userId);

                $movement = $movementModel->find($movementId);
                $totalCogs += (float) ($movement['stock_value'] ?? 0);

                $deliveryLineModel->insert([
                    'sales_delivery_id' => $deliveryId,
                    'sales_order_id' => $so['id'],
                    'sales_order_line_id' => $soLine['id'],
                    'stock_movement_id' => $movementId,
                    'line_no' => $soLine['line_no'] ?? $soLine['so_line'] ?? 0,
                    'item_id' => $soLine['item_id'] ?? null,
                    'item_code' => $itemCode,
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $soLine['item_name'] ?? null,
                    'qty_delivered' => $qtyDeliver,
                    'reversed_qty' => 0,
                    'uom_code' => $soLine['uom_code'] ?? 'PCS',
                    'unit_price' => $soLine['unit_price'] ?? 0,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'location_id' => $header['location_id'] ?? null,
                ]);
                $postedLineCount++;
            }

            if ($postedLineCount < 1) {
                throw new RuntimeException('No delivery line can be posted. Please fill Deliver Now qty greater than zero.');
            }

            $this->recalculateSoDeliveryQuantities((int) $so['id'], $userId);

            try {
                $glEntryId = $this->postCogsGl($header, $deliveryId, round($totalCogs, 2), $userId);
            } catch (RuntimeException $e) {
                $glEntryId = null;
                $glWarning = 'GL skipped: ' . $e->getMessage();
            }
            $deliveryUpdate = [];
            if ($glEntryId !== null) {
                $deliveryUpdate['gl_entry_id'] = $glEntryId;
            }
            if ($glWarning !== null) {
                $deliveryUpdate['notes'] = trim(($header['notes'] ?? '') . ' ' . $glWarning);
            }
            if ($deliveryUpdate !== []) {
                $deliveryModel->update($deliveryId, $deliveryUpdate);
            }

            $this->refreshSoStatus((int) $so['id'], $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post sales delivery.');
            }
            $db->transCommit();

            (new AuditLogService())->log('sales.delivery', 'delivery.post', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'sales_deliveries',
                'record_id' => $deliveryId,
                'record_code' => $header['delivery_no'],
                'description' => $glWarning !== null ? 'Sales delivery posted and stock decreased. ' . $glWarning : 'Sales delivery posted, stock decreased, and COGS GL posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'cogs_amount' => $totalCogs, 'gl_entry_id' => $glEntryId, 'gl_warning' => $glWarning],
            ]);

            return $deliveryId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function reverse(int $deliveryId, ?int $userId = null, ?string $reason = null): void
    {
        $db = Database::connect();
        $db->transBegin();

        try {
            $deliveryModel = new SalesDeliveryModel();
            $deliveryLineModel = new SalesDeliveryLineModel();
            $stock = new InventoryStockService();

            $delivery = $deliveryModel->find($deliveryId);
            if ($delivery === null) {
                throw new RuntimeException('Sales delivery not found.');
            }
            if ((string) ($delivery['status'] ?? '') !== 'posted') {
                throw new RuntimeException('Only posted sales delivery can be reversed.');
            }
            if (! empty($delivery['reversed_at'])) {
                throw new RuntimeException('Sales delivery has already been reversed.');
            }
            $invoice = (new SalesInvoiceModel())
                ->where('sales_delivery_id', $deliveryId)
                ->where('status !=', 'cancelled')
                ->first();
            if ($invoice !== null) {
                throw new RuntimeException('Sales delivery already has sales invoice ' . ($invoice['invoice_no'] ?? '#' . $invoice['id']) . '. Reverse or cancel the invoice first.');
            }
            $this->assertPeriodOpen('sales', $delivery, 'delivery_date');
            $this->assertPeriodOpen('inventory', $delivery, 'delivery_date');

            $lines = $deliveryLineModel->where('sales_delivery_id', $deliveryId)->orderBy('line_no', 'ASC')->findAll();
            if ($lines === []) {
                throw new RuntimeException('Sales delivery has no lines to reverse.');
            }

            $now = date('Y-m-d H:i:s');
            foreach ($lines as $line) {
                if (! empty($line['reversal_movement_id'])) {
                    throw new RuntimeException('Sales delivery line has already been reversed.');
                }

                $reversalMovementId = $stock->stockIn([
                    'company_id' => $delivery['company_id'],
                    'site_id' => $delivery['site_id'] ?? null,
                    'warehouse_id' => $line['warehouse_id'] ?? $delivery['warehouse_id'] ?? null,
                    'location_id' => $line['location_id'] ?? $delivery['location_id'] ?? null,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'] ?? '',
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $line['item_name'] ?? null,
                    'uom_code' => $line['uom_code'] ?? 'PCS',
                    'qty' => (float) ($line['qty_delivered'] ?? 0),
                    'unit_cost' => 0,
                    'movement_date' => $delivery['delivery_date'] ?? date('Y-m-d'),
                    'movement_type' => 'sales_delivery_reversal',
                    'reference_type' => 'sales_delivery_reversal',
                    'reference_id' => $deliveryId,
                    'reference_no' => ($delivery['delivery_no'] ?? 'DO') . '-REV',
                    'notes' => trim(($reason ?? '') . ' Reversal for delivery ' . ($delivery['delivery_no'] ?? '')),
                ], $userId);

                $deliveryLineModel->update((int) $line['id'], [
                    'reversed_qty' => (float) ($line['qty_delivered'] ?? 0),
                    'reversal_movement_id' => $reversalMovementId,
                    'reversed_at' => $now,
                    'reversed_by' => $userId,
                    'reversal_reason' => $reason,
                ]);
            }

            $reversalGlEntryId = $this->postGlReversal($delivery, $userId);

            $deliveryModel->update($deliveryId, [
                'status' => 'reversed',
                'reversed_at' => $now,
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
                'reversal_gl_entry_id' => $reversalGlEntryId,
                'updated_by' => $userId,
            ]);

            $this->recalculateSoDeliveryQuantities((int) ($delivery['sales_order_id'] ?? 0), $userId);
            $this->refreshSoStatus((int) ($delivery['sales_order_id'] ?? 0), $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to reverse sales delivery.');
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }

        (new AuditLogService())->log('sales.delivery', 'delivery.reverse', [
            'company_id' => $delivery['company_id'] ?? null,
            'site_id' => $delivery['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'sales_deliveries',
            'record_id' => $deliveryId,
            'record_code' => $delivery['delivery_no'] ?? null,
            'description' => 'Sales delivery reversed, stock returned, and reversal GL posted when original GL exists.',
            'old_values' => ['status' => 'posted'],
            'new_values' => ['status' => 'reversed', 'reason' => $reason, 'reversal_gl_entry_id' => $reversalGlEntryId],
        ]);
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

    private function postGlReversal(array $delivery, ?int $userId): ?int
    {
        if (empty($delivery['gl_entry_id'])) {
            return null;
        }

        $glLines = (new GlEntryLineModel())
            ->where('gl_entry_id', (int) $delivery['gl_entry_id'])
            ->orderBy('line_no', 'ASC')
            ->findAll();
        if ($glLines === []) {
            return null;
        }

        $lines = [];
        foreach ($glLines as $line) {
            $lines[] = [
                'account_no' => $line['account_no'],
                'description' => 'Reverse delivery ' . ($delivery['delivery_no'] ?? '') . ' - ' . ($line['description'] ?? ''),
                'debit' => (float) ($line['credit'] ?? 0),
                'credit' => (float) ($line['debit'] ?? 0),
            ];
        }

        return (new GeneralLedgerService())->post([
            'company_id' => (int) $delivery['company_id'],
            'site_id' => $delivery['site_id'] ?? null,
            'journal_no' => 'GL-REV-' . ($delivery['delivery_no'] ?? $delivery['id']),
            'journal_date' => $delivery['delivery_date'] ?? date('Y-m-d'),
            'source_module' => 'sales',
            'source_type' => 'sales_delivery_reversal',
            'source_id' => $delivery['id'],
            'source_no' => $delivery['delivery_no'] ?? null,
            'description' => 'Reverse sales delivery ' . ($delivery['delivery_no'] ?? ''),
            'currency_code' => $delivery['currency_code'] ?? 'IDR',
        ], $lines, $userId);
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

    private function assertStorageLocation(array $header): void
    {
        $warehouseId = (int) ($header['warehouse_id'] ?? 0);
        $locationId = (int) ($header['location_id'] ?? 0);
        if ($warehouseId < 1 || $locationId < 1) {
            throw new RuntimeException('Warehouse and location are required before posting sales delivery.');
        }

        $db = Database::connect();
        $warehouse = $db->table('warehouses')->where('id', $warehouseId);
        $location = $db->table('locations')->where('id', $locationId);

        if (! empty($header['company_id'])) {
            $warehouse->where('company_id', (int) $header['company_id']);
            $location->where('company_id', (int) $header['company_id']);
        }
        if (! empty($header['site_id'])) {
            $warehouse->where('site_id', (int) $header['site_id']);
            $location->where('site_id', (int) $header['site_id']);
        }
        if ($db->fieldExists('deleted_at', 'warehouses')) {
            $warehouse->where('deleted_at', null);
        }
        if ($db->fieldExists('deleted_at', 'locations')) {
            $location->where('deleted_at', null);
        }

        $warehouseRow = $warehouse->get()->getRowArray();
        $locationRow = $location->get()->getRowArray();
        if ($warehouseRow === null) {
            throw new RuntimeException('Selected warehouse is not valid for this delivery.');
        }
        if ($locationRow === null) {
            throw new RuntimeException('Selected location is not valid for this delivery.');
        }
        if ((int) ($locationRow['warehouse_id'] ?? 0) !== $warehouseId) {
            throw new RuntimeException('Selected location does not belong to selected warehouse.');
        }
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
                ->select('COALESCE(SUM(sdl.qty_delivered), 0) AS qty_delivered', false)
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

            $lineModel->update($line['id'], [
                'qty_delivered' => $qtyDelivered,
                'qty_outstanding' => $qtyOutstanding,
                'qty_reserved' => $qtyReserved,
                'line_status' => $qtyDelivered <= 0 ? 'open' : ($qtyOutstanding <= 0 ? 'delivered' : 'partial_delivered'),
                'updated_by' => $userId,
            ]);
        }
    }

    private function refreshSoStatus(int $soId, ?int $userId = null): void
    {
        if ($soId < 1) {
            return;
        }

        $lineModel = new SalesOrderLineModel();
        $lines = $lineModel->where('sales_order_id', $soId)->findAll();
        $totalOutstanding = 0.0;
        $totalDelivered = 0.0;
        foreach ($lines as $line) {
            $totalOutstanding += (float) ($line['qty_outstanding'] ?? 0);
            $totalDelivered += (float) ($line['qty_delivered'] ?? 0);
        }

        $status = $totalOutstanding <= 0 ? 'delivered' : ($totalDelivered > 0 ? 'partial_delivered' : 'approved');
        (new SalesOrderModel())->update($soId, [
            'status' => $status,
            'document_status' => $status,
            'updated_by' => $userId,
        ]);
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
}
