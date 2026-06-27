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
        $unitCosts = (array) $this->request->getPost('unit_cost');
        $lines = [];
        foreach ($lineIds as $index => $lineId) {
            $qty = $this->toNumber($qtys[$index] ?? 0);
            if ((int) $lineId > 0 && $qty > 0) {
                $unitCost = $this->toNumber($unitCosts[$index] ?? 0);
                if ($unitCost < 0) {
                    throw new RuntimeException('Unit cost cannot be negative.');
                }
                $lines[] = [
                    'purchase_order_line_id' => (int) $lineId,
                    'qty_received' => $qty,
                    'batch_no' => trim((string) ($batchNos[$index] ?? '')),
                    'unit_cost' => $unitCost,
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

            $payload = ['item_code' => $resolved['item_code']];
            if (($resolved['item_name'] ?? '') !== '') {
                $payload['item_name'] = $resolved['item_name'];
            }
            if (($resolved['uom_code'] ?? '') !== '') {
                $payload['uom_code'] = $resolved['uom_code'];
            }
            if (! empty($resolved['item_id'])) {
                $payload['item_id'] = $resolved['item_id'];
            }
            $lineModel->update($lineId, $payload);
        }
    }

    private function resolveLineItem(array $line): array
    {
        $itemCode = trim((string) ($line['item_code'] ?? $line['item'] ?? $line['item_no'] ?? $line['itemcode'] ?? ''));
        $itemName = trim((string) ($line['item_name'] ?? $line['description'] ?? ''));
        $description = trim((string) ($line['description'] ?? ''));
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

        $item = $itemId > 0 ? $this->findItemById($itemId) : null;
        if ($item === null && $itemName !== '') {
            $item = $this->findItemByImportedName($itemName);
        }
        if ($item === null && $description !== '' && $description !== $itemName) {
            $item = $this->findItemByImportedName($description);
        }

        if ($item !== null) {
            return $this->itemPayloadFromMaster($item, $uomCode, $itemName);
        }

        return [
            'item_id' => $itemId ?: null,
            'item_code' => '',
            'item_name' => $itemName,
            'uom_code' => $uomCode,
        ];
    }

    private function findItemById(int $itemId): ?array
    {
        if ($itemId < 1) {
            return null;
        }
        $db = Database::connect();
        if (! $db->tableExists('items')) {
            return null;
        }
        return $db->table('items')->where('id', $itemId)->get(1)->getRowArray() ?: null;
    }

    private function findItemByImportedName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $db = Database::connect();
        if (! $db->tableExists('items')) {
            return null;
        }
        $builder = $db->table('items');
        $builder->groupStart();
        if ($db->fieldExists('item_name', 'items')) {
            $builder->orWhere('item_name', $name);
        }
        if ($db->fieldExists('name', 'items')) {
            $builder->orWhere('name', $name);
        }
        if ($db->fieldExists('item_code', 'items')) {
            $builder->orWhere('item_code', $name);
        }
        if ($db->fieldExists('code', 'items')) {
            $builder->orWhere('code', $name);
        }
        $builder->groupEnd();
        return $builder->get(1)->getRowArray() ?: null;
    }

    private function itemPayloadFromMaster(array $item, string $fallbackUom = '', string $fallbackName = ''): array
    {
        return [
            'item_id' => (int) ($item['id'] ?? 0) ?: null,
            'item_code' => (string) ($item['item_code'] ?? $item['code'] ?? ''),
            'item_name' => (string) ($item['item_name'] ?? $item['name'] ?? $fallbackName),
            'uom_code' => (string) ($item['uom_code'] ?? $item['base_uom'] ?? $fallbackUom),
        ];
    }

    private function assertStorageLocation(TenantContext $tenant, ?int $warehouseId, ?int $locationId): void
    {
        if ($warehouseId === null || $locationId === null) {
            throw new RuntimeException('Warehouse and location are required.');
        }
        $db = Database::connect();
        $warehouse = $db->table('warehouses')->where('id', $warehouseId)->get(1)->getRowArray();
        if ($warehouse === null) {
            throw new RuntimeException('Warehouse not found.');
        }
        $location = $db->table('locations')->where('id', $locationId)->get(1)->getRowArray();
        if ($location === null) {
            throw new RuntimeException('Location not found.');
        }
        if ((int) ($location['warehouse_id'] ?? 0) !== (int) $warehouseId) {
            throw new RuntimeException('Selected location does not belong to selected warehouse.');
        }
        if ($tenant->activeCompanyId() !== null && (int) ($warehouse['company_id'] ?? 0) !== (int) $tenant->activeCompanyId()) {
            throw new RuntimeException('Warehouse belongs to another company.');
        }
        if ($tenant->activeCompanyId() !== null && (int) ($location['company_id'] ?? 0) !== (int) $tenant->activeCompanyId()) {
            throw new RuntimeException('Location belongs to another company.');
        }
    }

    private function masterRows(string $table): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return [];
        }
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
        return $builder->orderBy($db->fieldExists('code', $table) ? 'code' : 'id', 'ASC')->get(500)->getResultArray();
    }

    private function oldOrDefaultId(string $field, array $rows): int
    {
        $old = old($field);
        if ($old !== null && $old !== '') {
            return (int) $old;
        }
        return (int) ($rows[0]['id'] ?? 0);
    }

    private function oldOrDefaultLocationId(array $locations, int $warehouseId): int
    {
        $old = old('location_id');
        if ($old !== null && $old !== '') {
            return (int) $old;
        }
        foreach ($locations as $location) {
            if ((int) ($location['warehouse_id'] ?? 0) === $warehouseId) {
                return (int) ($location['id'] ?? 0);
            }
        }
        return (int) ($locations[0]['id'] ?? 0);
    }

    private function masterLabel(string $table, mixed $id): string
    {
        $id = (int) $id;
        if ($id < 1) {
            return '-';
        }
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return '#' . $id;
        }
        $row = $db->table($table)->where('id', $id)->get(1)->getRowArray();
        if ($row === null) {
            return '#' . $id;
        }
        return trim((string) ($row['code'] ?? $id) . ' - ' . (string) ($row['name'] ?? ''));
    }

    private function previewDocumentNumber(string $transactionCode): string
    {
        try {
            return (new DocumentNumberService())->preview($transactionCode, date('Y-m-d'));
        } catch (RuntimeException) {
            return '';
        }
    }

    private function issueDocumentNumber(string $transactionCode, string $date, int $companyId, mixed $siteId): string
    {
        return (new DocumentNumberService())->next($transactionCode, $date, $companyId, $siteId !== null ? (int) $siteId : null);
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
}
