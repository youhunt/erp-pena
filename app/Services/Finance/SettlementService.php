<?php

namespace App\Services\Finance;

use App\Models\ApPayableModel;
use App\Models\ApPaymentModel;
use App\Models\ArReceivableModel;
use App\Models\ArReceiptModel;
use App\Models\CashBankAccountModel;
use App\Models\CashBankEntryModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SalesInvoiceModel;
use App\Services\AuditLogService;
use Config\Database;
use RuntimeException;
use Throwable;

class SettlementService
{
    public function postApPayment(array $data, ?int $userId = null): int
    {
        if (empty($data['company_id']) || empty($data['ap_payable_id']) || empty($data['payment_no'])) {
            throw new RuntimeException('Company, payable, and payment number are required.');
        }

        $payableModel = new ApPayableModel();
        $payable = $payableModel->find((int) $data['ap_payable_id']);
        if ($payable === null || (int) $payable['company_id'] !== (int) $data['company_id']) {
            throw new RuntimeException('A/P payable not found.');
        }
        $this->assertSameSite($payable, $data, 'A/P payable');
        $this->assertUniqueDocumentNo('ap_payments', 'payment_no', (string) $data['payment_no'], (int) $data['company_id'], $data['site_id'] ?? null);

        $amount = round((float) ($data['payment_amount'] ?? 0), 6);
        $outstanding = round((float) ($payable['outstanding_amount'] ?? 0), 6);
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }
        if ($outstanding <= 0) {
            throw new RuntimeException('A/P payable is already fully paid.');
        }
        if ($amount > $outstanding) {
            throw new RuntimeException('Payment amount cannot exceed outstanding amount.');
        }
        if (trim((string) ($data['cash_bank_code'] ?? '')) === '') {
            throw new RuntimeException('Cash/Bank account is required for A/P payment.');
        }
        (new PeriodCloseService())->assertOpen('ap', (int) $data['company_id'], (string) ($data['payment_date'] ?? date('Y-m-d')), ! empty($data['site_id']) ? (int) $data['site_id'] : null);
        (new PeriodCloseService())->assertOpen('cashbank', (int) $data['company_id'], (string) ($data['payment_date'] ?? date('Y-m-d')), ! empty($data['site_id']) ? (int) $data['site_id'] : null);

        $db = Database::connect();
        $db->transBegin();

        try {
            $paymentModel = new ApPaymentModel();
            $paymentModel->insert($data + [
                'purchase_invoice_id' => $payable['purchase_invoice_id'],
                'invoice_no' => $payable['invoice_no'],
                'supplier_id' => $payable['supplier_id'] ?? null,
                'supplier_code' => $payable['supplier_code'] ?? null,
                'supplier_name' => $payable['supplier_name'] ?? null,
                'currency_code' => $payable['currency_code'] ?? 'IDR',
                'payment_amount' => $amount,
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $paymentId = (int) $paymentModel->getInsertID();
            if ($paymentId < 1) {
                throw new RuntimeException('Failed to create A/P payment.');
            }

            $cashBankEntryId = (new CashBankService())->post([
                'company_id' => $data['company_id'],
                'site_id' => $data['site_id'] ?? null,
                'entry_no' => 'CB-' . ($data['payment_no'] ?? date('Ymd-His')),
                'entry_date' => $data['payment_date'] ?? date('Y-m-d'),
                'entry_type' => $this->cashBankEntryType((int) $data['company_id'], (string) $data['cash_bank_code'], 'out'),
                'cash_bank_code' => $data['cash_bank_code'],
                'currency_code' => $payable['currency_code'] ?? 'IDR',
                'amount' => $amount,
                'counter_account_no' => (new PostingProfileService())->account((int) $data['company_id'], 'ap', 'payable', '2100'),
                'reference_no' => $data['reference_no'] ?? $data['payment_no'] ?? null,
                'description' => 'A/P payment ' . ($data['payment_no'] ?? '') . ' for invoice ' . ($payable['invoice_no'] ?? ''),
            ], $userId);
            $cashBankEntry = (new CashBankEntryModel())->find($cashBankEntryId);
            $paymentModel->update($paymentId, [
                'cash_bank_entry_id' => $cashBankEntryId,
                'gl_entry_id' => $cashBankEntry['gl_entry_id'] ?? null,
            ]);

            $balance = $this->recalculatePayable($payableModel, new PurchaseInvoiceModel(), $payable, $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post A/P payment.');
            }
            $db->transCommit();

            (new AuditLogService())->log('finance.ap', 'ap_payment.post', [
                'company_id' => $data['company_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'ap_payments',
                'record_id' => $paymentId,
                'record_code' => $data['payment_no'],
                'description' => 'A/P payment posted, cash/bank updated, and supplier payable settled.',
                'new_values' => ['payment' => $data, 'amount' => $amount, 'outstanding' => $balance['outstanding_amount'], 'cash_bank_entry_id' => $cashBankEntryId, 'gl_entry_id' => $cashBankEntry['gl_entry_id'] ?? null],
            ]);

            return $paymentId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function postArReceipt(array $data, ?int $userId = null): int
    {
        if (empty($data['company_id']) || empty($data['ar_receivable_id']) || empty($data['receipt_no'])) {
            throw new RuntimeException('Company, receivable, and receipt number are required.');
        }

        $receivableModel = new ArReceivableModel();
        $receivable = $receivableModel->find((int) $data['ar_receivable_id']);
        if ($receivable === null || (int) $receivable['company_id'] !== (int) $data['company_id']) {
            throw new RuntimeException('A/R receivable not found.');
        }
        $this->assertSameSite($receivable, $data, 'A/R receivable');
        $this->assertUniqueDocumentNo('ar_receipts', 'receipt_no', (string) $data['receipt_no'], (int) $data['company_id'], $data['site_id'] ?? null);

        $amount = round((float) ($data['receipt_amount'] ?? 0), 6);
        $outstanding = round((float) ($receivable['outstanding_amount'] ?? 0), 6);
        if ($amount <= 0) {
            throw new RuntimeException('Receipt amount must be greater than zero.');
        }
        if ($outstanding <= 0) {
            throw new RuntimeException('A/R receivable is already fully paid.');
        }
        if ($amount > $outstanding) {
            throw new RuntimeException('Receipt amount cannot exceed outstanding amount.');
        }
        if (trim((string) ($data['cash_bank_code'] ?? '')) === '') {
            throw new RuntimeException('Cash/Bank account is required for A/R receipt.');
        }
        (new PeriodCloseService())->assertOpen('ar', (int) $data['company_id'], (string) ($data['receipt_date'] ?? date('Y-m-d')), ! empty($data['site_id']) ? (int) $data['site_id'] : null);
        (new PeriodCloseService())->assertOpen('cashbank', (int) $data['company_id'], (string) ($data['receipt_date'] ?? date('Y-m-d')), ! empty($data['site_id']) ? (int) $data['site_id'] : null);

        $db = Database::connect();
        $db->transBegin();

        try {
            $receiptModel = new ArReceiptModel();
            $receiptModel->insert($data + [
                'sales_invoice_id' => $receivable['sales_invoice_id'],
                'invoice_no' => $receivable['invoice_no'],
                'customer_id' => $receivable['customer_id'] ?? null,
                'customer_code' => $receivable['customer_code'] ?? null,
                'customer_name' => $receivable['customer_name'] ?? null,
                'currency_code' => $receivable['currency_code'] ?? 'IDR',
                'receipt_amount' => $amount,
                'status' => 'posted',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $receiptId = (int) $receiptModel->getInsertID();
            if ($receiptId < 1) {
                throw new RuntimeException('Failed to create A/R receipt.');
            }

            $cashBankEntryId = (new CashBankService())->post([
                'company_id' => $data['company_id'],
                'site_id' => $data['site_id'] ?? null,
                'entry_no' => 'CB-' . ($data['receipt_no'] ?? date('Ymd-His')),
                'entry_date' => $data['receipt_date'] ?? date('Y-m-d'),
                'entry_type' => $this->cashBankEntryType((int) $data['company_id'], (string) $data['cash_bank_code'], 'in'),
                'cash_bank_code' => $data['cash_bank_code'],
                'currency_code' => $receivable['currency_code'] ?? 'IDR',
                'amount' => $amount,
                'counter_account_no' => (new PostingProfileService())->account((int) $data['company_id'], 'ar', 'receivable', '1200'),
                'reference_no' => $data['reference_no'] ?? $data['receipt_no'] ?? null,
                'description' => 'A/R receipt ' . ($data['receipt_no'] ?? '') . ' for invoice ' . ($receivable['invoice_no'] ?? ''),
            ], $userId);
            $cashBankEntry = (new CashBankEntryModel())->find($cashBankEntryId);
            $receiptModel->update($receiptId, [
                'cash_bank_entry_id' => $cashBankEntryId,
                'gl_entry_id' => $cashBankEntry['gl_entry_id'] ?? null,
            ]);

            $balance = $this->recalculateReceivable($receivableModel, new SalesInvoiceModel(), $receivable, $userId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post A/R receipt.');
            }
            $db->transCommit();

            (new AuditLogService())->log('finance.ar', 'ar_receipt.post', [
                'company_id' => $data['company_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'user_id' => $userId,
                'table_name' => 'ar_receipts',
                'record_id' => $receiptId,
                'record_code' => $data['receipt_no'],
                'description' => 'A/R receipt posted, cash/bank updated, and customer receivable settled.',
                'new_values' => ['receipt' => $data, 'amount' => $amount, 'outstanding' => $balance['outstanding_amount'], 'cash_bank_entry_id' => $cashBankEntryId, 'gl_entry_id' => $cashBankEntry['gl_entry_id'] ?? null],
            ]);

            return $receiptId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function cancelApPayment(int $paymentId, ?int $userId = null, ?string $reason = null): void
    {
        $db = Database::connect();
        $db->transBegin();

        try {
            $paymentModel = new ApPaymentModel();
            $payableModel = new ApPayableModel();
            $invoiceModel = new PurchaseInvoiceModel();
            $payment = $paymentModel->find($paymentId);
            if ($payment === null) {
                throw new RuntimeException('A/P payment not found.');
            }
            if ((string) ($payment['status'] ?? 'posted') === 'cancelled') {
                throw new RuntimeException('A/P payment has already been cancelled.');
            }

            $cashBankEntry = ! empty($payment['cash_bank_entry_id'])
                ? (new CashBankEntryModel())->find((int) $payment['cash_bank_entry_id'])
                : null;
            if ($cashBankEntry !== null && (! empty($cashBankEntry['reconciled_at']) || ! empty($cashBankEntry['bank_reconciliation_id']))) {
                throw new RuntimeException('A/P payment cash/bank entry has been reconciled.');
            }

            $paymentDate = (string) ($payment['payment_date'] ?? date('Y-m-d'));
            (new PeriodCloseService())->assertOpen('ap', (int) $payment['company_id'], $paymentDate, ! empty($payment['site_id']) ? (int) $payment['site_id'] : null);
            (new PeriodCloseService())->assertOpen('cashbank', (int) $payment['company_id'], $paymentDate, ! empty($payment['site_id']) ? (int) $payment['site_id'] : null);

            $amount = round((float) ($payment['payment_amount'] ?? 0), 6);
            $reversalCashBankEntryId = (new CashBankService())->post([
                'company_id' => $payment['company_id'],
                'site_id' => $payment['site_id'] ?? null,
                'entry_no' => $this->cancellationEntryNo('CB-CNL-AP-', (string) ($payment['payment_no'] ?? 'APPAY'), $paymentId),
                'entry_date' => $paymentDate,
                'entry_type' => $this->cashBankEntryType((int) $payment['company_id'], (string) $payment['cash_bank_code'], 'in'),
                'cash_bank_code' => $payment['cash_bank_code'],
                'currency_code' => $payment['currency_code'] ?? 'IDR',
                'amount' => $amount,
                'counter_account_no' => (new PostingProfileService())->account((int) $payment['company_id'], 'ap', 'payable', '2100'),
                'reference_no' => $payment['payment_no'] ?? null,
                'description' => 'Cancel A/P payment ' . ($payment['payment_no'] ?? ''),
            ], $userId);
            $reversalCashBankEntry = (new CashBankEntryModel())->find($reversalCashBankEntryId);

            $paymentModel->update($paymentId, [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by' => $userId,
                'cancel_reason' => $reason,
                'reversal_cash_bank_entry_id' => $reversalCashBankEntryId,
                'reversal_gl_entry_id' => $reversalCashBankEntry['gl_entry_id'] ?? null,
                'updated_by' => $userId,
            ]);

            $payable = $payableModel->find((int) ($payment['ap_payable_id'] ?? 0));
            if ($payable !== null) {
                $this->recalculatePayable($payableModel, $invoiceModel, $payable, $userId);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to cancel A/P payment.');
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }

        (new AuditLogService())->log('finance.ap', 'ap_payment.cancel', [
            'company_id' => $payment['company_id'] ?? null,
            'site_id' => $payment['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'ap_payments',
            'record_id' => $paymentId,
            'record_code' => $payment['payment_no'] ?? null,
            'description' => 'A/P payment cancelled and cash/bank reversal posted.',
            'old_values' => ['status' => $payment['status'] ?? 'posted'],
            'new_values' => ['status' => 'cancelled', 'reason' => $reason, 'reversal_cash_bank_entry_id' => $reversalCashBankEntryId],
        ]);
    }

    public function cancelArReceipt(int $receiptId, ?int $userId = null, ?string $reason = null): void
    {
        $db = Database::connect();
        $db->transBegin();

        try {
            $receiptModel = new ArReceiptModel();
            $receivableModel = new ArReceivableModel();
            $invoiceModel = new SalesInvoiceModel();
            $receipt = $receiptModel->find($receiptId);
            if ($receipt === null) {
                throw new RuntimeException('A/R receipt not found.');
            }
            if ((string) ($receipt['status'] ?? 'posted') === 'cancelled') {
                throw new RuntimeException('A/R receipt has already been cancelled.');
            }

            $cashBankEntry = ! empty($receipt['cash_bank_entry_id'])
                ? (new CashBankEntryModel())->find((int) $receipt['cash_bank_entry_id'])
                : null;
            if ($cashBankEntry !== null && (! empty($cashBankEntry['reconciled_at']) || ! empty($cashBankEntry['bank_reconciliation_id']))) {
                throw new RuntimeException('A/R receipt cash/bank entry has been reconciled.');
            }

            $receiptDate = (string) ($receipt['receipt_date'] ?? date('Y-m-d'));
            (new PeriodCloseService())->assertOpen('ar', (int) $receipt['company_id'], $receiptDate, ! empty($receipt['site_id']) ? (int) $receipt['site_id'] : null);
            (new PeriodCloseService())->assertOpen('cashbank', (int) $receipt['company_id'], $receiptDate, ! empty($receipt['site_id']) ? (int) $receipt['site_id'] : null);

            $amount = round((float) ($receipt['receipt_amount'] ?? 0), 6);
            $reversalCashBankEntryId = (new CashBankService())->post([
                'company_id' => $receipt['company_id'],
                'site_id' => $receipt['site_id'] ?? null,
                'entry_no' => $this->cancellationEntryNo('CB-CNL-AR-', (string) ($receipt['receipt_no'] ?? 'ARREC'), $receiptId),
                'entry_date' => $receiptDate,
                'entry_type' => $this->cashBankEntryType((int) $receipt['company_id'], (string) $receipt['cash_bank_code'], 'out'),
                'cash_bank_code' => $receipt['cash_bank_code'],
                'currency_code' => $receipt['currency_code'] ?? 'IDR',
                'amount' => $amount,
                'counter_account_no' => (new PostingProfileService())->account((int) $receipt['company_id'], 'ar', 'receivable', '1200'),
                'reference_no' => $receipt['receipt_no'] ?? null,
                'description' => 'Cancel A/R receipt ' . ($receipt['receipt_no'] ?? ''),
            ], $userId);
            $reversalCashBankEntry = (new CashBankEntryModel())->find($reversalCashBankEntryId);

            $receiptModel->update($receiptId, [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by' => $userId,
                'cancel_reason' => $reason,
                'reversal_cash_bank_entry_id' => $reversalCashBankEntryId,
                'reversal_gl_entry_id' => $reversalCashBankEntry['gl_entry_id'] ?? null,
                'updated_by' => $userId,
            ]);

            $receivable = $receivableModel->find((int) ($receipt['ar_receivable_id'] ?? 0));
            if ($receivable !== null) {
                $this->recalculateReceivable($receivableModel, $invoiceModel, $receivable, $userId);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to cancel A/R receipt.');
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }

        (new AuditLogService())->log('finance.ar', 'ar_receipt.cancel', [
            'company_id' => $receipt['company_id'] ?? null,
            'site_id' => $receipt['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'ar_receipts',
            'record_id' => $receiptId,
            'record_code' => $receipt['receipt_no'] ?? null,
            'description' => 'A/R receipt cancelled and cash/bank reversal posted.',
            'old_values' => ['status' => $receipt['status'] ?? 'posted'],
            'new_values' => ['status' => 'cancelled', 'reason' => $reason, 'reversal_cash_bank_entry_id' => $reversalCashBankEntryId],
        ]);
    }

    private function cashBankEntryType(int $companyId, string $cashBankCode, string $direction): string
    {
        $account = (new CashBankAccountModel())
            ->where('company_id', $companyId)
            ->where('cash_bank_code', $cashBankCode)
            ->where('is_active', 1)
            ->first();

        if ($account === null) {
            throw new RuntimeException('Cash/Bank account not found or inactive.');
        }

        $accountType = (string) ($account['account_type'] ?? 'bank');

        return ($accountType === 'cash' ? 'cash' : 'bank') . '_' . $direction;
    }

    private function cancellationEntryNo(string $prefix, string $documentNo, int $id): string
    {
        $cleanDocumentNo = trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $documentNo), '-');
        $suffix = '-' . $id;
        $maxDocumentLength = max(1, 60 - strlen($prefix) - strlen($suffix));

        return $prefix . substr($cleanDocumentNo !== '' ? $cleanDocumentNo : 'DOC', 0, $maxDocumentLength) . $suffix;
    }

    private function assertSameSite(array $document, array $data, string $label): void
    {
        if (empty($data['site_id']) || empty($document['site_id'])) {
            return;
        }
        if ((int) $data['site_id'] !== (int) $document['site_id']) {
            throw new RuntimeException($label . ' belongs to a different site.');
        }
    }

    private function assertUniqueDocumentNo(string $table, string $field, string $documentNo, int $companyId, mixed $siteId = null): void
    {
        $documentNo = trim($documentNo);
        if ($documentNo === '') {
            throw new RuntimeException('Document number is required.');
        }

        $db = Database::connect();
        $builder = $db->table($table)
            ->where('company_id', $companyId)
            ->where($field, $documentNo);
        if ($siteId !== null && $siteId !== '' && $db->fieldExists('site_id', $table)) {
            $builder->where('site_id', (int) $siteId);
        }
        if ($db->fieldExists('status', $table)) {
            $builder->where('status !=', 'cancelled');
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }

        if ($builder->countAllResults() > 0) {
            throw new RuntimeException('Document number already exists: ' . $documentNo);
        }
    }

    private function recalculatePayable(ApPayableModel $payableModel, PurchaseInvoiceModel $invoiceModel, array $payable, ?int $userId): array
    {
        $paidRow = (new ApPaymentModel())
            ->select('COALESCE(SUM(payment_amount), 0) AS paid_amount', false)
            ->where('ap_payable_id', (int) $payable['id'])
            ->where('status', 'posted')
            ->first() ?? [];

        $invoiceAmount = round((float) ($payable['invoice_amount'] ?? 0), 6);
        $newPaid = min($invoiceAmount, round((float) ($paidRow['paid_amount'] ?? 0), 6));
        $newOutstanding = round(max(0, $invoiceAmount - $newPaid), 6);
        $newStatus = $newOutstanding <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'open');

        $payload = [
            'paid_amount' => $newPaid,
            'outstanding_amount' => $newOutstanding,
            'status' => $newStatus,
        ];
        $payableModel->update((int) $payable['id'], $payload);
        $invoiceModel->update((int) $payable['purchase_invoice_id'], $payload + ['updated_by' => $userId]);

        return $payload;
    }

    private function recalculateReceivable(ArReceivableModel $receivableModel, SalesInvoiceModel $invoiceModel, array $receivable, ?int $userId): array
    {
        $paidRow = (new ArReceiptModel())
            ->select('COALESCE(SUM(receipt_amount), 0) AS paid_amount', false)
            ->where('ar_receivable_id', (int) $receivable['id'])
            ->where('status', 'posted')
            ->first() ?? [];

        $invoiceAmount = round((float) ($receivable['invoice_amount'] ?? 0), 6);
        $newPaid = min($invoiceAmount, round((float) ($paidRow['paid_amount'] ?? 0), 6));
        $newOutstanding = round(max(0, $invoiceAmount - $newPaid), 6);
        $newStatus = $newOutstanding <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'open');

        $payload = [
            'paid_amount' => $newPaid,
            'outstanding_amount' => $newOutstanding,
            'status' => $newStatus,
        ];
        $receivableModel->update((int) $receivable['id'], $payload);
        $invoiceModel->update((int) $receivable['sales_invoice_id'], $payload + ['updated_by' => $userId]);

        return $payload;
    }
}
