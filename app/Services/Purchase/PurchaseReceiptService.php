<?php

namespace App\Services\Purchase;

use App\Models\InventoryStockMovementModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseReceiptLineModel;
use App\Models\PurchaseReceiptModel;
use App\Services\AuditLogService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Finance\PostingProfileService;
use App\Services\Inventory\InventoryStockService;
use Config\Database;
use RuntimeException;
use Throwable;

class PurchaseReceiptService
{
    public function post(array $header, array $lines, ?int $userId = null): int
    {
        if (empty($header['company_id']) || empty($header['purchase_order_id']) || empty($header['receipt_no'])) {
            throw new RuntimeException('Company, PO, and receipt number are required.');
        }
        if ($lines === []) {
            throw new RuntimeException('At least one receipt line is required.');
        }
        $this->assertDocumentNumberAvailable($header);
        $this->assertStorageLocation($header);
        $this->assertPeriodOpen('purchase', $header, 'receipt_date');
        $this->assertPeriodOpen('inventory', $header, 'receipt_date');

        $poModel = new PurchaseOrderModel();
        $po = $poModel->find((int) $header['purchase_order_id']);
        if ($po === null) {
            throw new RuntimeException('Purchase order not found.');
        }

        $poStatus = (string) ($po['document_status'] ?? $po['status'] ?? 'draft');
        if (! in_array($poStatus, ['approved', 'partial_received'], true)) {
            throw new RuntimeException('Only approved or partially received PO can be received. Current status: ' . $poStatus);
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $receiptModel = new PurchaseReceiptModel();
            $receiptLineModel = new PurchaseReceiptLineModel();
            $poLineModel = new PurchaseOrderLineModel();
            $stock = new InventoryStockService();
            $movementModel = new InventoryStockMovementModel();
            $totalInventoryValue = 0.0;
            $postedLineCount = 0;
            $glWarning = null;

            $receiptModel->insert($header + [
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $receiptId = (int) $receiptModel->getInsertID();
            if ($receiptId < 1) {
                throw new RuntimeException('Failed to create purchase receipt header.');
            }

            foreach ($lines as $line) {
                $poLine = $poLineModel->find((int) ($line['purchase_order_line_id'] ?? 0));
                if ($poLine === null) {
                    throw new RuntimeException('PO line not found.');
                }
                if ((int) ($poLine['purchase_order_id'] ?? 0) !== (int) $po['id']) {
                    throw new RuntimeException('Receipt line does not belong to selected PO.');
                }

                $qtyReceive = $this->toNumber($line['qty_received'] ?? 0);
                $outstanding = $this->currentOutstanding($poLine);
                if ($qtyReceive <= 0) {
                    continue;
                }
                if ($qtyReceive > $outstanding) {
                    throw new RuntimeException('Receive qty cannot exceed outstanding qty for item ' . ($poLine['item_code'] ?? '-') . '. Outstanding: ' . $outstanding);
                }

                $itemCode = trim((string) ($poLine['item_code'] ?? ''));
                if ($itemCode === '') {
                    throw new RuntimeException('PO line item code is required before receipt can be posted.');
                }

                $movementId = $stock->stockIn([
                    'company_id' => $header['company_id'],
                    'site_id' => $header['site_id'] ?? null,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'location_id' => $header['location_id'] ?? null,
                    'item_id' => $poLine['item_id'] ?? null,
                    'item_code' => $itemCode,
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $poLine['item_name'] ?? null,
                    'uom_code' => $poLine['uom_code'] ?? 'PCS',
                    'qty' => $qtyReceive,
                    'unit_cost' => (float) ($poLine['unit_price'] ?? 0),
                    'movement_date' => $header['receipt_date'] ?? date('Y-m-d'),
                    'movement_type' => 'purchase_receipt',
                    'reference_type' => 'purchase_receipt',
                    'reference_id' => $receiptId,
                    'reference_no' => $header['receipt_no'],
                    'notes' => 'Stock in from PO ' . ($po['po_no'] ?? ''),
                ], $userId);

                $receiptLineModel->insert([
                    'purchase_receipt_id' => $receiptId,
                    'purchase_order_id' => $po['id'],
                    'purchase_order_line_id' => $poLine['id'],
                    'stock_movement_id' => $movementId,
                    'line_no' => $poLine['line_no'] ?? $poLine['po_line'] ?? 0,
                    'item_id' => $poLine['item_id'] ?? null,
                    'item_code' => $itemCode,
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $poLine['item_name'] ?? null,
                    'qty_received' => $qtyReceive,
                    'reversed_qty' => 0,
                    'uom_code' => $poLine['uom_code'] ?? 'PCS',
                    'unit_cost' => $poLine['unit_price'] ?? 0,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'location_id' => $header['location_id'] ?? null,
                ]);
                $postedLineCount++;

                $movement = $movementModel->find($movementId);
                $totalInventoryValue += (float) ($movement['stock_value'] ?? 0);
            }

            if ($postedLineCount < 1) {
                throw new RuntimeException('No receipt line can be posted. Please fill Receive Now qty greater than zero.');
            }

            $this->recalculatePoReceiptQuantities((int) $po['id'], $userId);

            try {
                $glEntryId = $this->postReceiptGl($header, $receiptId, round($totalInventoryValue, 2), $userId);
            } catch (RuntimeException $e) {
                $glEntryId = null;
                $glWarning = 'GL skipped: ' . $e->getMessage();
            }

            $receiptUpdate = [];
            if ($glEntryId !== null) {
                $receiptUpdate['gl_entry_id'] = $glEntryId;
            }
            if ($glWarning !== null) {
                $receiptUpdate['notes'] = trim(($header['notes'] ?? '') . ' ' . $glWarning);
            }
            if ($receiptUpdate !== []) {
                $receiptModel->update($receiptId, $receiptUpdate);
            }

            $this->refreshPoStatus((int) $po['id'], $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post purchase receipt.');
            }
            $db->transCommit();

            (new AuditLogService())->log('purchase.receipt', 'receipt.post', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'purchase_receipts',
                'record_id' => $receiptId,
                'record_code' => $header['receipt_no'],
                'description' => $glWarning !== null ? 'Purchase receipt posted and stock increased. ' . $glWarning : 'Purchase receipt posted, stock increased, and inventory/GRNI GL posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'inventory_value' => $totalInventoryValue, 'gl_entry_id' => $glEntryId, 'gl_warning' => $glWarning],
            ]);

            return $receiptId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function reverse(int $receiptId, ?int $userId = null, ?string $reason = null): void
    {
        $db = Database::connect();
        $db->transBegin();

        try {
            $receiptModel = new PurchaseReceiptModel();
            $receiptLineModel = new PurchaseReceiptLineModel();
            $stock = new InventoryStockService();

            $receipt = $receiptModel->find($receiptId);
            if ($receipt === null) {
                throw new RuntimeException('Purchase receipt not found.');
            }
            if ((string) ($receipt['status'] ?? '') !== 'posted') {
                throw new RuntimeException('Only posted purchase receipt can be reversed.');
            }
            if (! empty($receipt['reversed_at'])) {
                throw new RuntimeException('Purchase receipt has already been reversed.');
            }
            $invoice = (new PurchaseInvoiceModel())->where('purchase_receipt_id', $receiptId)->where('status !=', 'cancelled')->first();
            if ($invoice !== null) {
                throw new RuntimeException('Purchase receipt already has purchase invoice ' . ($invoice['invoice_no'] ?? '#' . $invoice['id']) . '. Reverse or cancel the invoice first.');
            }

            $this->assertPeriodOpen('purchase', $receipt, 'receipt_date');
            $this->assertPeriodOpen('inventory', $receipt, 'receipt_date');

            $lines = $receiptLineModel->where('purchase_receipt_id', $receiptId)->orderBy('line_no', 'ASC')->findAll();
            if ($lines === []) {
                throw new RuntimeException('Purchase receipt has no lines to reverse.');
            }

            $now = date('Y-m-d H:i:s');
            foreach ($lines as $line) {
                if (! empty($line['reversal_movement_id'])) {
                    throw new RuntimeException('Purchase receipt line has already been reversed.');
                }

                $reversalMovementId = $stock->stockOut([
                    'company_id' => $receipt['company_id'],
                    'site_id' => $receipt['site_id'] ?? null,
                    'warehouse_id' => $line['warehouse_id'] ?? $receipt['warehouse_id'] ?? null,
                    'location_id' => $line['location_id'] ?? $receipt['location_id'] ?? null,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'] ?? '',
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $line['item_name'] ?? null,
                    'uom_code' => $line['uom_code'] ?? 'PCS',
                    'qty' => (float) ($line['qty_received'] ?? 0),
                    'unit_cost' => (float) ($line['unit_cost'] ?? 0),
                    'movement_date' => $receipt['receipt_date'] ?? date('Y-m-d'),
                    'movement_type' => 'purchase_receipt_reversal',
                    'reference_type' => 'purchase_receipt_reversal',
                    'reference_id' => $receiptId,
                    'reference_no' => $receipt['receipt_no'] ?? (string) $receiptId,
                    'notes' => 'Reverse purchase receipt ' . ($receipt['receipt_no'] ?? ''),
                ], $userId);

                $receiptLineModel->update($line['id'], [
                    'reversed_qty' => (float) ($line['qty_received'] ?? 0),
                    'reversal_movement_id' => $reversalMovementId,
                    'reversed_at' => $now,
                    'reversed_by' => $userId,
                    'reversal_reason' => $reason,
                ]);
            }

            $receiptModel->update($receiptId, [
                'status' => 'reversed',
                'reversed_at' => $now,
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
            ]);

            $this->recalculatePoReceiptQuantities((int) ($receipt['purchase_order_id'] ?? 0), $userId);
            $this->refreshPoStatus((int) ($receipt['purchase_order_id'] ?? 0), $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to reverse purchase receipt.');
            }
            $db->transCommit();

            (new AuditLogService())->log('purchase.receipt', 'receipt.reverse', [
                'company_id' => $receipt['company_id'] ?? null,
                'site_id' => $receipt['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'purchase_receipts',
                'record_id' => $receiptId,
                'record_code' => $receipt['receipt_no'] ?? null,
                'description' => 'Purchase receipt reversed and stock decreased.',
                'old_values' => ['status' => 'posted'],
                'new_values' => ['status' => 'reversed', 'reason' => $reason],
            ]);
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function postReceiptGl(array $header, int $receiptId, float $inventoryValue, ?int $userId): ?int
    {
        if ($inventoryValue <= 0) {
            return null;
        }

        $companyId = (int) $header['company_id'];
        $profile = new PostingProfileService();

        return (new GeneralLedgerService())->post([
            'company_id' => $companyId,
            'site_id' => $header['site_id'] ?? null,
            'journal_no' => 'GL-' . $header['receipt_no'],
            'journal_date' => $header['receipt_date'] ?? date('Y-m-d'),
            'source_module' => 'purchase',
            'source_type' => 'purchase_receipt',
            'source_id' => $receiptId,
            'source_no' => $header['receipt_no'],
            'description' => 'Inventory receipt posting ' . $header['receipt_no'],
            'currency_code' => $header['currency_code'] ?? 'IDR',
        ], [
            [
                'account_no' => $profile->account($companyId, 'ap', 'inventory', '1300'),
                'description' => 'Purchased Inventory',
                'debit' => $inventoryValue,
                'credit' => 0,
            ],
            [
                'account_no' => $profile->account($companyId, 'ap', 'grni', '2300'),
                'description' => 'Goods Received Not Invoiced',
                'debit' => 0,
                'credit' => $inventoryValue,
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
        $existing = (new PurchaseReceiptModel())
            ->where('company_id', (int) $header['company_id'])
            ->where('receipt_no', (string) $header['receipt_no'])
            ->first();

        if ($existing !== null) {
            throw new RuntimeException('Purchase receipt number already exists and cannot be posted again: ' . $header['receipt_no'] . '.');
        }
    }

    private function assertStorageLocation(array $header): void
    {
        $warehouseId = (int) ($header['warehouse_id'] ?? 0);
        $locationId = (int) ($header['location_id'] ?? 0);
        if ($warehouseId < 1 || $locationId < 1) {
            throw new RuntimeException('Warehouse and location are required before posting purchase receipt.');
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
            throw new RuntimeException('Selected warehouse is not valid for this receipt.');
        }
        if ($locationRow === null) {
            throw new RuntimeException('Selected location is not valid for this receipt.');
        }
        if ((int) ($locationRow['warehouse_id'] ?? 0) !== $warehouseId) {
            throw new RuntimeException('Selected location does not belong to selected warehouse.');
        }
    }

    private function recalculatePoReceiptQuantities(int $poId, ?int $userId = null): void
    {
        if ($poId < 1) {
            return;
        }

        $db = Database::connect();
        $lineModel = new PurchaseOrderLineModel();
        $lines = $lineModel->where('purchase_order_id', $poId)->findAll();

        foreach ($lines as $line) {
            $receivedRow = $db->table('purchase_receipt_lines prl')
                ->select('COALESCE(SUM(prl.qty_received), 0) AS qty_received', false)
                ->join('purchase_receipts pr', 'pr.id = prl.purchase_receipt_id', 'inner')
                ->where('prl.purchase_order_line_id', (int) $line['id'])
                ->where('pr.status', 'posted')
                ->where('pr.reversed_at', null)
                ->get()
                ->getRowArray();

            $qtyReceived = round((float) ($receivedRow['qty_received'] ?? 0), 4);
            $qtyOrdered = round($this->toNumber($line['qty_ordered'] ?? $line['qty'] ?? 0), 4);
            $qtyOutstanding = max(0.0, round($qtyOrdered - $qtyReceived, 4));

            $lineModel->update($line['id'], [
                'qty_received' => $qtyReceived,
                'qty_outstanding' => $qtyOutstanding,
                'line_status' => $qtyReceived <= 0 ? 'open' : ($qtyOutstanding <= 0 ? 'received' : 'partial_received'),
                'updated_by' => $userId,
            ]);
        }
    }

    private function refreshPoStatus(int $poId, ?int $userId = null): void
    {
        if ($poId < 1) {
            return;
        }

        $lineModel = new PurchaseOrderLineModel();
        $lines = $lineModel->where('purchase_order_id', $poId)->findAll();
        $totalOutstanding = 0.0;
        $totalReceived = 0.0;
        foreach ($lines as $line) {
            $totalOutstanding += (float) ($line['qty_outstanding'] ?? 0);
            $totalReceived += (float) ($line['qty_received'] ?? 0);
        }

        $status = $totalOutstanding <= 0 ? 'received' : ($totalReceived > 0 ? 'partial_received' : 'approved');
        (new PurchaseOrderModel())->update($poId, [
            'status' => $status,
            'document_status' => $status,
            'updated_by' => $userId,
        ]);
    }

    private function currentOutstanding(array $poLine): float
    {
        if (array_key_exists('qty_outstanding', $poLine) && $poLine['qty_outstanding'] !== null && $poLine['qty_outstanding'] !== '') {
            return max(0.0, $this->toNumber($poLine['qty_outstanding']));
        }

        $ordered = $this->toNumber($poLine['qty_ordered'] ?? $poLine['qty'] ?? 0);
        $received = $this->toNumber($poLine['qty_received'] ?? 0);
        return max(0.0, $ordered - $received);
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
