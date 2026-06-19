<?php

namespace App\Controllers\Purchase;

use App\Controllers\BaseController;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Support\DocumentNumberService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;

class PurchaseOrderController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $orders = new PurchaseOrderModel();
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
                ->like('po_no', $search)
                ->orLike('supplier_code', $search)
                ->orLike('supplier_name', $search)
                ->groupEnd();
        }

        return view('purchase/orders/index', [
            'title' => 'Purchase Orders',
            'orders' => $orders->orderBy('po_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
            'filters' => ['status' => $status, 'q' => $search],
            'statusOptions' => ['draft', 'submitted', 'approved', 'partial_received', 'received', 'closed', 'cancelled'],
        ]);
    }

    public function create(): string
    {
        return view('purchase/orders/form', [
            'title' => 'Create Purchase Order',
            'order' => [],
            'lines' => [],
            'isEdit' => false,
            'action' => site_url('purchase/orders'),
            'suppliers' => $this->masterRows('suppliers'),
            'items' => $this->masterRows('items'),
            'suggestedPoNo' => $this->previewDocumentNumber('PO'),
        ]);
    }

    public function store()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (! $this->validate(['po_no' => 'permit_empty|max_length[60]', 'po_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $lines = $this->postedLines();
        if ($lines === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line item is required.');
        }

        try {
            $header = $this->postedHeader($tenant);
            if (trim((string) ($header['po_no'] ?? '')) === '') {
                $header['po_no'] = $this->issueDocumentNumber('PO', (string) $header['po_date'], $companyId, $tenant->activeSiteId());
            }

            $poId = (new PurchaseOrderService())->create($header, $lines, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/purchase/orders/' . $poId)->with('message', 'Purchase order created.');
    }

    public function edit(int $id): string
    {
        $tenant = new TenantContext(session());
        $order = $this->scopedOrder($tenant, $id);
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $status = (string) ($order['document_status'] ?? $order['status'] ?? 'draft');
        $lines = (new PurchaseOrderLineModel())->where('purchase_order_id', $id)->orderBy('line_no', 'ASC')->findAll();
        if (! in_array($status, ['draft', 'submitted', 'approved'], true) || $this->hasReceivedLine($lines)) {
            return view('errors/html/error_404', ['message' => 'PO cannot be edited because it is already received/closed/cancelled or has received quantity.']);
        }

        return view('purchase/orders/form', [
            'title' => 'Edit Purchase Order',
            'order' => $order,
            'lines' => $lines,
            'isEdit' => true,
            'action' => site_url('purchase/orders/' . $id),
            'suppliers' => $this->masterRows('suppliers'),
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

        if (! $this->validate(['po_no' => 'permit_empty|max_length[60]', 'po_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $lines = $this->postedLines();
        if ($lines === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line item is required.');
        }

        try {
            (new PurchaseOrderService())->update($id, $this->postedHeader($tenant, $order), $lines, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/purchase/orders/' . $id)->with('message', 'Purchase order updated.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $order = $this->scopedOrder($tenant, $id);
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('purchase/orders/show', [
            'title' => 'Purchase Order Detail',
            'order' => $order,
            'lines' => (new PurchaseOrderLineModel())->where('purchase_order_id', $id)->orderBy('line_no', 'ASC')->findAll(),
        ]);
    }

    public function submit(int $id)
    {
        return $this->transition($id, 'submit', 'PO submitted.');
    }

    public function approve(int $id)
    {
        return $this->transition($id, 'approve', 'PO approved.');
    }

    public function close(int $id)
    {
        return $this->transition($id, 'close', 'PO closed.');
    }

    public function cancel(int $id)
    {
        $reason = trim((string) $this->request->getPost('cancel_reason'));
        try {
            (new PurchaseOrderService())->cancel($id, $reason, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->to('/purchase/orders/' . $id)->with('message', 'PO cancelled.');
    }

    private function transition(int $id, string $method, string $message)
    {
        try {
            (new PurchaseOrderService())->{$method}($id, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->to('/purchase/orders/' . $id)->with('message', $message);
    }

    private function scopedOrder(TenantContext $tenant, int $id): ?array
    {
        $orders = new PurchaseOrderModel();
        if ($tenant->activeCompanyId() !== null) {
            $orders->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $orders->where('site_id', $tenant->activeSiteId());
        }
        return $orders->find($id);
    }

    private function postedHeader(TenantContext $tenant, array $existing = []): array
    {
        $companyId = $tenant->activeCompanyId();
        $supplierId = (int) ($this->request->getPost('supplier_id') ?: 0);
        $supplier = $supplierId > 0 ? Database::connect()->table('suppliers')->where('id', $supplierId)->get()->getRowArray() : null;
        $supplierCode = $supplier['supplier'] ?? $supplier['code'] ?? $existing['supplier_code'] ?? $existing['supplier'] ?? null;
        $supplierName = $supplier['supplierna'] ?? $supplier['name'] ?? trim((string) $this->request->getPost('supplier_name'));
        $poNo = trim((string) $this->request->getPost('po_no'));
        if ($poNo === '' && isset($existing['po_no'])) {
            $poNo = (string) $existing['po_no'];
        }

        return [
            'company_id' => $companyId,
            'site_id' => $tenant->activeSiteId(),
            'company' => session('active_company_code') ?: ($existing['company'] ?? null),
            'site' => session('active_site_code') ?: ($existing['site'] ?? null),
            'po_no' => $poNo,
            'po_date' => (string) $this->request->getPost('po_date'),
            'delivery_date' => $this->nullableDate($this->request->getPost('delivery_date')),
            'arrive_date' => $this->nullableDate($this->request->getPost('arrive_date')),
            'supplier_id' => $supplierId > 0 ? $supplierId : ($existing['supplier_id'] ?? null),
            'supplier' => $supplierCode,
            'supplier_code' => $supplierCode,
            'supplier_name' => $supplierName,
            'terms_code' => trim((string) ($this->request->getPost('terms_code') ?: ($supplier['terms_code'] ?? $supplier['terms'] ?? $existing['terms_code'] ?? ''))),
            'currency_code' => trim((string) ($this->request->getPost('currency_code') ?: 'IDR')),
            'discount_percent' => (float) $this->request->getPost('discount_percent'),
            'discount_amount' => (float) $this->request->getPost('discount_amount'),
            'freight_amount' => (float) $this->request->getPost('freight_amount'),
            'other_amount' => (float) $this->request->getPost('other_amount'),
            'special_charge_amount' => (float) $this->request->getPost('special_charge_amount'),
            'vat_amount' => (float) $this->request->getPost('vat_amount'),
            'wht_amount' => (float) $this->request->getPost('wht_amount'),
            'status' => $existing['status'] ?? 'draft',
            'document_status' => $existing['document_status'] ?? 'draft',
            'notes' => trim((string) $this->request->getPost('notes')),
            'remarks' => trim((string) $this->request->getPost('remarks')),
        ];
    }

    private function postedLines(): array
    {
        $poLines = (array) $this->request->getPost('po_line');
        $itemCodes = (array) $this->request->getPost('item_code');
        $itemIds = (array) $this->request->getPost('item_id');
        $itemNames = (array) $this->request->getPost('item_name');
        $descriptions = (array) $this->request->getPost('description');
        $qtys = (array) $this->request->getPost('qty');
        $uoms = (array) $this->request->getPost('uom_code');
        $prices = (array) $this->request->getPost('unit_price');
        $lines = [];

        foreach ($itemCodes as $index => $code) {
            $qty = (float) ($qtys[$index] ?? 0);
            $price = (float) ($prices[$index] ?? 0);
            $name = trim((string) ($itemNames[$index] ?? ''));
            $code = trim((string) $code);
            if ($code === '' && $name === '' && $qty <= 0) {
                continue;
            }
            if ($qty <= 0) {
                continue;
            }
            $lines[] = [
                'po_line' => (int) ($poLines[$index] ?? (count($lines) + 1)),
                'item_id' => (int) ($itemIds[$index] ?? 0) > 0 ? (int) $itemIds[$index] : null,
                'item_code' => $code !== '' ? $code : null,
                'item_name' => $name !== '' ? $name : $code,
                'description' => trim((string) ($descriptions[$index] ?? '')),
                'qty' => $qty,
                'uom_code' => trim((string) ($uoms[$index] ?? 'PCS')),
                'unit_price' => $price,
            ];
        }
        return $lines;
    }

    private function hasReceivedLine(array $lines): bool
    {
        foreach ($lines as $line) {
            if ((float) ($line['qty_received'] ?? 0) > 0) {
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

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
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
