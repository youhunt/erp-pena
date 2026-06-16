<?php

namespace App\Controllers\AccountsReceivable;

use App\Controllers\BaseController;
use App\Models\ArReceivableModel;
use App\Models\ArReceiptModel;
use App\Models\CashBankAccountModel;
use App\Services\Finance\SettlementService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
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

        return view('accounts_receivable/receipts/form', [
            'title' => 'Post A/R Receipt',
            'receivable' => $receivable,
            'cashBankAccounts' => $this->cashBankAccounts($tenant),
        ]);
    }

    public function storeFromInvoice(int $invoiceId)
    {
        $tenant = new TenantContext(session());
        $receivable = $this->receivableFromInvoice($tenant, $invoiceId);
        if ($receivable === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->validate([
            'receipt_no' => 'required|max_length[60]',
            'receipt_date' => 'required|valid_date[Y-m-d]',
            'receipt_amount' => 'required|numeric|greater_than[0]',
            'receipt_method' => 'required|max_length[40]',
            'cash_bank_code' => 'required|max_length[80]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $receiptId = (new SettlementService())->postArReceipt([
                'company_id' => $receivable['company_id'],
                'site_id' => $receivable['site_id'] ?? null,
                'ar_receivable_id' => $receivable['id'],
                'receipt_no' => trim((string) $this->request->getPost('receipt_no')),
                'receipt_date' => (string) $this->request->getPost('receipt_date'),
                'receipt_amount' => (float) $this->request->getPost('receipt_amount'),
                'receipt_method' => trim((string) $this->request->getPost('receipt_method')),
                'cash_bank_code' => trim((string) $this->request->getPost('cash_bank_code')),
                'reference_no' => trim((string) $this->request->getPost('reference_no')),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/ar/receipts/' . $receiptId)->with('message', 'A/R receipt posted.');
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

        return redirect()->to('/ar/receipts/' . $id)->with('message', 'A/R receipt cancelled.');
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
}
