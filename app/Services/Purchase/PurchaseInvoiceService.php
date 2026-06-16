<?php

namespace App\Services\Purchase;

use App\Models\ApPayableModel;
use App\Models\PurchaseInvoiceLineModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseReceiptLineModel;
use App\Models\PurchaseReceiptModel;
use App\Services\AuditLogService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Finance\PostingProfileService;
use Config\Database;
use RuntimeException;
use Throwable;

class PurchaseInvoiceService
{
    public function postManual(array $header, array $rawLines, ?int $userId = null): int
    {
        if (empty($header['company_id']) || empty($header['invoice_no']) || empty($header['supplier_code'])) {
            throw new RuntimeException('Company, invoice number, and supplier are required.');
        }
        (new PeriodCloseService())->assertOpen('ap', (int) $header['company_id'], (string) ($header['invoice_date'] ?? date('Y-m-d')), ! empty($header['site_id']) ? (int) $header['site_id'] : null);

        $lines = $this->normalizeManualLines($rawLines);
        if ($lines === []) {
            throw new RuntimeException('Manual A/P invoice requires at least one line.');
        }

        $subtotal = round(array_sum(array_column($lines, 'line_subtotal')), 6);
        $discount = round(array_sum(array_column($lines, 'discount_amount')), 6);
        $tax = round(array_sum(array_column($lines, 'tax_amount')), 6);
        $total = round(array_sum(array_column($lines, 'line_total')), 6);
        if ($total <= 0) {
            throw new RuntimeException('Manual A/P invoice total must be greater than zero.');
        }

        $invoiceModel = new PurchaseInvoiceModel();
        $existing = $invoiceModel
            ->where('company_id', (int) $header['company_id'])
            ->where('invoice_no', (string) $header['invoice_no'])
            ->where('deleted_at', null)
            ->first();
        if ($existing !== null) {
            throw new RuntimeException('Purchase invoice number already exists.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $invoiceDate = (string) ($header['invoice_date'] ?? date('Y-m-d'));
            $dueDate = $header['due_date'] ?? $invoiceDate;
            $invoiceModel->insert($header + [
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'source_type' => 'manual',
                'status' => 'open',
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'outstanding_amount' => $total,
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $invoiceId = (int) $invoiceModel->getInsertID();
            if ($invoiceId < 1) {
                throw new RuntimeException('Failed to create purchase invoice header.');
            }

            $lineModel = new PurchaseInvoiceLineModel();
            foreach ($lines as $line) {
                unset($line['line_subtotal']);
                $lineModel->insert($line + ['purchase_invoice_id' => $invoiceId]);
            }

            (new ApPayableModel())->insert([
                'company_id' => $header['company_id'],
                'site_id' => $header['site_id'] ?? null,
                'purchase_invoice_id' => $invoiceId,
                'invoice_no' => $header['invoice_no'],
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'supplier_id' => $header['supplier_id'] ?? null,
                'supplier_code' => $header['supplier_code'] ?? null,
                'supplier_name' => $header['supplier_name'] ?? null,
                'currency_code' => $header['currency_code'] ?? 'IDR',
                'invoice_amount' => $total,
                'paid_amount' => 0,
                'outstanding_amount' => $total,
                'status' => 'open',
            ]);

            $glEntryId = $this->postPurchaseInvoiceGl($header, $invoiceId, $invoiceDate, $subtotal, $discount, $tax, $total, $userId, 'manual_ap_invoice');
            $invoiceModel->update($invoiceId, ['gl_entry_id' => $glEntryId]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post manual A/P invoice.');
            }
            $db->transCommit();

            (new AuditLogService())->log('finance.ap', 'manual_ap_invoice.post', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'purchase_invoices',
                'record_id' => $invoiceId,
                'record_code' => $header['invoice_no'],
                'description' => 'Manual A/P invoice posted, payable opened, and GL posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'total' => $total, 'gl_entry_id' => $glEntryId],
            ]);

            return $invoiceId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function postFromReceipt(array $header, ?int $userId = null): int
    {
        if (empty($header['company_id']) || empty($header['purchase_receipt_id']) || empty($header['invoice_no'])) {
            throw new RuntimeException('Company, receipt, and invoice number are required.');
        }
        (new PeriodCloseService())->assertOpen('ap', (int) $header['company_id'], (string) ($header['invoice_date'] ?? date('Y-m-d')), ! empty($header['site_id']) ? (int) $header['site_id'] : null);

        $receiptModel = new PurchaseReceiptModel();
        $receipt = $receiptModel->find((int) $header['purchase_receipt_id']);
        if ($receipt === null) {
            throw new RuntimeException('Purchase receipt not found.');
        }
        if ((string) ($receipt['status'] ?? '') === 'invoiced') {
            throw new RuntimeException('Purchase receipt already invoiced.');
        }

        $invoiceModel = new PurchaseInvoiceModel();
        $existing = $invoiceModel
            ->where('purchase_receipt_id', (int) $header['purchase_receipt_id'])
            ->where('deleted_at', null)
            ->first();
        if ($existing !== null) {
            throw new RuntimeException('Purchase receipt already has invoice ' . ($existing['invoice_no'] ?? '#'. $existing['id']) . '.');
        }

        $receiptLines = (new PurchaseReceiptLineModel())
            ->where('purchase_receipt_id', (int) $header['purchase_receipt_id'])
            ->orderBy('line_no', 'ASC')
            ->findAll();
        if ($receiptLines === []) {
            throw new RuntimeException('Purchase receipt has no lines.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $invoiceLineModel = new PurchaseInvoiceLineModel();
            $payableModel = new ApPayableModel();
            $poLineModel = new PurchaseOrderLineModel();

            $lines = [];
            $subtotal = 0.0;
            $discount = 0.0;
            $tax = 0.0;

            foreach ($receiptLines as $receiptLine) {
                $poLine = ! empty($receiptLine['purchase_order_line_id'])
                    ? $poLineModel->find((int) $receiptLine['purchase_order_line_id'])
                    : null;
                $qty = (float) ($receiptLine['qty_received'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $unitCost = (float) ($receiptLine['unit_cost'] ?? 0);
                $orderedQty = $poLine !== null ? (float) ($poLine['qty_ordered'] ?? $poLine['qty'] ?? 0) : $qty;
                $ratio = $orderedQty > 0 ? $qty / $orderedQty : 1.0;
                $lineDiscount = round((float) ($poLine['discount_amount'] ?? 0) * $ratio, 6);
                $lineTax = round((float) ($poLine['tax_amount'] ?? 0) * $ratio, 6);
                $lineSubtotal = round($qty * $unitCost, 6);
                $lineTotal = round($lineSubtotal - $lineDiscount + $lineTax, 6);

                $subtotal += $lineSubtotal;
                $discount += $lineDiscount;
                $tax += $lineTax;
                $lines[] = [
                    'purchase_order_id' => $receiptLine['purchase_order_id'] ?? $receipt['purchase_order_id'] ?? null,
                    'purchase_order_line_id' => $receiptLine['purchase_order_line_id'] ?? null,
                    'purchase_receipt_id' => $receipt['id'],
                    'purchase_receipt_line_id' => $receiptLine['id'],
                    'line_no' => $receiptLine['line_no'],
                    'item_id' => $receiptLine['item_id'] ?? null,
                    'item_code' => $receiptLine['item_code'] ?? null,
                    'item_name' => $receiptLine['item_name'] ?? null,
                    'qty_invoiced' => $qty,
                    'uom_code' => $receiptLine['uom_code'] ?? 'PCS',
                    'unit_cost' => $unitCost,
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ];
            }

            if ($lines === []) {
                throw new RuntimeException('No receipt qty can be invoiced.');
            }

            $total = round($subtotal - $discount + $tax, 6);
            $invoiceDate = (string) ($header['invoice_date'] ?? date('Y-m-d'));
            $dueDate = $header['due_date'] ?? $invoiceDate;

            $invoiceModel->insert($header + [
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'purchase_order_id' => $receipt['purchase_order_id'] ?? null,
                'purchase_receipt_id' => $receipt['id'],
                'po_no' => $receipt['po_no'] ?? null,
                'receipt_no' => $receipt['receipt_no'] ?? null,
                'supplier_id' => $receipt['supplier_id'] ?? null,
                'supplier_code' => $receipt['supplier_code'] ?? null,
                'supplier_name' => $receipt['supplier_name'] ?? null,
                'status' => 'open',
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'outstanding_amount' => $total,
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $invoiceId = (int) $invoiceModel->getInsertID();
            if ($invoiceId < 1) {
                throw new RuntimeException('Failed to create purchase invoice header.');
            }

            foreach ($lines as $line) {
                $invoiceLineModel->insert($line + ['purchase_invoice_id' => $invoiceId]);
            }

            $payableModel->insert([
                'company_id' => $header['company_id'],
                'site_id' => $header['site_id'] ?? null,
                'purchase_invoice_id' => $invoiceId,
                'invoice_no' => $header['invoice_no'],
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'supplier_id' => $receipt['supplier_id'] ?? null,
                'supplier_code' => $receipt['supplier_code'] ?? null,
                'supplier_name' => $receipt['supplier_name'] ?? null,
                'currency_code' => $header['currency_code'] ?? 'IDR',
                'invoice_amount' => $total,
                'paid_amount' => 0,
                'outstanding_amount' => $total,
                'status' => 'open',
            ]);

            $glEntryId = $this->postPurchaseInvoiceGl($header, $invoiceId, $invoiceDate, $subtotal, $discount, $tax, $total, $userId);
            $invoiceModel->update($invoiceId, ['gl_entry_id' => $glEntryId]);

            $receiptModel->update((int) $receipt['id'], [
                'status' => 'invoiced',
                'updated_by' => $userId,
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post purchase invoice.');
            }
            $db->transCommit();

            (new AuditLogService())->log('finance.ap', 'purchase_invoice.post', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'purchase_invoices',
                'record_id' => $invoiceId,
                'record_code' => $header['invoice_no'],
                'description' => 'Purchase invoice posted, AP payable opened, and GL posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'total' => $total, 'gl_entry_id' => $glEntryId],
            ]);

            return $invoiceId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function normalizeManualLines(array $rawLines): array
    {
        $lines = [];
        $lineNo = 1;

        foreach ($rawLines as $rawLine) {
            $description = trim((string) ($rawLine['item_name'] ?? $rawLine['description'] ?? ''));
            $qty = round((float) ($rawLine['qty'] ?? 0), 4);
            $unitCost = round((float) ($rawLine['unit_cost'] ?? 0), 6);
            if ($description === '' || $qty <= 0 || $unitCost < 0) {
                continue;
            }

            $lineSubtotal = round($qty * $unitCost, 6);
            $discount = round((float) ($rawLine['discount_amount'] ?? 0), 6);
            $tax = round((float) ($rawLine['tax_amount'] ?? 0), 6);
            $lineTotal = round($lineSubtotal - $discount + $tax, 6);
            if ($lineTotal <= 0) {
                continue;
            }

            $lines[] = [
                'line_no' => $lineNo,
                'item_id' => ! empty($rawLine['item_id']) ? (int) $rawLine['item_id'] : null,
                'item_code' => trim((string) ($rawLine['item_code'] ?? '')),
                'item_name' => $description,
                'qty_invoiced' => $qty,
                'uom_code' => trim((string) ($rawLine['uom_code'] ?? 'PCS')),
                'unit_cost' => $unitCost,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'line_subtotal' => $lineSubtotal,
                'line_total' => $lineTotal,
            ];
            $lineNo++;
        }

        return $lines;
    }

    private function postPurchaseInvoiceGl(array $header, int $invoiceId, string $invoiceDate, float $subtotal, float $discount, float $tax, float $total, ?int $userId, string $sourceType = 'purchase_invoice'): int
    {
        $companyId = (int) $header['company_id'];
        $profile = new PostingProfileService();
        $inventoryAmount = round($subtotal - $discount, 2);
        $taxAmount = round($tax, 2);
        $totalAmount = round($total, 2);

        $lines = [
            [
                'account_no' => $sourceType === 'manual_ap_invoice'
                    ? $profile->account($companyId, 'ap', 'manual_expense', '6200')
                    : $profile->account($companyId, 'ap', 'grni', '2300'),
                'description' => $sourceType === 'manual_ap_invoice' ? 'Manual A/P expense' : 'Goods Received Not Invoiced',
                'debit' => $inventoryAmount,
                'credit' => 0,
            ],
        ];

        if ($taxAmount > 0) {
            $lines[] = [
                'account_no' => $profile->account($companyId, 'ap', 'input_vat', '1400'),
                'description' => 'Input VAT',
                'debit' => $taxAmount,
                'credit' => 0,
            ];
        }

        $lines[] = [
            'account_no' => $profile->account($companyId, 'ap', 'payable', '2100'),
            'description' => 'Accounts Payable',
            'debit' => 0,
            'credit' => $totalAmount,
        ];

        return (new GeneralLedgerService())->post([
            'company_id' => $companyId,
            'site_id' => $header['site_id'] ?? null,
            'journal_no' => 'GL-' . $header['invoice_no'],
            'journal_date' => $invoiceDate,
            'source_module' => 'ap',
            'source_type' => $sourceType,
            'source_id' => $invoiceId,
            'source_no' => $header['invoice_no'],
            'description' => ($sourceType === 'manual_ap_invoice' ? 'Manual A/P invoice ' : 'Purchase invoice ') . $header['invoice_no'],
            'currency_code' => $header['currency_code'] ?? 'IDR',
        ], $lines, $userId);
    }
}
