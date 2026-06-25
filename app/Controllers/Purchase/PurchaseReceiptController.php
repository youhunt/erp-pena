<?php

namespace App\Controllers\Purchase;

use App\Controllers\BaseController;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseReceiptLineModel;
use App\Models\PurchaseReceiptModel;
use App\Services\Purchase\PurchaseReceiptService;
use App\Services\Support\DocumentNumberService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use DateTimeImmutable;
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
            return view('errors/html/error_404', ['message' => 'Only approved or partially received PO can be received. Current status: ' . $status]);
        }

        $this->healPoLineItemCodes($poId);

        $warehouses = $this->masterRows('warehouses');
        $locations = $this->masterRows('locations');

        $selectedWarehouseId = $this->oldOrDefaultId('warehouse_id', $warehouses);
        $selectedLocationId = $this->oldOrDefaultLocationId($locations, $selectedWarehouseId);

        return view('purchase/receipts/form', [
            'title' => 'Receive Purchase Order',
            'po' => $po,
            'lines' => (new PurchaseOrderLineModel())->where('purchase_order_id', $poId)->where('qty_outstanding >', 0)->orderBy('line_no', 'ASC')->findAll(),
            'warehouses' => $warehouses,
            'locations' => $locations,
            'selectedWarehouseId' => $selectedWarehouseId,
            'selectedLocationId' => $selectedLocationId,
            'suggestedReceiptNo' => $this->previewDocumentNumber('PR'),
        ]);
    }

    public function storeFromPo(int $poId)
    {
        $tenant = new TenantContext(session());
        $po = $this->scopedPo($tenant, $poId);
        if ($po === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $receiveUrl = '/purchase/orders/' . $poId . '/receive';
        if (! $this->validate(['receipt_no' => 'permit_empty|max_length[60]', 'receipt_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->to($receiveUrl)->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $this->healPoLineItemCodes($poId);

        $lines = $this->postedLines();
        if ($lines === []) {
            return redirect()->to($receiveUrl)->withInput()->with('error', 'At least one receipt line qty is required.');
        }

        $warehouseId = $this->nullableInt($this->request->getPost('warehouse_id'));
        $locationId = $this->nullableInt($this->request->getPost('location_id'));
        $receiptDate = (string) $this->request->getPost('receipt_date');
        $receiptNo = trim((string) $this->request->getPost('receipt_no'));

        try {
            $this->assertStorageLocation($tenant, $warehouseId, $locationId);
            if ($receiptNo === '') {
                $receiptNo = $this->issueDocumentNumber('PR', $receiptDate, (int) $po['company_id'], $po['site_id'] ?? null);
            }

            $receiptId = (new PurchaseReceiptService())->post([
                'company_id' => $po['company_id'],
                'site_id' => $po['site_id'] ?? null,
                'company' => $po['company'] ?? session('active_company_code'),
                'site' => $po['site'] ?? session('active_site_code'),
                'receipt_no' => $receiptNo,
                'receipt_date' => $receiptDate,
                'purchase_order_id' => $po['id'],
                'po_no' => $po['po_no'],
                'supplier_id' => $po['supplier_id'] ?? null,
                'supplier_code' => $po['supplier_code'] ?? $po['supplier'] ?? null,
                'supplier_name' => $po['supplier_name'] ?? null,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'notes' => trim((string) $this->request->getPost('notes')),
            ], $lines, auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->to($receiveUrl)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/purchase/receipts/' . $receiptId)->with('message', 'Purchase receipt posted and PO quantities updated.');
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
            'existingInvoice' => $this->existingPurchaseInvoice((int) $receipt['id']),
            'lines' => (new PurchaseReceiptLineModel())->where('purchase_receipt_id', $id)->orderBy('line_no', 'ASC')->findAll(),
            'warehouseLabel' => $this->masterLabel('warehouses', $receipt['warehouse_id'] ?? null),
            'locationLabel' => $this->masterLabel('locations', $receipt['location_id'] ?? null),
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

        return redirect()->to('/purchase/receipts/' . $id)->with('message', 'Purchase receipt reversed and PO quantities recalculated.');
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

    private function existingPurchaseInvoice(int $purchaseReceiptId): ?array
    {
        $db = Database::connect();
        if (! $db->tableExists('purchase_invoices')) {
            return null;
        }

        $builder = $db->table('purchase_invoices')
            ->where('purchase_receipt_id', $purchaseReceiptId)
            ->where('status !=', 'cancelled');
        if ($db->fieldExists('deleted_at', 'purchase_invoices')) {
            $builder->where('deleted_at', null);
        }

        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    private function postedLines(): array
    {
        $lineIds = (array) $this->request->getPost('purchase_order_line_id');
        $qtys = (array) $this->request->getPost('qty_received');
        $batchNos = (array) $this->request->getPost('batch_no');
        $lines = [];
        foreach ($lineIds as $index => $lineId) {
            $qty = $this->toNumber($qtys[$index] ?? 0);
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

    private function healPoLineItemCodes(int $poId): void
    {
        if ($poId < 1) {
            return;
        }

        $db = Database::connect();
        if (! $db->tableExists('purchase_order_lines')) {
            return;
        }

        $lineModel = new PurchaseOrderLineModel();
        $lines = $lineModel->where('purchase_order_id', $poId)->findAll();
        foreach ($lines as $line) {
            $lineId = (int) ($line['id'] ?? 0);
            if ($lineId < 1 || trim((string) ($line['item_code'] ?? '')) !== '') {
                continue;
            }

            $resolved = $this->resolveLineItem($line);
            if (($resolved['item_code'] ?? '') === '') {
                continue;
            }

            $payload = [
                'item_code' => $resolved['item_code'],
            ];
            if (trim((string) ($line['item_name'] ?? '')) === '' && ($resolved['item_name'] ?? '') !== '') {
                $payload['item_name'] = $resolved['item_name'];
            }
            if (trim((string) ($line['uom_code'] ?? '')) === '' && ($resolved['uom_code'] ?? '') !== '') {
                $payload['uom_code'] = $resolved['uom_code'];
            }
            if (empty($line['item_id']) && ! empty($resolved['item_id'])) {
                $payload['item_id'] = $resolved['item_id'];
            }
            $lineModel->update($lineId, $payload);
        }
    }

    private function resolveLineItem(array $line): array
    {
        $itemCode = trim((string) ($line['item_code'] ?? $line['item'] ?? $line['item_no'] ?? $line['itemcode'] ?? ''));
        $itemName = trim((string) ($line['item_name'] ?? $line['description'] ?? ''));
        $uomCode = trim((string) ($line['uom_code'] ?? ''));
        $itemId = (int) ($line['item_id'] ?? 0);

        if ($itemCode !== '') {
            return [
                'item_id' => $itemId ?: null,
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'uom_code' => $uomCode,
            ];
        }

        if ($itemId > 0) {
            $db = Database::connect();
            if ($db->tableExists('items')) {
                $item = $db->table('items')->where('id', $itemId)->get(1)->getRowArray();
                if ($item !== null) {
                    return [
                        'item_id' => $itemId,
                        'item_code' => trim((string) ($item['item_code'] ?? $item['code'] ?? $item['item'] ?? $item['item_no'] ?? '')),
                        'item_name' => trim((string) ($item['item_name'] ?? $item['name'] ?? $itemName)),
                        'uom_code' => trim((string) ($uomCode ?: ($item['stockuom'] ?? $item['uom_code'] ?? 'PCS'))),
                    ];
                }
            }
        }

        return [
            'item_id' => $itemId ?: null,
            'item_code' => '',
            'item_name' => $itemName,
            'uom_code' => $uomCode,
        ];
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

    private function oldOrDefaultId(string $field, array $rows): ?int
    {
        $old = $this->nullableInt(old($field));
        if ($old !== null) {
            return $old;
        }

        return isset($rows[0]['id']) ? (int) $rows[0]['id'] : null;
    }

    private function oldOrDefaultLocationId(array $locations, ?int $warehouseId): ?int
    {
        $old = $this->nullableInt(old('location_id'));
        if ($old !== null && $warehouseId !== null) {
            foreach ($locations as $location) {
                if ((int) ($location['id'] ?? 0) === $old && (int) ($location['warehouse_id'] ?? 0) === $warehouseId) {
                    return $old;
                }
            }
        }
        if ($old !== null && $warehouseId === null) {
            return $old;
        }

        foreach ($locations as $location) {
            if ($warehouseId === null || (int) ($location['warehouse_id'] ?? 0) === $warehouseId) {
                return isset($location['id']) ? (int) $location['id'] : null;
            }
        }

        return null;
    }

    private function assertStorageLocation(TenantContext $tenant, ?int $warehouseId, ?int $locationId): void
    {
        if ($warehouseId === null || $locationId === null) {
            throw new RuntimeException('Warehouse and location are required before posting purchase receipt.');
        }

        $db = Database::connect();
        $warehouse = $db->table('warehouses')->where('id', $warehouseId);
        if ($tenant->activeCompanyId() !== null) {
            $warehouse->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $warehouse->where('site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', 'warehouses')) {
            $warehouse->where('deleted_at', null);
        }
        $warehouseRow = $warehouse->get()->getRowArray();
        if ($warehouseRow === null) {
            throw new RuntimeException('Selected warehouse is not valid for the active company/site.');
        }

        $location = $db->table('locations')->where('id', $locationId);
        if ($tenant->activeCompanyId() !== null) {
            $location->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $location->where('site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', 'locations')) {
            $location->where('deleted_at', null);
        }
        $locationRow = $location->get()->getRowArray();
        if ($locationRow === null) {
            throw new RuntimeException('Selected location is not valid for the active company/site.');
        }
        if ((int) ($locationRow['warehouse_id'] ?? 0) !== $warehouseId) {
            throw new RuntimeException('Selected location does not belong to selected warehouse.');
        }
    }

    private function masterLabel(string $table, mixed $id): string
    {
        $id = (int) $id;
        if ($id < 1) {
            return '-';
        }

        $row = Database::connect()->table($table)->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            return '#' . $id;
        }

        return trim((string) (($row['code'] ?? $id) . ' - ' . ($row['name'] ?? '-')));
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
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
