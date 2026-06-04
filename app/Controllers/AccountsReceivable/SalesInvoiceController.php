<?php

namespace App\Controllers\AccountsReceivable;

use App\Controllers\BaseController;
use App\Models\ArReceivableModel;
use App\Models\SalesDeliveryLineModel;
use App\Models\SalesDeliveryModel;
use App\Models\SalesInvoiceLineModel;
use App\Models\SalesInvoiceModel;
use App\Services\Sales\SalesInvoiceService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class SalesInvoiceController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = new SalesInvoiceModel();

        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }

        return view('accounts_receivable/sales_invoices/index', [
            'title' => 'Sales Invoices',
            'invoices' => $model->orderBy('invoice_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
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
        ]);
    }

    public function storeFromDelivery(int $deliveryId)
    {
        $tenant = new TenantContext(session());
        $delivery = $this->scopedDelivery($tenant, $deliveryId);
        if ($delivery === null) {
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
            $invoiceId = (new SalesInvoiceService())->postFromDelivery([
                'company_id' => $delivery['company_id'],
                'site_id' => $delivery['site_id'] ?? null,
                'company' => $delivery['company'] ?? session('active_company_code'),
                'site' => $delivery['site'] ?? session('active_site_code'),
                'invoice_no' => trim((string) $this->request->getPost('invoice_no')),
                'invoice_date' => (string) $this->request->getPost('invoice_date'),
                'due_date' => (string) ($this->request->getPost('due_date') ?: $this->request->getPost('invoice_date')),
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
}
