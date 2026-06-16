<?php

namespace App\Controllers\Purchase;

use App\Controllers\BaseController;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseReceiptLineModel;
use App\Models\PurchaseReceiptModel;
use App\Services\Purchase\PurchaseReceiptService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use RuntimeException;

class PurchaseReceiptController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = new PurchaseReceiptModel();
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
                ->like('receipt_no', $search)
                ->orLike('po_no', $search)
                ->orLike('supplier_code', $search)
                ->orLike('supplier_name', $search)
                ->groupEnd();
        }

        return view('purchase/receipts/index', [
            'title' => 'Purchase Receipts',
            'receipts' => $model->orderBy('receipt_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
            'filters' => ['status' => $status, 'q' => $search],
            'statusOptions' => ['posted', 'invoiced', 'reversed'],
        ]);
    }

    public function createFromPo(int $poId): string
    {
        $tenant = new TenantContext(session());
        $po = $this->scopedPo($tenant, $poId);
        if ($po === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $status = (string) ($po['document_status'] ?? $po['status'] ?? 'draft');
        if (! in_array($status, ['approved', 'partial_received'], true)) {
            return view('errors/html/error_404', ['message' => 'Only approved or partially received PO can be received.']);
        }

        return view('purchase/receipts/form', [
            'title' => 'Receive Purchase Order',
            'po' => $po,
            'lines' => (new PurchaseOrderLineModel())->where('purchase_order_id', $poId)->where('qty_outstanding >', 0)->orderBy('line_no', 'ASC')->findAll(),
            'warehouses' => $this->masterRows('warehouses'),
            'locations' => $this->masterRows('locations'),
        ]);
    }

    public function storeFromPo(int $poId)
    {
        $tenant = new TenantContext(session());
        $po = $this->scopedPo($tenant, $poId);
        if ($po === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->validate(['receipt_no' => 'required|max_length[60]', 'receipt_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $lines = $this->postedLines();
        if ($lines === []) {
            return redirect()->back()->withInput()->with('error', 'At least one receipt line qty is required.');
        }

        try {
            $receiptId = (new PurchaseReceiptService())->post([
                'company_id' => $po['company_id'],
                'site_id' => $po['site_id'] ?? null,
                'company' => $po['company'] ?? session('active_company_code'),
                'site' => $po['site'] ?? session('active_site_code'),
                'receipt_no' => trim((string) $this->request->getPost('receipt_no')),
                'receipt_date' => (string) $this->request->getPost('receipt_date'),
                'purchase_order_id' => $po['id'],
                'po_no' => $po['po_no'],
                'supplier_id' => $po['supplier_id'] ?? null,
                'supplier_code' => $po['supplier_code'] ?? $po['supplier'] ?? null,
                'supplier_name' => $po['supplier_name'] ?? null,
                'warehouse_id' => $this->nullableInt($this->request->getPost('warehouse_id')),
                'location_id' => $this->nullableInt($this->request->getPost('location_id')),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], $lines, auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/purchase/receipts/' . $receiptId)->with('message', 'Purchase receipt posted.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new PurchaseReceiptModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        $receipt = $model->find($id);
        if ($receipt === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('purchase/receipts/show', [
            'title' => 'Purchase Receipt Detail',
            'receipt' => $receipt,
            'lines' => (new PurchaseReceiptLineModel())->where('purchase_receipt_id', $id)->orderBy('line_no', 'ASC')->findAll(),
        ]);
    }

    public function reverse(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new PurchaseReceiptModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        if ($model->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            $reason = trim((string) $this->request->getPost('reversal_reason')) ?: null;
            (new PurchaseReceiptService())->reverse($id, auth()->id(), $reason);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/purchase/receipts/' . $id)->with('message', 'Purchase receipt reversed.');
    }

    private function scopedPo(TenantContext $tenant, int $poId): ?array
    {
        $model = new PurchaseOrderModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        return $model->find($poId);
    }

    private function postedLines(): array
    {
        $lineIds = (array) $this->request->getPost('purchase_order_line_id');
        $qtys = (array) $this->request->getPost('qty_received');
        $batchNos = (array) $this->request->getPost('batch_no');
        $lines = [];
        foreach ($lineIds as $index => $lineId) {
            $qty = (float) ($qtys[$index] ?? 0);
            if ((int) $lineId > 0 && $qty > 0) {
                $lines[] = [
                    'purchase_order_line_id' => (int) $lineId,
                    'qty_received' => $qty,
                    'batch_no' => trim((string) ($batchNos[$index] ?? '')),
                ];
            }
        }
        return $lines;
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

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
}
