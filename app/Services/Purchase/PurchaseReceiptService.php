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
                $unitCost = array_key_exists('unit_cost', $line) && $line['unit_cost'] !== '' && $line['unit_cost'] !== null
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
                $totalInventoryValue += (float) ($movement['stock_value'] ?? round($qtyReceive * $unitCost, 2));
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
                'description' => 'Purchase receipt posted, stock increased, and inventory/GRNI GL posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'gl_entry_id' => $glEntryId],
            ]);

            return $receiptId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function reverse(int $receiptId, ?int $userId = null, ?string $reason = null): void
    {
        $receiptModel = new PurchaseReceiptModel();
        $lineModel = new PurchaseReceiptLineModel();
        $receipt = $receiptModel->find($receiptId);
        if ($receipt === null) {
            throw new RuntimeException('Purchase receipt not found.');
        }
        if (($receipt['status'] ?? '') === 'reversed') {
            throw new RuntimeException('Purchase receipt is already reversed.');
        }
        if (($receipt['status'] ?? '') !== 'posted') {
            throw new RuntimeException('Only posted purchase receipt can be reversed.');
        }
        if ($this->hasActiveInvoice($receiptId)) {
            throw new RuntimeException('Purchase receipt already has active invoice. Cancel invoice first before reversing receipt.');
        }

        $this->assertPeriodOpen('purchase', $receipt, 'receipt_date');
        $this->assertPeriodOpen('inventory', $receipt, 'receipt_date');

        $db = Database::connect();
        $db->transBegin();
        try {
            $lines = $lineModel->where('purchase_receipt_id', $receiptId)->findAll();
            $stock = new InventoryStockService();
            $totalReversalValue = 0.0;
            $now = date('Y-m-d H:i:s');

            foreach ($lines as $line) {
                $qty = (float) ($line['qty_received'] ?? 0) - (float) ($line['reversed_qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $unitCost = (float) ($line['unit_cost'] ?? 0);
                $movementId = $stock->stockOut([
                    'company_id' => $receipt['company_id'],
                    'site_id' => $receipt['site_id'] ?? null,
                    'warehouse_id' => $line['warehouse_id'] ?? $receipt['warehouse_id'] ?? null,
                    'location_id' => $line['location_id'] ?? $receipt['location_id'] ?? null,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'],
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $line['item_name'] ?? null,
                    'uom_code' => $line['uom_code'] ?? 'PCS',
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'movement_date' => date('Y-m-d'),
                    'movement_type' => 'purchase_receipt_reversal',
                    'reference_type' => 'purchase_receipt_reversal',
                    'reference_id' => $receiptId,
                    'reference_no' => $receipt['receipt_no'],
                    'notes' => 'Reverse purchase receipt ' . ($receipt['receipt_no'] ?? ''),
                ], $userId);

                $lineModel->update((int) $line['id'], [
                    'reversed_qty' => (float) ($line['reversed_qty'] ?? 0) + $qty,
                    'reversal_movement_id' => $movementId,
                    'reversed_at' => $now,
                    'reversed_by' => $userId,
                    'reversal_reason' => $reason,
                ]);
                $totalReversalValue += round($qty * $unitCost, 2);
            }

            $reversalGlEntryId = $this->postReceiptReversalGl($receipt, $receiptId, round($totalReversalValue, 2), $userId);
            if ($reversalGlEntryId !== null) {
                (new PostingIntegrityGuard())->assertGlEntryForAmount(round($totalReversalValue, 2), $reversalGlEntryId, 'Purchase receipt reversal');
            }

            $receiptModel->update($receiptId, [
                'status' => 'reversed',
                'reversed_at' => $now,
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
                'reversal_gl_entry_id' => $reversalGlEntryId,
                'updated_by' => $userId,
            ]);

            if (! empty($receipt['purchase_order_id'])) {
                $this->recalculatePoReceiptQuantities((int) $receipt['purchase_order_id'], $userId);
                $this->refreshPoStatus((int) $receipt['purchase_order_id'], $userId);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to reverse purchase receipt.');
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function postReceiptGl(array $header, int $receiptId, float $inventoryValue, ?int $userId): ?int
    {
        $inventoryValue = round($inventoryValue, 2);
        if ($inventoryValue <= 0) {
            return null;
        }
        $companyId = (int) $header['company_id'];
        $profile = new PostingProfileService();
        $inventoryAccount = $profile->account($companyId, 'ap', 'inventory', '1300');
        $grniAccount = $profile->account($companyId, 'ap', 'grni', '2300');
        $receiptNo = (string) ($header['receipt_no'] ?? ('PR-' . $receiptId));

        return (new GeneralLedgerService())->post([
            'company_id' => $companyId,
            'site_id' => $header['site_id'] ?? null,
            'journal_no' => 'GL-PR-' . $receiptNo,
            'journal_date' => (string) ($header['receipt_date'] ?? date('Y-m-d')),
            'source_module' => 'purchase',
            'source_type' => 'purchase_receipt',
            'source_id' => $receiptId,
            'source_no' => $receiptNo,
            'description' => 'Purchase receipt ' . $receiptNo,
            'currency_code' => 'IDR',
        ], [
            ['account_no' => $inventoryAccount, 'description' => 'Inventory receipt', 'debit' => $inventoryValue, 'credit' => 0],
            ['account_no' => $grniAccount, 'description' => 'GRNI from purchase receipt', 'debit' => 0, 'credit' => $inventoryValue],
        ], $userId);
    }

    private function postReceiptReversalGl(array $receipt, int $receiptId, float $amount, ?int $userId): ?int
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }
        $companyId = (int) $receipt['company_id'];
        $profile = new PostingProfileService();
        $inventoryAccount = $profile->account($companyId, 'ap', 'inventory', '1300');
        $grniAccount = $profile->account($companyId, 'ap', 'grni', '2300');
        $receiptNo = (string) ($receipt['receipt_no'] ?? ('PR-' . $receiptId));

        return (new GeneralLedgerService())->post([
            'company_id' => $companyId,
            'site_id' => $receipt['site_id'] ?? null,
            'journal_no' => 'GL-PR-REV-' . $receiptNo . '-' . date('His'),
            'journal_date' => date('Y-m-d'),
            'source_module' => 'purchase',
            'source_type' => 'purchase_receipt_reversal',
            'source_id' => $receiptId,
            'source_no' => $receiptNo,
            'description' => 'Reverse purchase receipt ' . $receiptNo,
            'currency_code' => 'IDR',
        ], [
            ['account_no' => $grniAccount, 'description' => 'Reverse GRNI', 'debit' => $amount, 'credit' => 0],
            ['account_no' => $inventoryAccount, 'description' => 'Reverse inventory receipt', 'debit' => 0, 'credit' => $amount],
        ], $userId);
    }

    private function recalculatePoReceiptQuantities(int $poId, ?int $userId = null): void
    {
        $db = Database::connect();
        $lineModel = new PurchaseOrderLineModel();
        $lines = $lineModel->where('purchase_order_id', $poId)->findAll();
        foreach ($lines as $line) {
            $qtyRow = $db->table('purchase_receipt_lines prl')
                ->select('COALESCE(SUM(prl.qty_received - COALESCE(prl.reversed_qty, 0)), 0) AS qty_received', false)
                ->join('purchase_receipts pr', 'pr.id = prl.purchase_receipt_id', 'inner')
                ->where('prl.purchase_order_line_id', (int) $line['id'])
                ->where('pr.status', 'posted')
                ->get()
                ->getRowArray();

            $qtyReceived = round((float) ($qtyRow['qty_received'] ?? 0), 4);
            $qtyOrdered = round($this->toNumber($line['qty_ordered'] ?? $line['qty'] ?? 0), 4);
            $qtyOutstanding = max(0.0, round($qtyOrdered - $qtyReceived, 4));
            $lineModel->update((int) $line['id'], [
                'qty_received' => $qtyReceived,
                'qty_outstanding' => $qtyOutstanding,
                'line_status' => $qtyReceived <= 0 ? 'open' : ($qtyOutstanding <= 0 ? 'received' : 'partial_received'),
                'updated_by' => $userId,
            ]);
        }
    }

    private function refreshPoStatus(int $poId, ?int $userId = null): void
    {
        $lines = (new PurchaseOrderLineModel())->where('purchase_order_id', $poId)->findAll();
        $totalOutstanding = 0.0;
        $totalReceived = 0.0;
        foreach ($lines as $line) {
            $totalOutstanding += (float) ($line['qty_outstanding'] ?? 0);
            $totalReceived += (float) ($line['qty_received'] ?? 0);
        }
        $status = $totalOutstanding <= 0 && $totalReceived > 0 ? 'received' : ($totalReceived > 0 ? 'partial_received' : 'approved');
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
        return max(0.0, $this->toNumber($poLine['qty_ordered'] ?? $poLine['qty'] ?? 0) - $this->toNumber($poLine['qty_received'] ?? 0));
    }

    private function hasActiveInvoice(int $receiptId): bool
    {
        $model = new PurchaseInvoiceModel();
        $model->where('purchase_receipt_id', $receiptId)->where('status !=', 'cancelled');
        return $model->first() !== null;
    }

    private function assertDocumentNumberAvailable(array $header): void
    {
        $existing = (new PurchaseReceiptModel())
            ->where('company_id', (int) $header['company_id'])
            ->where('receipt_no', (string) $header['receipt_no'])
            ->first();
        if ($existing !== null) {
            throw new RuntimeException('Receipt number already exists: ' . $header['receipt_no']);
        }
    }

    private function assertStorageLocation(array $header): void
    {
        if (empty($header['warehouse_id']) || empty($header['location_id'])) {
            throw new RuntimeException('Warehouse and location are required.');
        }
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
