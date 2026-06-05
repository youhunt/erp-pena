<?php

namespace App\Controllers\AccountsPayable;

use App\Controllers\BaseController;
use App\Models\ApPayableModel;
use App\Models\PurchaseInvoiceLineModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseReceiptLineModel;
use App\Models\PurchaseReceiptModel;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class PurchaseInvoiceController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = new PurchaseInvoiceModel();

        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }

        return view('accounts_payable/purchase_invoices/index', [
            'title' => 'Purchase Invoices',
            'invoices' => $model->orderBy('invoice_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
        ]);
    }

    public function createFromReceipt(int $receiptId): string
    {
        $tenant = new TenantContext(session());
        $receipt = $this->scopedReceipt($tenant, $receiptId);
        if ($receipt === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ((string) ($receipt['status'] ?? '') === 'invoiced') {
            return view('errors/html/error_404', ['message' => 'Purchase receipt already invoiced.']);
        }

        return view('accounts_payable/purchase_invoices/form', [
            'title' => 'Create Purchase Invoice',
            'receipt' => $receipt,
            'lines' => (new PurchaseReceiptLineModel())->where('purchase_receipt_id', $receiptId)->orderBy('line_no', 'ASC')->findAll(),
        ]);
    }

    public function storeFromReceipt(int $receiptId)
    {
        $tenant = new TenantContext(session());
        $receipt = $this->scopedReceipt($tenant, $receiptId);
        if ($receipt === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (! $this->validate([
            'invoice_no' => 'required|max_length[60]',
            'invoice_date' => 'required|valid_date[Y-m-d]',
            'due_date' => 'permit_empty|valid_date[Y-m-d]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $invoiceId = (new PurchaseInvoiceService())->postFromReceipt([
                'company_id' => $receipt['company_id'],
                'site_id' => $receipt['site_id'] ?? null,
                'company' => $receipt['company'] ?? session('active_company_code'),
                'site' => $receipt['site'] ?? session('active_site_code'),
                'invoice_no' => trim((string) $this->request->getPost('invoice_no')),
                'invoice_date' => (string) $this->request->getPost('invoice_date'),
                'due_date' => (string) ($this->request->getPost('due_date') ?: $this->request->getPost('invoice_date')),
                'purchase_receipt_id' => $receipt['id'],
                'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: 'IDR')),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/ap/purchase-invoices/' . $invoiceId)->with('message', 'Purchase invoice posted.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new PurchaseInvoiceModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        $invoice = $model->find($id);
        if ($invoice === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('accounts_payable/purchase_invoices/show', [
            'title' => 'Purchase Invoice Detail',
            'invoice' => $invoice,
            'lines' => (new PurchaseInvoiceLineModel())->where('purchase_invoice_id', $id)->orderBy('line_no', 'ASC')->findAll(),
            'payable' => (new ApPayableModel())->where('purchase_invoice_id', $id)->first(),
        ]);
    }

    private function scopedReceipt(TenantContext $tenant, int $receiptId): ?array
    {
        $model = new PurchaseReceiptModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        return $model->find($receiptId);
    }
}
