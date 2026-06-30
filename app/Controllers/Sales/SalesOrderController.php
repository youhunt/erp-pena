<?php

namespace App\Controllers\Sales;

use App\Controllers\BaseController;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\Sales\SalesOrderActivationService;
use App\Services\Sales\SalesOrderService;
use App\Services\Support\DocumentNumberService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;

class SalesOrderController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $orders = new SalesOrderModel();
        $status = trim((string) $this->request->getGet('status'));
        $search = trim((string) $this->request->getGet('q'));
        if ($tenant->activeCompanyId() !== null) {
            $orders->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $orders->where('site_id', $tenant->activeSiteId());
        }
        if ($status !== '') {
            $orders->where('document_status', $status);
        }
        if ($search !== '') {
            $orders->groupStart()
                ->like('so_no', $search)
                ->orLike('customer_code', $search)
                ->orLike('customer_name', $search)
                ->groupEnd();
        }
        return view('sales/orders/index', [
            'title' => 'Sales Orders',
            'orders' => $orders->orderBy('so_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
            'filters' => ['status' => $status, 'q' => $search],
            'statusOptions' => ['draft', 'submitted', 'approved', 'reserved', 'partial_delivered', 'delivered', 'invoiced', 'cancelled'],
        ]);
    }

    public function create(): string
    {
        return view('sales/orders/form', [
            'title' => 'Create Sales Order',
            'order' => [],
            'lines' => [],
            'isEdit' => false,
            'action' => site_url('sales/orders'),
            'customers' => $this->masterRows('customers'),
            'items' => $this->masterRows('items'),
            'suggestedSoNo' => $this->previewDocumentNumber('SO'),
        ]);
    }

    public function store()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }
        if (! $this->validate(['so_no' => 'permit_empty|max_length[60]', 'so_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $lines = $this->postedLines();
        if ($lines === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line item is required.');
        }

        try {
            $header = $this->postedHeader($tenant);
            if (trim((string) ($header['so_no'] ?? '')) === '') {
                $header['so_no'] = $this->issueDocumentNumber('SO', (string) $header['so_date'], $companyId, $tenant->activeSiteId());
            }

            $soId = (new SalesOrderService())->create($header, $lines, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
        return redirect()->to('/sales/orders/' . $soId)->with('message', 'Sales order created.');
    }

    public function edit(int $id): string
    {
        $tenant = new TenantContext(session());
        $order = $this->scopedOrder($tenant, $id);
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $status = (string) ($order['document_status'] ?? $order['status'] ?? 'draft');
        $lines = (new SalesOrderLineModel())->where('sales_order_id', $id)->orderBy('line_no', 'ASC')->findAll();
        if ($status !== 'draft' || $this->hasProcessedLine($lines)) {
            return view('errors/html/error_404', ['message' => 'Only draft sales order without reserved/delivered quantity can be edited. Current status: ' . $status . '.']);
        }

        return view('sales/orders/form', [
            'title' => 'Edit Sales Order',
            'order' => $order,
            'lines' => $lines,
            'isEdit' => true,
            'action' => site_url('sales/orders/' . $id),
            'customers' => $this->masterRows('customers'),
            'items' => $this->masterRows('items'),
        ]);
    }

    public function update(int $id)
    {
        $tenant = new TenantContext(session());
        $order = $this->scopedOrder($tenant, $id);
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (! $this->validate(['so_no' => 'permit_empty|max_length[60]', 'so_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $lines = $this->postedLines();
        if ($lines === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line item is required.');
        }

        try {
            (new SalesOrderService())->update($id, $this->postedHeader($tenant, $order), $lines, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/sales/orders/' . $id)->with('message', 'Sales order updated.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $order = $this->scopedOrder($tenant, $id);
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        return view('sales/orders/show', [
            'title' => 'Sales Order Detail',
            'order' => $order,
            'lines' => (new SalesOrderLineModel())->where('sales_order_id', $id)->orderBy('line_no', 'ASC')->findAll(),
            'relatedGlEntries' => $this->relatedGlEntries($id),
        ]);
    }

    public function submit(int $id)
    {
        return $this->transition($id, 'submit', 'SO submitted.');
    }

    public function approve(int $id)
    {
        return $this->transition($id, 'approve', 'SO approved.');
    }

    public function reserve(int $id)
    {
        return $this->transition($id, 'reserve', 'SO stock reserved.');
    }

    public function cancel(int $id)
    {
        try {
            $action = (string) $this->request->getPost('action');
            if ($action === 'reopen') {
                (new SalesOrderService())->reopen($id, auth()->id());
                return redirect()->to('/sales/orders/' . $id)->with('message', 'SO reopened as draft.');
            }
            if (in_array($action, ['activate', 'back_to_draft'], true)) {
                (new SalesOrderActivationService())->activate($id, auth()->id());
                return redirect()->to('/sales/orders/' . $id)->with('message', 'SO returned to draft.');
            }

            $reason = trim((string) $this->request->getPost('cancel_reason'));
            (new SalesOrderService())->cancel($id, $reason, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->to('/sales/orders/' . $id)->with('message', 'SO cancelled.');
    }

    private function transition(int $id, string $method, string $message)
    {
        try {
            (new SalesOrderService())->{$method}($id, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->to('/sales/orders/' . $id)->with('message', $message);
    }

    private function scopedOrder(TenantContext $tenant, int $id): ?array
    {
        $orders = new SalesOrderModel();
        if ($tenant->activeCompanyId() !== null) {
            $orders->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $orders->where('site_id', $tenant->activeSiteId());
        }
        return $orders->find($id);
    }

    private function relatedGlEntries(int $salesOrderId): array
    {
        $db = Database::connect();
        $entries = [];

        $deliveries = $this->documentRows('sales_deliveries', ['sales_order_id' => $salesOrderId]);
        $this->appendGlRows($entries, 'Delivery / COGS', $deliveries, 'delivery_no', 'delivery_date', 'sales/deliveries');

        $invoices = $this->documentRows('sales_invoices', ['sales_order_id' => $salesOrderId]);
        $this->appendGlRows($entries, 'Sales Invoice', $invoices, 'invoice_no', 'invoice_date', 'ar/sales-invoices');

        $invoiceIds = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $invoices)));
        if ($invoiceIds !== [] && $db->tableExists('ar_receipts')) {
            $receipts = $db->table('ar_receipts')->whereIn('sales_invoice_id', $invoiceIds);
            if ($db->fieldExists('deleted_at', 'ar_receipts')) {
                $receipts->where('deleted_at', null);
            }
            $this->appendGlRows($entries, 'A/R Receipt', $receipts->get()->getResultArray(), 'receipt_no', 'receipt_date', 'ar/receipts');
        }

        usort($entries, static fn (array $a, array $b): int => strcmp((string) ($b['journal_date'] ?? ''), (string) ($a['journal_date'] ?? '')));

        return $entries;
    }

    private function documentRows(string $table, array $where): array
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return [];
        }

        $builder = $db->table($table)->where($where);
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }

        return $builder->get()->getResultArray();
    }

    private function appendGlRows(array &$entries, string $module, array $documents, string $docNoField, string $dateField, string $routePrefix): void
    {
        foreach ($documents as $document) {
            $this->appendGlRow($entries, $module, $document, $docNoField, $dateField, $routePrefix, 'gl_entry_id', 'posting');
            $this->appendGlRow($entries, $module, $document, $docNoField, $dateField, $routePrefix, 'reversal_gl_entry_id', 'reversal');
        }
    }

    private function appendGlRow(array &$entries, string $module, array $document, string $docNoField, string $dateField, string $routePrefix, string $glField, string $role): void
    {
        $glEntryId = (int) ($document[$glField] ?? 0);
        if ($glEntryId < 1) {
            return;
        }

        $gl = Database::connect()->table('gl_entries')->where('id', $glEntryId)->get(1)->getRowArray();
        if ($gl === null) {
            return;
        }

        $entries[] = [
            'module' => $module,
            'role' => $role,
            'document_no' => $document[$docNoField] ?? ('#' . ($document['id'] ?? '')),
            'document_date' => $document[$dateField] ?? '-',
            'document_url' => site_url($routePrefix . '/' . (int) ($document['id'] ?? 0)),
            'gl_entry_id' => $glEntryId,
            'journal_no' => $gl['journal_no'] ?? ('#' . $glEntryId),
            'journal_date' => $gl['journal_date'] ?? '-',
            'description' => $gl['description'] ?? '',
            'status' => $gl['status'] ?? '-',
            'gl_url' => site_url('gl/entries/' . $glEntryId),
        ];
    }

    private function postedHeader(TenantContext $tenant, array $existing = []): array
    {
        $companyId = $tenant->activeCompanyId();
        $customerId = (int) ($this->request->getPost('customer_id') ?: 0);
        $customer = $customerId > 0 ? Database::connect()->table('customers')->where('id', $customerId)->get()->getRowArray() : null;
        $customerCode = $customer['customer_code'] ?? $customer['customer'] ?? $customer['code'] ?? $existing['customer_code'] ?? $existing['customer'] ?? null;
        $customerName = $customer['customer_name'] ?? $customer['customern'] ?? $customer['name'] ?? trim((string) $this->request->getPost('customer_name'));
        $soNo = trim((string) $this->request->getPost('so_no'));
        if ($soNo === '' && isset($existing['so_no'])) {
            $soNo = (string) $existing['so_no'];
        }

        return [
            'company_id' => $companyId,
            'site_id' => $tenant->activeSiteId(),
            'company' => session('active_company_code') ?: ($existing['company'] ?? null),
            'site' => session('active_site_code') ?: ($existing['site'] ?? null),
            'so_no' => $soNo,
            'so_date' => (string) $this->request->getPost('so_date'),
            'customer_id' => $customerId > 0 ? $customerId : ($existing['customer_id'] ?? null),
            'customer' => $customerCode,
            'customer_code' => $customerCode,
            'customer_name' => $customerName,
            'terms_code' => trim((string) ($this->request->getPost('terms_code') ?: ($customer['terms_code'] ?? $customer['terms'] ?? $existing['terms_code'] ?? ''))),
            'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: 'IDR')),
            'discount_percent' => (float) $this->request->getPost('discount_percent'),
            'discount_amount' => (float) $this->request->getPost('discount_amount'),
            'freight_amount' => (float) $this->request->getPost('freight_amount'),
            'other_amount' => (float) $this->request->getPost('other_amount'),
            'status' => $existing['status'] ?? 'draft',
            'document_status' => $existing['document_status'] ?? 'draft',
            'notes' => trim((string) $this->request->getPost('notes')),
            'remarks' => trim((string) $this->request->getPost('remarks')),
        ];
    }

    private function postedLines(): array
    {
        $soLines = (array) $this->request->getPost('so_line');
        $itemCodes = (array) $this->request->getPost('item_code');
        $itemOriginalCodes = (array) $this->request->getPost('item_code_original');
        $itemIds = (array) $this->request->getPost('item_id');
        $itemNames = (array) $this->request->getPost('item_name');
        $descriptions = (array) $this->request->getPost('description');
        $qtys = (array) $this->request->getPost('qty');
        $uoms = (array) $this->request->getPost('uom_code');
        $prices = (array) $this->request->getPost('unit_price');
        $discountPercents = (array) $this->request->getPost('discount_percent');
        $discountAmounts = (array) $this->request->getPost('discount_amount');
        $freightAmounts = (array) $this->request->getPost('freight_amount_line');
        $specialChargeAmounts = (array) $this->request->getPost('special_charge_amount');
        $otherAmounts = (array) $this->request->getPost('other_amount_line');
        $taxes = (array) $this->request->getPost('tax_amount');
        $lines = [];
        foreach ($itemCodes as $index => $code) {
            $qty = (float) ($qtys[$index] ?? 0);
            $price = (float) ($prices[$index] ?? 0);
            $name = trim((string) ($itemNames[$index] ?? ''));
            $code = trim((string) $code);
            $originalCode = trim((string) ($itemOriginalCodes[$index] ?? ''));
            if ($code === '' && $originalCode !== '') {
                $code = $originalCode;
            }
            if ($code === '' && $name === '' && $qty <= 0) {
                continue;
            }
            if ($qty <= 0) {
                continue;
            }
            $lines[] = [
                'so_line' => (int) ($soLines[$index] ?? (count($lines) + 1)),
                'item_id' => (int) ($itemIds[$index] ?? 0) > 0 ? (int) $itemIds[$index] : null,
                'item_code' => $code !== '' ? $code : null,
                'item_name' => $name !== '' ? $name : $code,
                'description' => trim((string) ($descriptions[$index] ?? '')),
                'qty' => $qty,
                'uom_code' => trim((string) ($uoms[$index] ?? 'PCS')),
                'unit_price' => $price,
                'discount_percent' => (float) ($discountPercents[$index] ?? 0),
                'discount_amount' => (float) ($discountAmounts[$index] ?? 0),
                'freight_amount' => (float) ($freightAmounts[$index] ?? 0),
                'special_charge_amount' => (float) ($specialChargeAmounts[$index] ?? 0),
                'other_amount' => (float) ($otherAmounts[$index] ?? 0),
                'tax_amount' => (float) ($taxes[$index] ?? 0),
            ];
        }
        return $lines;
    }

    private function hasProcessedLine(array $lines): bool
    {
        foreach ($lines as $line) {
            if ((float) ($line['qty_reserved'] ?? 0) > 0 || (float) ($line['qty_delivered'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }

    private function masterRows(string $table): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table($table);
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        if ($db->fieldExists('is_active', $table)) {
            $builder->where('is_active', 1);
        }
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $table)) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        return $builder->orderBy($db->fieldExists('code', $table) ? 'code' : 'id', 'ASC')->get()->getResultArray();
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

    private function issueDocumentNumber(string $transactionCode, string $date, int $companyId, ?int $siteId): string
    {
        return (new DocumentNumberService())->next($transactionCode, new DateTimeImmutable($date), [
            'company_id' => $companyId,
            'site_id' => $siteId ?? 0,
            'prefix' => $transactionCode,
            'format' => '{PREFIX}/{YYYY}{MM}/{SEQ}',
            'reset_period' => 'monthly',
            'padding' => 5,
        ]);
    }
}
