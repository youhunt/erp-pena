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
                $poLine = $poLineModel->find((int) $line['purchase_order_line_id']);
                if ($poLine === null) {
                    throw new RuntimeException('PO line not found.');
                }
                if ((int) ($poLine['purchase_order_id'] ?? 0) !== (int) $po['id']) {
                    throw new RuntimeException('Receipt line does not belong to selected PO.');
                }

                $qtyReceive = $this->toNumber($line['qty_received'] ?? 0);
                $outstanding = $this->toNumber($poLine['qty_outstanding'] ?? $poLine['qty'] ?? 0);
                if ($qtyReceive <= 0) {
                    continue;
                }
                if ($qtyReceive > $outstanding) {
                    throw new RuntimeException('Receive qty cannot exceed outstanding qty for item ' . ($poLine['item_code'] ?? '-'));
                }

                $movementId = $stock->stockIn([
                    'company_id' => $header['company_id'],
                    'site_id' => $header['site_id'] ?? null,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'location_id' => $header['location_id'] ?? null,
                    'item_id' => $poLine['item_id'] ?? null,
                    'item_code' => $poLine['item_code'] ?? '',
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
                    'line_no' => $poLine['line_no'],
                    'item_id' => $poLine['item_id'] ?? null,
                    'item_code' => $poLine['item_code'] ?? null,
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $poLine['item_name'] ?? null,
                    'qty_received' => $qtyReceive,
                    'uom_code' => $poLine['uom_code'] ?? 'PCS',
                    'unit_cost' => $poLine['unit_price'] ?? 0,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'location_id' => $header['location_id'] ?? null,
                ]);
                $postedLineCount++;

                $newReceived = $this->toNumber($poLine['qty_received'] ?? 0) + $qtyReceive;
                $newOutstanding = max(0, $outstanding - $qtyReceive);
                $poLineModel->update($poLine['id'], [
                    'qty_received' => $newReceived,
                    'qty_outstanding' => $newOutstanding,
                    'line_status' => $newOutstanding <= 0 ? 'received' : 'partial_received',
                ]);

                $movement = $movementModel->find($movementId);
                $totalInventoryValue += (float) ($movement['stock_value'] ?? 0);
            }

            if ($postedLineCount < 1) {
                throw new RuntimeException('No receipt line can be posted. Please fill Receive Now qty greater than zero.');
            }

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
            $poLineModel = new PurchaseOrderLineModel();
            $stock = new InventoryStockService();
            $movementModel = new InventoryStockMovementModel();

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
            $invoice = (new PurchaseInvoiceModel())
                ->where('purchase_receipt_id', $receiptId)
                ->where('status !=', 'cancelled')
                ->first();
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

                $poLine = $poLineModel->find((int) ($line['purchase_order_line_id'] ?? 0));
                if ($poLine !== null) {
                    $newReceived = max(0, (float) ($poLine['qty_received'] ?? 0) - (float) ($line['qty_received'] ?? 0));
                    $qtyOrdered = (float) ($poLine['qty_ordered'] ?? $poLine['qty'] ?? 0);
                    $newOutstanding = max(0, $qtyOrdered - $newReceived);
                    $poLineModel->update($poLine['id'], [
                        'qty_received' => $newReceived,
                        'qty_outstanding' => $newOutstanding,
                        'line_status' => $newReceived <= 0 ? 'approved' : ($newOutstanding <= 0 ? 'received' : 'partial_received'),
                    ]);
                }
            }

            $receiptModel->update($receiptId, [
                'status' => 'reversed',
                'reversed_at' => $now,
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
            ]);
            $this->refreshPoStatus((int) ($receipt['purchase_order_id'] ?? 0), $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to reverse purchase receipt.');
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function refreshPoStatus(int $poId, ?int $userId): void
    {
        if ($poId < 1) {
            return;
        }

        $lineModel = new PurchaseOrderLineModel();
        $poModel = new PurchaseOrderModel();
        $lines = $lineModel->where('purchase_order_id', $poId)->findAll();
        if ($lines === []) {
            return;
        }

        $allReceived = true;
        $anyReceived = false;
        foreach ($lines as $line) {
            $received = (float) ($line['qty_received'] ?? 0);
            $outstanding = (float) ($line['qty_outstanding'] ?? $line['qty'] ?? 0);
            if ($received > 0) {
                $anyReceived = true;
            }
            if ($outstanding > 0) {
                $allReceived = false;
            }
        }

        $status = $allReceived ? 'received' : ($anyReceived ? 'partial_received' : 'approved');
        $poModel->update($poId, ['status' => $status, 'document_status' => $status, 'updated_by' => $userId]);
    }

    private function postReceiptGl(array $header, int $receiptId, float $inventoryValue, ?int $userId): ?int
    {
        if ($inventoryValue <= 0) {
            return null;
        }

Need ensure file still complete with postReceiptGl etc? We replaced full up to maybe truncated in payload? Actually we ended full file? we included until reverse/refresh, but not postReceiptGl? Wait content ended at postReceiptGl incomplete? Need fetch rest. 
