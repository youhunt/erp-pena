<?php

namespace App\Services\Purchase;

use App\Models\GlEntryLineModel;
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
use App\Services\Support\PostingIntegrityGuard;
use App\Services\Support\TransactionDocumentGuard;
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
        (new TransactionDocumentGuard())->assertSameTenant($po, $header, 'Purchase order');

        $poStatus = (string) ($po['document_status'] ?? $po['status'] ?? 'draft');
        if (! in_array($poStatus, ['approved', 'partial_received'], true)) {
            throw new RuntimeException('Only approved or partially received PO can be received. Current status: ' . $poStatus);
        }

        $header = array_replace($header, [
            'company_id' => $po['company_id'],
            'site_id' => $po['site_id'] ?? null,
            'purchase_order_id' => $po['id'],
            'po_no' => $po['po_no'] ?? $po['document_no'] ?? null,
            'supplier_id' => $po['supplier_id'] ?? null,
            'supplier_code' => $po['supplier_code'] ?? $po['supplier'] ?? null,
            'supplier_name' => $po['supplier_name'] ?? null,
        ]);

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

            $receiptModel->insert(array_replace($header, [
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
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
                $unitCost = array_key_exists('unit_cost', $line)
                    ? $this->toNumber($line['unit_cost'])
                    : (float) ($poLine['unit_price'] ?? 0);
                if ($unitCost < 0) {
                    throw new RuntimeException('Unit cost cannot be negative for item ' . ($poLine['item_code'] ?? '-') . '.');
                }

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
                    'unit_cost' => $unitCost,
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
                    'unit_cost' => $unitCost,
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

            $inventoryValue = round($totalInventoryValue, 2);
            $glEntryId = $this->postReceiptGl($header, $receiptId, $inventoryValue, $userId);
            (new PostingIntegrityGuard())->assertGlEntryForAmount($inventoryValue, $glEntryId, 'Purchase receipt');

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
                'description' => $glEntryId !== null
                    ? 'Purchase receipt posted, stock increased, and inventory/GRNI GL posted.'
                    : 'Purchase receipt posted and stock increased.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'gl_entry_id' => $glEntryId],
            ]);

            return $receiptId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    // existing methods below are unchanged
}
