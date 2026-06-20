<?php

namespace App\Controllers\AccountsReceivable;

use App\Controllers\BaseController;
use App\Models\ArReceivableModel;
use App\Models\CustomerModel;
use App\Models\ItemModel;
use App\Models\SalesDeliveryLineModel;
use App\Models\SalesDeliveryModel;
use App\Models\SalesInvoiceLineModel;
use App\Models\SalesInvoiceModel;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Support\DocumentNumberService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use DateTimeImmutable;
use RuntimeException;

class SalesInvoiceController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = new SalesInvoiceModel();
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
                ->orLike('delivery_no', $search)
                ->orLike('customer_code', $search)
                ->orLike('customer_name', $search)
                ->groupEnd();
        }

        return view('accounts_receivable/sales_invoices/index', [
            'title' => 'Sales Invoices',
            'invoices' => $model->orderBy('invoice_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
            'filters' => ['status' => $status, 'q' => $search],
            'statusOptions' => ['open', 'partial', 'paid', 'cancelled'],
        ]);
    }

    public function createFromDelivery(int $deliveryId): string
    {
        $tenant = new TenantContext(session());
        $delivery = $this->scopedDelivery($tenant, $deliveryId);
        if ($delivery === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ((string) ($delivery['status'] ?? '') === 'invoiced') {
            return view('errors/html/error_404', ['message' => 'Delivery order already invoiced.']);
        }

        return view('accounts_receivable/sales_invoices/form', [
            'title' => 'Create Sales Invoice',
            'delivery' => $delivery,
            'lines' => (new SalesDeliveryLineModel())->where('sales_delivery_id', $deliveryId)->orderBy('line_no', 'ASC')->findAll(),
            'suggestedInvoiceNo' => $this->previewDocumentNumber('SI'),
        ]);
    }

    public function newManual(): string
    {
        $tenant = new TenantContext(session());

        return view('accounts_receivable/sales_invoices/manual_form', [
            'title' => 'Manual A/R Invoice',
            'customers' => $this->customers($tenant),
            'items' => $this->items($tenant),
            'suggestedInvoiceNo' => $this->previewDocumentNumber('SI'),
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
            'customer_id' => 'required|is_natural_no_zero',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $customer = (new CustomerModel())->where('company_id', $companyId)->find((int) $this->request->getPost('customer_id'));
        if ($customer === null) {
            return redirect()->back()->withInput()->with('error', 'Customer not found for active company.');
        }

        try {
            $invoiceDate = (string) $this->request->getPost('invoice_date');
            $invoiceNo = trim((string) $this->request->getPost('invoice_no'));
            if ($invoiceNo === '') {
                $invoiceNo = $this->issueDocumentNumber('SI', $invoiceDate, $companyId, $tenant->activeSiteId());
            }

            $invoiceId = (new SalesInvoiceService())->postManual([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'company' => session('active_company_code'),
                'site' => session('active_site_code'),
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'due_date' => (string) ($this->request->getPost('due_date') ?: $invoiceDate),
                'customer_id' => $customer['id'],
                'customer_code' => $customer['code'] ?? $customer['customer'] ?? null,
                'customer_name' => $customer['name'] ?? $customer['customern'] ?? null,
                'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: ($customer['currency_code'] ?? 'IDR'))),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], $this->manualLines('unit_price'), auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/ar/sales-invoices/' . $invoiceId)->with('message', 'Manual A/R invoice posted.');
    }

    public function storeFromDelivery(int $deliveryId)
    {
        $tenant = new TenantContext(session());
        $delivery = $this->scopedDelivery($tenant, $deliveryId);
        if ($delivery === null) {
            throw PageNotFoundException::forPageNotFound();
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
                $invoiceNo = $this->issueDocumentNumber('SI', $invoiceDate, (int) $delivery['company_id'], $delivery['site_id'] ?? null);
            }

            $invoiceId = (new SalesInvoiceService())->postFromDelivery([
                'company_id' => $delivery['company_id'],
                'site_id' => $delivery['site_id'] ?? null,
                'company' => $delivery['company'] ?? session('active_company_code'),
                'site' => $delivery['site'] ?? session('active_site_code'),
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'due_date' => (string) ($this->request->getPost('due_date') ?: $invoiceDate),
                'sales_delivery_id' => $delivery['id'],
                'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: 'IDR')),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/ar/sales-invoices/' . $invoiceId)->with('message', 'Sales invoice posted.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new SalesInvoiceModel();
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

        return view('accounts_receivable/sales_invoices/show', [
            'title' => 'Sales Invoice Detail',
            'invoice' => $invoice,
            'lines' => (new SalesInvoiceLineModel())->where('sales_invoice_id', $id)->orderBy('line_no', 'ASC')->findAll(),
            'receivable' => (new ArReceivableModel())->where('sales_invoice_id', $id)->first(),
        ]);
    }

    public function cancel(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new SalesInvoiceModel();
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
            (new SalesInvoiceService())->cancel($id, auth()->id(), trim((string) $this->request->getPost('cancel_reason')) ?: null);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/ar/sales-invoices/' . $id)->with('message', 'Sales invoice cancelled.');
    }

    private function scopedDelivery(TenantContext $tenant, int $deliveryId): ?array
    {
        $model = new SalesDeliveryModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        return $model->find($deliveryId);
    }

    private function customers(TenantContext $tenant): array
    {
        $model = new CustomerModel();
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
