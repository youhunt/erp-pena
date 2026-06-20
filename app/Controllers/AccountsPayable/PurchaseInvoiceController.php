<?php

namespace App\Controllers\AccountsPayable;

use App\Controllers\BaseController;
use App\Models\ApPayableModel;
use App\Models\ItemModel;
use App\Models\PurchaseInvoiceLineModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseReceiptLineModel;
use App\Models\PurchaseReceiptModel;
use App\Models\SupplierModel;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Support\DocumentNumberService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use DateTimeImmutable;
use RuntimeException;

class PurchaseInvoiceController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = new PurchaseInvoiceModel();
        $status = trim((string) $this->request->getGet('status'));
        $search = trim((string) $this->request->getGet('q'));

        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        if ($status !== '') {
            $model->where('status', $status);
        }
        if ($search !== '') {
            $model->groupStart()
                ->like('invoice_no', $search)
                ->orLike('receipt_no', $search)
                ->orLike('supplier_code', $search)
                ->orLike('supplier_name', $search)
                ->groupEnd();
        }

        return view('accounts_payable/purchase_invoices/index', [
            'title' => 'Purchase Invoices',
            'invoices' => $model->orderBy('invoice_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
            'filters' => ['status' => $status, 'q' => $search],
            'statusOptions' => ['open', 'partial', 'paid', 'cancelled'],
        ]);
    }

    public function createFromReceipt(int $receiptId): string
    {
        $tenant = new TenantContext(session());
        $receipt = $this->scopedReceipt($tenant, $receiptId);
        if ($receipt === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $status = (string) ($receipt['status'] ?? '');
        if ($status !== 'posted') {
            return view('errors/html/error_404', ['message' => 'Only posted purchase receipt can be invoiced. Current status: ' . ($status !== '' ? $status : 'unknown') . '.']);
        }

        return view('accounts_payable/purchase_invoices/form', [
            'title' => 'Create Purchase Invoice',
            'receipt' => $receipt,
            'lines' => (new PurchaseReceiptLineModel())->where('purchase_receipt_id', $receiptId)->orderBy('line_no', 'ASC')->findAll(),
            'suggestedInvoiceNo' => $this->previewDocumentNumber('PI'),
        ]);
    }

    public function newManual(): string
    {
        $tenant = new TenantContext(session());

        return view('accounts_payable/purchase_invoices/manual_form', [
            'title' => 'Manual A/P Invoice',
            'suppliers' => $this->suppliers($tenant),
            'items' => $this->items($tenant),
            'suggestedInvoiceNo' => $this->previewDocumentNumber('PI'),
        ]);
    }

    public function storeManual()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (! $this->validate([
            'invoice_no' => 'permit_empty|max_length[60]',
            'invoice_date' => 'required|valid_date[Y-m-d]',
            'due_date' => 'permit_empty|valid_date[Y-m-d]',
            'supplier_id' => 'required|is_natural_no_zero',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $supplier = (new SupplierModel())->where('company_id', $companyId)->find((int) $this->request->getPost('supplier_id'));
        if ($supplier === null) {
            return redirect()->back()->withInput()->with('error', 'Supplier not found for active company.');
        }

        try {
            $invoiceDate = (string) $this->request->getPost('invoice_date');
            $invoiceNo = trim((string) $this->request->getPost('invoice_no'));
            if ($invoiceNo === '') {
                $invoiceNo = $this->issueDocumentNumber('PI', $invoiceDate, $companyId, $tenant->activeSiteId());
            }

            $invoiceId = (new PurchaseInvoiceService())->postManual([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'company' => session('active_company_code'),
                'site' => session('active_site_code'),
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'due_date' => (string) ($this->request->getPost('due_date') ?: $invoiceDate),
                'supplier_id' => $supplier['id'],
                'supplier_code' => $supplier['code'] ?? $supplier['supplier'] ?? null,
                'supplier_name' => $supplier['name'] ?? $supplier['supplierna'] ?? null,
                'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: ($supplier['currency_code'] ?? 'IDR'))),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], $this->manualLines('unit_cost'), auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/ap/purchase-invoices/' . $invoiceId)->with('message', 'Manual A/P invoice posted.');
    }

    public function storeFromReceipt(int $receiptId)
    {
        $tenant = new TenantContext(session());
        $receipt = $this->scopedReceipt($tenant, $receiptId);
        if ($receipt === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $receiptStatus = (string) ($receipt['status'] ?? '');
        if ($receiptStatus !== 'posted') {
            return redirect()->back()->with('error', 'Only posted purchase receipt can be invoiced. Current status: ' . ($receiptStatus !== '' ? $receiptStatus : 'unknown') . '.');
        }
        if (! $this->validate([
            'invoice_no' => 'permit_empty|max_length[60]',
            'invoice_date' => 'required|valid_date[Y-m-d]',
            'due_date' => 'permit_empty|valid_date[Y-m-d]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $invoiceDate = (string) $this->request->getPost('invoice_date');
            $invoiceNo = trim((string) $this->request->getPost('invoice_no'));
            if ($invoiceNo === '') {
                $invoiceNo = $this->issueDocumentNumber('PI', $invoiceDate, (int) $receipt['company_id'], $receipt['site_id'] ?? null);
            }

            $invoiceId = (new PurchaseInvoiceService())->postFromReceipt([
                'company_id' => $receipt['company_id'],
                'site_id' => $receipt['site_id'] ?? null,
                'company' => $receipt['company'] ?? session('active_company_code'),
                'site' => $receipt['site'] ?? session('active_site_code'),
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'due_date' => (string) ($this->request->getPost('due_date') ?: $invoiceDate),
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

    public function cancel(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new PurchaseInvoiceModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        if ($model->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->validate([
            'cancel_reason' => 'permit_empty|max_length[500]',
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            (new PurchaseInvoiceService())->cancel($id, auth()->id(), trim((string) $this->request->getPost('cancel_reason')) ?: null);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/ap/purchase-invoices/' . $id)->with('message', 'Purchase invoice cancelled.');
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

    private function suppliers(TenantContext $tenant): array
    {
        $model = new SupplierModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        }

        return $model->where('is_active', 1)->orderBy('name', 'ASC')->findAll(200);
    }

    private function items(TenantContext $tenant): array
    {
        $model = new ItemModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        }

        return $model->where('is_active', 1)->orderBy('name', 'ASC')->findAll(200);
    }

    private function manualLines(string $amountField): array
    {
        $lines = [];
        $itemIds = (array) $this->request->getPost('line_item_id');
        $itemCodes = (array) $this->request->getPost('line_item_code');
        $itemNames = (array) $this->request->getPost('line_item_name');
        $qtys = (array) $this->request->getPost('line_qty');
        $uoms = (array) $this->request->getPost('line_uom_code');
        $amounts = (array) $this->request->getPost('line_unit_amount');
        $discounts = (array) $this->request->getPost('line_discount_amount');
        $taxes = (array) $this->request->getPost('line_tax_amount');

        foreach ($itemNames as $index => $itemName) {
            $lines[] = [
                'item_id' => $itemIds[$index] ?? null,
                'item_code' => $itemCodes[$index] ?? null,
                'item_name' => $itemName,
                'qty' => $qtys[$index] ?? 0,
                'uom_code' => $uoms[$index] ?? 'PCS',
                $amountField => $amounts[$index] ?? 0,
                'discount_amount' => $discounts[$index] ?? 0,
                'tax_amount' => $taxes[$index] ?? 0,
            ];
        }

        return $lines;
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
}
