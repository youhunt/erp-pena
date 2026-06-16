<?php

namespace App\Services\Purchase;

use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseReceiptLineModel;
use App\Models\PurchaseReceiptModel;
use App\Models\InventoryStockMovementModel;
use App\Services\AuditLogService;
use App\Services\Finance\GeneralLedgerService;
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

                $qtyReceive = (float) ($line['qty_received'] ?? 0);
                $outstanding = (float) ($poLine['qty_outstanding'] ?? $poLine['qty'] ?? 0);
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

                $newReceived = (float) ($poLine['qty_received'] ?? 0) + $qtyReceive;
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
                throw new RuntimeException('No receipt line can be posted.');
            }

            $glEntryId = $this->postReceiptGl($header, $receiptId, round($totalInventoryValue, 2), $userId);
            if ($glEntryId !== null) {
                $receiptModel->update($receiptId, ['gl_entry_id' => $glEntryId]);
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
                'description' => 'Purchase receipt posted, stock increased, and inventory/GRNI GL posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'inventory_value' => $totalInventoryValue, 'gl_entry_id' => $glEntryId],
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
                    'movement_type' => 'purchase_receipt_reversal',
                    'reference_type' => 'purchase_receipt_reversal',
                    'reference_id' => $receiptId,
                    'reference_no' => ($receipt['receipt_no'] ?? 'RCPT') . '-REV',
                    'notes' => trim(($reason ?? '') . ' Reversal for receipt ' . ($receipt['receipt_no'] ?? '')),
                ], $userId);

                $receiptLineModel->update((int) $line['id'], [
                    'reversal_movement_id' => $reversalMovementId,
                    'updated_at' => $now,
                ]);

                if (! empty($line['purchase_order_line_id'])) {
                    $poLine = $poLineModel->find((int) $line['purchase_order_line_id']);
                    if ($poLine !== null) {
                        $qty = (float) ($line['qty_received'] ?? 0);
                        $newReceived = max(0, (float) ($poLine['qty_received'] ?? 0) - $qty);
                        $ordered = (float) ($poLine['qty_ordered'] ?? $poLine['qty'] ?? 0);
                        $newOutstanding = max(0, $ordered - $newReceived);
                        $poLineModel->update((int) $poLine['id'], [
                            'qty_received' => $newReceived,
                            'qty_outstanding' => $newOutstanding,
                            'line_status' => $newReceived <= 0 ? 'approved' : ($newOutstanding <= 0 ? 'received' : 'partial_received'),
                        ]);
                    }
                }

                $movementModel->find($reversalMovementId);
            }

            $receiptModel->update($receiptId, [
                'status' => 'reversed',
                'reversed_at' => $now,
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
                'updated_by' => $userId,
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
}
