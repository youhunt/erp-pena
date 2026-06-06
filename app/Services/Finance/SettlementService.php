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

        $amount = round((float) ($data['payment_amount'] ?? 0), 6);
        $outstanding = round((float) ($payable['outstanding_amount'] ?? 0), 6);
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }
        if ($amount > $outstanding) {
            throw new RuntimeException('Payment amount cannot exceed outstanding amount.');
        }
        if (trim((string) ($data['cash_bank_code'] ?? '')) === '') {
            throw new RuntimeException('Cash/Bank account is required for A/P payment.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $paymentModel = new ApPaymentModel();
            $paymentId = (int) $paymentModel->insert($data + [
                'purchase_invoice_id' => $payable['purchase_invoice_id'],
                'invoice_no' => $payable['invoice_no'],
                'supplier_id' => $payable['supplier_id'] ?? null,
                'supplier_code' => $payable['supplier_code'] ?? null,
                'supplier_name' => $payable['supplier_name'] ?? null,
                'currency_code' => $payable['currency_code'] ?? 'IDR',
                'payment_amount' => $amount,
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ], true);

            $cashBankEntryId = (new CashBankService())->post([
                'company_id' => $data['company_id'],
                'site_id' => $data['site_id'] ?? null,
                'entry_no' => 'CB-' . ($data['payment_no'] ?? date('Ymd-His')),
                'entry_date' => $data['payment_date'] ?? date('Y-m-d'),
                'entry_type' => $this->cashBankEntryType((int) $data['company_id'], (string) $data['cash_bank_code'], 'out'),
                'cash_bank_code' => $data['cash_bank_code'],
                'currency_code' => $payable['currency_code'] ?? 'IDR',
                'amount' => $amount,
                'counter_account_no' => '2100',
                'reference_no' => $data['reference_no'] ?? $data['payment_no'] ?? null,
                'description' => 'A/P payment ' . ($data['payment_no'] ?? '') . ' for invoice ' . ($payable['invoice_no'] ?? ''),
            ], $userId);
            $cashBankEntry = (new CashBankEntryModel())->find($cashBankEntryId);
            $paymentModel->update($paymentId, [
                'cash_bank_entry_id' => $cashBankEntryId,
                'gl_entry_id' => $cashBankEntry['gl_entry_id'] ?? null,
            ]);

            $newPaid = round((float) ($payable['paid_amount'] ?? 0) + $amount, 6);
            $newOutstanding = round(max(0, $outstanding - $amount), 6);
            $newStatus = $newOutstanding <= 0 ? 'paid' : 'partial';

            $payableModel->update((int) $payable['id'], [
                'paid_amount' => $newPaid,
                'outstanding_amount' => $newOutstanding,
                'status' => $newStatus,
            ]);
            (new PurchaseInvoiceModel())->update((int) $payable['purchase_invoice_id'], [
                'paid_amount' => $newPaid,
                'outstanding_amount' => $newOutstanding,
                'status' => $newStatus,
                'updated_by' => $userId,
            ]);

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
                'new_values' => ['payment' => $data, 'amount' => $amount, 'outstanding' => $newOutstanding, 'cash_bank_entry_id' => $cashBankEntryId, 'gl_entry_id' => $cashBankEntry['gl_entry_id'] ?? null],
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

        $amount = round((float) ($data['receipt_amount'] ?? 0), 6);
        $outstanding = round((float) ($receivable['outstanding_amount'] ?? 0), 6);
        if ($amount <= 0) {
            throw new RuntimeException('Receipt amount must be greater than zero.');
        }
        if ($amount > $outstanding) {
            throw new RuntimeException('Receipt amount cannot exceed outstanding amount.');
        }
        if (trim((string) ($data['cash_bank_code'] ?? '')) === '') {
            throw new RuntimeException('Cash/Bank account is required for A/R receipt.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $receiptModel = new ArReceiptModel();
            $receiptId = (int) $receiptModel->insert($data + [
                'sales_invoice_id' => $receivable['sales_invoice_id'],
                'invoice_no' => $receivable['invoice_no'],
                'customer_id' => $receivable['customer_id'] ?? null,
                'customer_code' => $receivable['customer_code'] ?? null,
                'customer_name' => $receivable['customer_name'] ?? null,
                'currency_code' => $receivable['currency_code'] ?? 'IDR',
                'receipt_amount' => $amount,
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ], true);

            $cashBankEntryId = (new CashBankService())->post([
                'company_id' => $data['company_id'],
                'site_id' => $data['site_id'] ?? null,
                'entry_no' => 'CB-' . ($data['receipt_no'] ?? date('Ymd-His')),
                'entry_date' => $data['receipt_date'] ?? date('Y-m-d'),
                'entry_type' => $this->cashBankEntryType((int) $data['company_id'], (string) $data['cash_bank_code'], 'in'),
                'cash_bank_code' => $data['cash_bank_code'],
                'currency_code' => $receivable['currency_code'] ?? 'IDR',
                'amount' => $amount,
                'counter_account_no' => '1200',
                'reference_no' => $data['reference_no'] ?? $data['receipt_no'] ?? null,
                'description' => 'A/R receipt ' . ($data['receipt_no'] ?? '') . ' for invoice ' . ($receivable['invoice_no'] ?? ''),
            ], $userId);
            $cashBankEntry = (new CashBankEntryModel())->find($cashBankEntryId);
            $receiptModel->update($receiptId, [
                'cash_bank_entry_id' => $cashBankEntryId,
                'gl_entry_id' => $cashBankEntry['gl_entry_id'] ?? null,
            ]);

            $newPaid = round((float) ($receivable['paid_amount'] ?? 0) + $amount, 6);
            $newOutstanding = round(max(0, $outstanding - $amount), 6);
            $newStatus = $newOutstanding <= 0 ? 'paid' : 'partial';

            $receivableModel->update((int) $receivable['id'], [
                'paid_amount' => $newPaid,
                'outstanding_amount' => $newOutstanding,
                'status' => $newStatus,
            ]);
            (new SalesInvoiceModel())->update((int) $receivable['sales_invoice_id'], [
                'paid_amount' => $newPaid,
                'outstanding_amount' => $newOutstanding,
                'status' => $newStatus,
                'updated_by' => $userId,
            ]);

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
                'new_values' => ['receipt' => $data, 'amount' => $amount, 'outstanding' => $newOutstanding, 'cash_bank_entry_id' => $cashBankEntryId, 'gl_entry_id' => $cashBankEntry['gl_entry_id'] ?? null],
            ]);

            return $receiptId;
        } catch (Throwable $e) {
            $db->transRollback();
            throw new RuntimeException($e->getMessage());
        }
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
}
