<?php

namespace App\Controllers\AccountsReceivable;

use App\Controllers\BaseController;
use App\Models\ArReceivableModel;
use App\Models\ArReceiptModel;
use App\Models\CashBankAccountModel;
use App\Services\Finance\SettlementService;
use App\Services\Support\DocumentNumberService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use DateTimeImmutable;
use RuntimeException;

class ReceiptController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = new ArReceiptModel();
        $this->scope($model, $tenant);

        return view('accounts_receivable/receipts/index', [
            'title' => 'A/R Receipts',
            'receipts' => $model->orderBy('receipt_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function createFromInvoice(int $invoiceId): string
    {
        $tenant = new TenantContext(session());
        $receivable = $this->receivableFromInvoice($tenant, $invoiceId);
        if ($receivable === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $status = (string) ($receivable['status'] ?? '');
        if (! in_array($status, ['open', 'partial'], true)) {
            return view('errors/html/error_404', ['message' => 'Only open or partial A/R invoice can receive payment. Current status: ' . ($status !== '' ? $status : 'unknown') . '.']);
        }
        if ((float) ($receivable['outstanding_amount'] ?? 0) <= 0) {
            return view('errors/html/error_404', ['message' => 'A/R invoice has no outstanding amount to receive.']);
        }

        return view('accounts_receivable/receipts/form', [
            'title' => 'Post A/R Receipt',
            'receivable' => $receivable,
            'cashBankAccounts' => $this->cashBankAccounts($tenant),
            'suggestedReceiptNo' => $this->previewDocumentNumber('ARR'),
        ]);
    }

    public function storeFromInvoice(int $invoiceId)
    {
        $tenant = new TenantContext(session());
        $receivable = $this->receivableFromInvoice($tenant, $invoiceId);
        if ($receivable === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $status = (string) ($receivable['status'] ?? '');
        if (! in_array($status, ['open', 'partial'], true)) {
            return redirect()->back()->with('error', 'Only open or partial A/R invoice can receive payment. Current status: ' . ($status !== '' ? $status : 'unknown') . '.');
        }

        if (! $this->validate([
            'receipt_no' => 'permit_empty|max_length[60]',
            'receipt_date' => 'required|valid_date[Y-m-d]',
            'receipt_amount' => 'required',
            'receipt_method' => 'required|max_length[40]',
            'cash_bank_code' => 'required|max_length[80]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $receiptDate = (string) $this->request->getPost('receipt_date');
            $receiptNo = trim((string) $this->request->getPost('receipt_no'));
            $receiptAmount = $this->toNumber($this->request->getPost('receipt_amount'));
            $receiptMethod = trim((string) $this->request->getPost('receipt_method'));
            $cashBankCode = trim((string) $this->request->getPost('cash_bank_code'));
            if ($receiptNo === '') {
                $receiptNo = $this->issueDocumentNumber('ARR', $receiptDate, (int) $receivable['company_id'], $receivable['site_id'] ?? null);
            }

            $this->ensureCashBankAccount($tenant, $cashBankCode, $receiptMethod);

            $receiptId = (new SettlementService())->postArReceipt([
                'company_id' => $receivable['company_id'],
                'site_id' => $receivable['site_id'] ?? null,
                'ar_receivable_id' => $receivable['id'],
                'receipt_no' => $receiptNo,
                'receipt_date' => $receiptDate,
                'receipt_amount' => $receiptAmount,
                'receipt_method' => $receiptMethod,
                'cash_bank_code' => $cashBankCode,
                'reference_no' => trim((string) $this->request->getPost('reference_no')),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/ar/receipts/' . $receiptId)->with('message', 'A/R receipt posted and invoice balance updated.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new ArReceiptModel();
        $this->scope($model, $tenant);
        $receipt = $model->find($id);
        if ($receipt === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('accounts_receivable/receipts/show', [
            'title' => 'A/R Receipt Detail',
            'receipt' => $receipt,
        ]);
    }

    public function cancel(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new ArReceiptModel();
        $this->scope($model, $tenant);
        if ($model->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->validate([
            'cancel_reason' => 'permit_empty|max_length[500]',
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            (new SettlementService())->cancelArReceipt($id, auth()->id(), trim((string) $this->request->getPost('cancel_reason')) ?: null);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/ar/receipts/' . $id)->with('message', 'A/R receipt cancelled and invoice balance reopened.');
    }

    private function receivableFromInvoice(TenantContext $tenant, int $invoiceId): ?array
    {
        $model = new ArReceivableModel();
        $this->scope($model, $tenant);
        return $model->where('sales_invoice_id', $invoiceId)->first();
    }

    private function scope($model, TenantContext $tenant): void
    {
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
    }

    private function cashBankAccounts(TenantContext $tenant): array
    {
        $model = new CashBankAccountModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->groupStart()
                ->where('site_id', $tenant->activeSiteId())
                ->orWhere('site_id', null)
                ->groupEnd();
        }

        return $model->where('is_active', 1)
            ->orderBy('account_type', 'ASC')
            ->orderBy('cash_bank_code', 'ASC')
            ->findAll();
    }

    private function ensureCashBankAccount(TenantContext $tenant, string $code, string $method): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new RuntimeException('Cash/Bank code is required.');
        }

        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            throw new RuntimeException('Active company is required before creating cash/bank account.');
        }

        $model = new CashBankAccountModel();
        $query = $model->where('company_id', $companyId)->where('cash_bank_code', $code)->where('is_active', 1);
        if ($tenant->activeSiteId() !== null) {
            $query->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        } else {
            $query->where('site_id', null);
        }

        if ($query->first() !== null) {
            return;
        }

        $accountType = strtolower($method) === 'cash' ? 'cash' : 'bank';

        $model->insert([
            'company_id' => $companyId,
            'site_id' => $tenant->activeSiteId(),
            'cash_bank_code' => $code,
            'cash_bank_name' => $code . ' - Auto Created',
            'account_type' => $accountType,
            'currency_code' => 'IDR',
            'gl_account_no' => null,
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => 1,
        ]);
    }

    private function previewDocumentNumber(string $transactionCode): string
    {
        try {
            return (new DocumentNumberService())->preview($transactionCode, new DateTimeImmutable(), [
                'prefix' => $transactionCode,
                'format' => '{PREFIX}/{YYYY}{MM}/{SEQ}',
                'reset_period' => 'monthly',
                'padding' => 5,
            ]);
        } catch (\Throwable) {
            return '';
        }
    }

    private function issueDocumentNumber(string $transactionCode, string $date, int $companyId, mixed $siteId): string
    {
        return (new DocumentNumberService())->next($transactionCode, new DateTimeImmutable($date), [
            'company_id' => $companyId,
            'site_id' => ! empty($siteId) ? (int) $siteId : 0,
            'prefix' => $transactionCode,
            'format' => '{PREFIX}/{YYYY}{MM}/{SEQ}',
            'reset_period' => 'monthly',
            'padding' => 5,
        ]);
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
