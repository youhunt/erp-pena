<?php

namespace App\Services\Sales;

use App\Models\ArReceivableModel;
use App\Models\ArReceiptModel;
use App\Models\GlEntryLineModel;
use App\Models\SalesDeliveryLineModel;
use App\Models\SalesDeliveryModel;
use App\Models\SalesInvoiceLineModel;
use App\Models\SalesInvoiceModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\AuditLogService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Finance\PostingProfileService;
use Config\Database;
use RuntimeException;
use Throwable;

class SalesInvoiceService
{
    public function postManual(array $header, array $rawLines, ?int $userId = null): int
    {
        if (empty($header['company_id']) || empty($header['invoice_no']) || empty($header['customer_code'])) {
            throw new RuntimeException('Company, invoice number, and customer are required.');
        }
        (new PeriodCloseService())->assertOpen('ar', (int) $header['company_id'], (string) ($header['invoice_date'] ?? date('Y-m-d')), ! empty($header['site_id']) ? (int) $header['site_id'] : null);

        $lines = $this->normalizeManualLines($rawLines);
        if ($lines === []) {
            throw new RuntimeException('Manual A/R invoice requires at least one line.');
        }

        $subtotal = round(array_sum(array_column($lines, 'line_subtotal')), 6);
        $discount = round(array_sum(array_column($lines, 'discount_amount')), 6);
        $tax = round(array_sum(array_column($lines, 'tax_amount')), 6);
        $total = round(array_sum(array_column($lines, 'line_total')), 6);
        if ($total <= 0) {
            throw new RuntimeException('Manual A/R invoice total must be greater than zero.');
        }

        $invoiceModel = new SalesInvoiceModel();
        $existing = $invoiceModel
            ->where('company_id', (int) $header['company_id'])
            ->where('invoice_no', (string) $header['invoice_no'])
            ->where('deleted_at', null)
            ->first();
        if ($existing !== null) {
            throw new RuntimeException('Sales invoice number already exists.');
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
                throw new RuntimeException('Failed to create sales invoice header.');
            }

            $lineModel = new SalesInvoiceLineModel();
            foreach ($lines as $line) {
                unset($line['line_subtotal']);
                $lineModel->insert($line + ['sales_invoice_id' => $invoiceId]);
            }

            (new ArReceivableModel())->insert([
                'company_id' => $header['company_id'],
                'site_id' => $header['site_id'] ?? null,
                'sales_invoice_id' => $invoiceId,
                'invoice_no' => $header['invoice_no'],
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'customer_id' => $header['customer_id'] ?? null,
                'customer_code' => $header['customer_code'] ?? null,
                'customer_name' => $header['customer_name'] ?? null,
                'currency_code' => $header['currency_code'] ?? 'IDR',
                'invoice_amount' => $total,
                'paid_amount' => 0,
                'outstanding_amount' => $total,
                'status' => 'open',
            ]);

            $glEntryId = $this->postSalesInvoiceGl($header, $invoiceId, $invoiceDate, $subtotal, $discount, $tax, $total, $userId, 'manual_ar_invoice');
            $invoiceModel->update($invoiceId, ['gl_entry_id' => $glEntryId]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post manual A/R invoice.');
            }
            $db->transCommit();

            (new AuditLogService())->log('finance.ar', 'manual_ar_invoice.post', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'sales_invoices',
                'record_id' => $invoiceId,
                'record_code' => $header['invoice_no'],
                'description' => 'Manual A/R invoice posted, receivable opened, and GL posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'total' => $total, 'gl_entry_id' => $glEntryId],
            ]);

            return $invoiceId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function postFromDelivery(array $header, ?int $userId = null): int
    {
        if (empty($header['company_id']) || empty($header['sales_delivery_id']) || empty($header['invoice_no'])) {
            throw new RuntimeException('Company, delivery, and invoice number are required.');
        }
        (new PeriodCloseService())->assertOpen('ar', (int) $header['company_id'], (string) ($header['invoice_date'] ?? date('Y-m-d')), ! empty($header['site_id']) ? (int) $header['site_id'] : null);

        $deliveryModel = new SalesDeliveryModel();
        $delivery = $deliveryModel->find((int) $header['sales_delivery_id']);
        if ($delivery === null) {
            throw new RuntimeException('Delivery order not found.');
        }
        $deliveryStatus = (string) ($delivery['status'] ?? '');
        if ($deliveryStatus !== 'posted') {
            throw new RuntimeException('Only posted delivery order can be invoiced. Current status: ' . ($deliveryStatus !== '' ? $deliveryStatus : 'unknown') . '.');
        }

        $invoiceModel = new SalesInvoiceModel();
        $existing = $invoiceModel
            ->where('sales_delivery_id', (int) $header['sales_delivery_id'])
            ->where('status !=', 'cancelled')
            ->where('deleted_at', null)
            ->first();
        if ($existing !== null) {
            throw new RuntimeException('Delivery order already has invoice ' . ($existing['invoice_no'] ?? '#'. $existing['id']) . '.');
        }

        $deliveryLines = (new SalesDeliveryLineModel())
            ->where('sales_delivery_id', (int) $header['sales_delivery_id'])
            ->orderBy('line_no', 'ASC')
            ->findAll();
        if ($deliveryLines === []) {
            throw new RuntimeException('Delivery order has no lines.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $invoiceLineModel = new SalesInvoiceLineModel();
            $receivableModel = new ArReceivableModel();
            $soLineModel = new SalesOrderLineModel();

            $lines = [];
            $subtotal = 0.0;
            $discount = 0.0;
            $tax = 0.0;

            foreach ($deliveryLines as $deliveryLine) {
                $soLine = ! empty($deliveryLine['sales_order_line_id'])
                    ? $soLineModel->find((int) $deliveryLine['sales_order_line_id'])
                    : null;
                $qty = (float) ($deliveryLine['qty_delivered'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $unitPrice = (float) ($deliveryLine['unit_price'] ?? 0);
                $orderedQty = $soLine !== null ? (float) ($soLine['qty_ordered'] ?? $soLine['qty'] ?? 0) : $qty;
                $ratio = $orderedQty > 0 ? $qty / $orderedQty : 1.0;
                $lineDiscount = round((float) ($soLine['discount_amount'] ?? 0) * $ratio, 6);
                $lineTax = round((float) ($soLine['tax_amount'] ?? 0) * $ratio, 6);
                $lineSubtotal = round($qty * $unitPrice, 6);
                $lineTotal = round($lineSubtotal - $lineDiscount + $lineTax, 6);

                $subtotal += $lineSubtotal;
                $discount += $lineDiscount;
                $tax += $lineTax;
                $lines[] = [
                    'sales_order_id' => $deliveryLine['sales_order_id'] ?? $delivery['sales_order_id'] ?? null,
                    'sales_order_line_id' => $deliveryLine['sales_order_line_id'] ?? null,
                    'sales_delivery_id' => $delivery['id'],
                    'sales_delivery_line_id' => $deliveryLine['id'],
                    'line_no' => $deliveryLine['line_no'],
                    'item_id' => $deliveryLine['item_id'] ?? null,
                    'item_code' => $deliveryLine['item_code'] ?? null,
                    'item_name' => $deliveryLine['item_name'] ?? null,
                    'qty_invoiced' => $qty,
                    'uom_code' => $deliveryLine['uom_code'] ?? 'PCS',
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ];
            }

            if ($lines === []) {
                throw new RuntimeException('No delivery qty can be invoiced.');
            }

            $total = round($subtotal - $discount + $tax, 6);
            $invoiceDate = (string) ($header['invoice_date'] ?? date('Y-m-d'));
            $dueDate = $header['due_date'] ?? $invoiceDate;

            $invoiceModel->insert($header + [
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'sales_order_id' => $delivery['sales_order_id'] ?? null,
                'sales_delivery_id' => $delivery['id'],
                'so_no' => $delivery['so_no'] ?? null,
                'delivery_no' => $delivery['delivery_no'] ?? null,
                'customer_id' => $delivery['customer_id'] ?? null,
                'customer_code' => $delivery['customer_code'] ?? null,
                'customer_name' => $delivery['customer_name'] ?? null,
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
                throw new RuntimeException('Failed to create sales invoice header.');
            }

            foreach ($lines as $line) {
                $invoiceLineModel->insert($line + ['sales_invoice_id' => $invoiceId]);
            }

            $receivableModel->insert([
                'company_id' => $header['company_id'],
                'site_id' => $header['site_id'] ?? null,
                'sales_invoice_id' => $invoiceId,
                'invoice_no' => $header['invoice_no'],
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'customer_id' => $delivery['customer_id'] ?? null,
                'customer_code' => $delivery['customer_code'] ?? null,
                'customer_name' => $delivery['customer_name'] ?? null,
                'currency_code' => $header['currency_code'] ?? 'IDR',
                'invoice_amount' => $total,
                'paid_amount' => 0,
                'outstanding_amount' => $total,
                'status' => 'open',
            ]);

            $glEntryId = $this->postSalesInvoiceGl($header, $invoiceId, $invoiceDate, $subtotal, $discount, $tax, $total, $userId);
            $invoiceModel->update($invoiceId, ['gl_entry_id' => $glEntryId]);

            $deliveryModel->update((int) $delivery['id'], [
                'status' => 'invoiced',
                'updated_by' => $userId,
            ]);
            $this->refreshSoStatus((int) ($delivery['sales_order_id'] ?? 0), $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post sales invoice.');
            }
            $db->transCommit();

            (new AuditLogService())->log('finance.ar', 'sales_invoice.post', [
                'company_id' => $header['company_id'] ?? null,
                'site_id' => $header['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'sales_invoices',
                'record_id' => $invoiceId,
                'record_code' => $header['invoice_no'],
                'description' => 'Sales invoice posted, AR receivable opened, and GL posted.',
                'new_values' => ['header' => $header, 'lines' => $lines, 'total' => $total, 'gl_entry_id' => $glEntryId],
            ]);

            return $invoiceId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function cancel(int $invoiceId, ?int $userId = null, ?string $reason = null): void
    {
        $db = Database::connect();
        $db->transBegin();

        try {
            $invoiceModel = new SalesInvoiceModel();
            $receivableModel = new ArReceivableModel();
            $invoice = $invoiceModel->find($invoiceId);
            if ($invoice === null) {
                throw new RuntimeException('Sales invoice not found.');
            }
            if ((string) ($invoice['status'] ?? '') === 'cancelled') {
                throw new RuntimeException('Sales invoice has already been cancelled.');
            }
            if ((string) ($invoice['status'] ?? '') !== 'open') {
                throw new RuntimeException('Only open sales invoice can be cancelled.');
            }
            $postedReceipt = (new ArReceiptModel())
                ->where('sales_invoice_id', $invoiceId)
                ->where('status', 'posted')
                ->first();
            if ((float) ($invoice['paid_amount'] ?? 0) > 0 || $postedReceipt !== null) {
                throw new RuntimeException('Sales invoice has a posted receipt. Cancel the receipt first.');
            }

            (new PeriodCloseService())->assertOpen('ar', (int) $invoice['company_id'], (string) ($invoice['invoice_date'] ?? date('Y-m-d')), ! empty($invoice['site_id']) ? (int) $invoice['site_id'] : null);

            $reversalGlEntryId = $this->postGlReversal($invoice, $userId);
            $now = date('Y-m-d H:i:s');
            $invoiceModel->update($invoiceId, [
                'status' => 'cancelled',
                'outstanding_amount' => 0,
                'cancelled_at' => $now,
                'cancelled_by' => $userId,
                'cancel_reason' => $reason,
                'reversal_gl_entry_id' => $reversalGlEntryId,
                'updated_by' => $userId,
            ]);

            $receivable = $receivableModel->where('sales_invoice_id', $invoiceId)->first();
            if ($receivable !== null) {
                $receivableModel->update((int) $receivable['id'], [
                    'outstanding_amount' => 0,
                    'status' => 'cancelled',
                ]);
            }

            if (! empty($invoice['sales_delivery_id'])) {
                (new SalesDeliveryModel())->update((int) $invoice['sales_delivery_id'], [
                    'status' => 'posted',
                    'updated_by' => $userId,
                ]);
                $this->refreshSoStatusAfterCancel((int) ($invoice['sales_order_id'] ?? 0), $userId);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to cancel sales invoice.');
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }

        (new AuditLogService())->log('finance.ar', 'sales_invoice.cancel', [
            'company_id' => $invoice['company_id'] ?? null,
            'site_id' => $invoice['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'sales_invoices',
            'record_id' => $invoiceId,
            'record_code' => $invoice['invoice_no'] ?? null,
            'description' => 'Sales invoice cancelled and reversal GL posted.',
            'old_values' => ['status' => $invoice['status'] ?? null],
            'new_values' => ['status' => 'cancelled', 'reason' => $reason, 'reversal_gl_entry_id' => $reversalGlEntryId],
        ]);
    }

    private function postGlReversal(array $invoice, ?int $userId): ?int
    {
        if (empty($invoice['gl_entry_id'])) {
            return null;
        }

        $glLines = (new GlEntryLineModel())
            ->where('gl_entry_id', (int) $invoice['gl_entry_id'])
            ->orderBy('line_no', 'ASC')
            ->findAll();
        if ($glLines === []) {
            return null;
        }

        $lines = [];
        foreach ($glLines as $line) {
            $lines[] = [
                'account_no' => $line['account_no'],
                'description' => 'Cancel ' . ($invoice['invoice_no'] ?? '') . ' - ' . ($line['description'] ?? ''),
                'debit' => (float) ($line['credit'] ?? 0),
                'credit' => (float) ($line['debit'] ?? 0),
            ];
        }

        return (new GeneralLedgerService())->post([
            'company_id' => (int) $invoice['company_id'],
            'site_id' => $invoice['site_id'] ?? null,
            'journal_no' => 'GL-CNL-' . ($invoice['invoice_no'] ?? $invoice['id']),
            'journal_date' => $invoice['invoice_date'] ?? date('Y-m-d'),
            'source_module' => 'ar',
            'source_type' => 'sales_invoice_cancel',
            'source_id' => $invoice['id'],
            'source_no' => $invoice['invoice_no'] ?? null,
            'description' => 'Cancel sales invoice ' . ($invoice['invoice_no'] ?? ''),
            'currency_code' => $invoice['currency_code'] ?? 'IDR',
        ], $lines, $userId);
    }

    private function normalizeManualLines(array $rawLines): array
    {
        $lines = [];
        $lineNo = 1;

        foreach ($rawLines as $rawLine) {
            $description = trim((string) ($rawLine['item_name'] ?? $rawLine['description'] ?? ''));
            $qty = round((float) ($rawLine['qty'] ?? 0), 4);
            $unitPrice = round((float) ($rawLine['unit_price'] ?? 0), 6);
            if ($description === '' || $qty <= 0 || $unitPrice < 0) {
                continue;
            }

            $lineSubtotal = round($qty * $unitPrice, 6);
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
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'line_subtotal' => $lineSubtotal,
                'line_total' => $lineTotal,
            ];
            $lineNo++;
        }

        return $lines;
    }

    private function postSalesInvoiceGl(array $header, int $invoiceId, string $invoiceDate, float $subtotal, float $discount, float $tax, float $total, ?int $userId, string $sourceType = 'sales_invoice'): int
    {
        $companyId = (int) $header['company_id'];
        $profile = new PostingProfileService();
        $revenueAmount = round($subtotal - $discount, 2);
        $taxAmount = round($tax, 2);
        $totalAmount = round($total, 2);

        $lines = [
            [
                'account_no' => $profile->account($companyId, 'ar', 'receivable', '1200'),
                'description' => 'Accounts Receivable',
                'debit' => $totalAmount,
                'credit' => 0,
            ],
            [
                'account_no' => $profile->account($companyId, 'ar', 'sales_revenue', '4100'),
                'description' => 'Sales Revenue',
                'debit' => 0,
                'credit' => $revenueAmount,
            ],
        ];

        if ($taxAmount > 0) {
            $lines[] = [
                'account_no' => $profile->account($companyId, 'ar', 'output_vat', '2200'),
                'description' => 'Output VAT',
                'debit' => 0,
                'credit' => $taxAmount,
            ];
        }

        return (new GeneralLedgerService())->post([
            'company_id' => $companyId,
            'site_id' => $header['site_id'] ?? null,
            'journal_no' => 'GL-' . $header['invoice_no'],
            'journal_date' => $invoiceDate,
            'source_module' => 'ar',
            'source_type' => $sourceType,
            'source_id' => $invoiceId,
            'source_no' => $header['invoice_no'],
            'description' => ($sourceType === 'manual_ar_invoice' ? 'Manual A/R invoice ' : 'Sales invoice ') . $header['invoice_no'],
            'currency_code' => $header['currency_code'] ?? 'IDR',
        ], $lines, $userId);
    }

    private function refreshSoStatus(int $soId, ?int $userId = null): void
    {
        if ($soId <= 0) {
            return;
        }

        $deliveryRows = (new SalesDeliveryModel())->where('sales_order_id', $soId)->findAll();
        if ($deliveryRows === []) {
            return;
        }

        $allInvoiced = true;
        foreach ($deliveryRows as $delivery) {
            if ((string) ($delivery['status'] ?? '') !== 'invoiced') {
                $allInvoiced = false;
                break;
            }
        }

        if ($allInvoiced) {
            (new SalesOrderModel())->update($soId, [
                'status' => 'invoiced',
                'document_status' => 'invoiced',
                'updated_by' => $userId,
            ]);
        }
    }

    private function refreshSoStatusAfterCancel(int $soId, ?int $userId = null): void
    {
        if ($soId <= 0) {
            return;
        }

        $lines = (new SalesOrderLineModel())->where('sales_order_id', $soId)->findAll();
        if ($lines === []) {
            return;
        }

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
}
