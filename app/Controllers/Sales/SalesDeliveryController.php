<?php

namespace App\Controllers\Sales;

use App\Controllers\BaseController;
use App\Models\AllocationLineModel;
use App\Models\AllocationOrderModel;
use App\Models\SalesDeliveryLineModel;
use App\Models\SalesDeliveryModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\Sales\AllocationDeliveryService;
use App\Services\Sales\SalesDeliveryService;
use App\Services\Support\DocumentNumberService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;

class SalesDeliveryController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $model = new SalesDeliveryModel();
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
                ->like('delivery_no', $search)
                ->orLike('so_no', $search)
                ->orLike('customer_code', $search)
                ->orLike('customer_name', $search)
                ->groupEnd();
        }

        return view('sales/deliveries/index', [
            'title' => 'Delivery Orders',
            'deliveries' => $model->orderBy('delivery_date', 'DESC')->orderBy('id', 'DESC')->findAll(100),
            'filters' => ['status' => $status, 'q' => $search],
            'statusOptions' => ['posted', 'invoiced', 'reversed'],
        ]);
    }

    public function createFromSo(int $soId): string
    {
        $tenant = new TenantContext(session());
        $so = $this->scopedSo($tenant, $soId);
        if ($so === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $allocationId = $this->nullableInt($this->request->getGet('allocation_id'));
        if ($allocationId !== null) {
            return $this->createFromAllocation($tenant, $so, $allocationId);
        }

        $status = (string) ($so['document_status'] ?? $so['status'] ?? 'draft');
        if (! in_array($status, ['approved', 'reserved', 'partial_delivered'], true)) {
            return view('errors/html/error_404', ['message' => 'Only approved, reserved, or partially delivered SO can be delivered.']);
        }

        $warehouses = $this->masterRows('warehouses');
        $locations = $this->masterRows('locations');
        $warehouseId = $this->nullableInt($this->request->getGet('warehouse_id')) ?? $this->oldOrDefaultId('warehouse_id', $warehouses);
        $locationId = $this->nullableInt($this->request->getGet('location_id')) ?? $this->oldOrDefaultLocationId($locations, $warehouseId);
        $lines = (new SalesOrderLineModel())->where('sales_order_id', $soId)->where('qty_outstanding >', 0)->orderBy('line_no', 'ASC')->findAll();

        return view('sales/deliveries/form', [
            'title' => 'Create Delivery Order',
            'so' => $so,
            'lines' => $lines,
            'warehouses' => $warehouses,
            'locations' => $locations,
            'selectedWarehouseId' => $warehouseId,
            'selectedLocationId' => $locationId,
            'stockByItem' => $this->stockByItemCode($lines, $tenant, $warehouseId, $locationId),
            'suggestedDeliveryNo' => $this->previewDocumentNumber('DO'),
        ]);
    }

    public function storeFromSo(int $soId)
    {
        $tenant = new TenantContext(session());
        $so = $this->scopedSo($tenant, $soId);
        if ($so === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $allocationId = $this->nullableInt($this->request->getPost('allocation_id'));
        if ($allocationId !== null) {
            return $this->storeFromAllocation($tenant, $so, $allocationId);
        }

        $deliveryUrl = '/sales/orders/' . $soId . '/deliver';
        if (! $this->validate(['delivery_no' => 'permit_empty|max_length[60]', 'delivery_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->to($deliveryUrl)->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $lines = $this->postedLines();
        if ($lines === []) {
            return redirect()->to($deliveryUrl)->withInput()->with('error', 'At least one delivery line qty is required.');
        }

        $warehouseId = $this->nullableInt($this->request->getPost('warehouse_id'));
        $locationId = $this->nullableInt($this->request->getPost('location_id'));
        $deliveryDate = (string) $this->request->getPost('delivery_date');
        $deliveryNo = trim((string) $this->request->getPost('delivery_no'));

        try {
            $this->assertStorageLocation($tenant, $warehouseId, $locationId);
            if ($deliveryNo === '') {
                $deliveryNo = $this->issueDocumentNumber('DO', $deliveryDate, (int) $so['company_id'], $so['site_id'] ?? null);
            }

            $deliveryId = (new SalesDeliveryService())->post([
                'company_id' => $so['company_id'],
                'site_id' => $so['site_id'] ?? null,
                'company' => $so['company'] ?? session('active_company_code'),
                'site' => $so['site'] ?? session('active_site_code'),
                'delivery_no' => $deliveryNo,
                'delivery_date' => $deliveryDate,
                'sales_order_id' => $so['id'],
                'so_no' => $so['so_no'],
                'customer_id' => $so['customer_id'] ?? null,
                'customer_code' => $so['customer_code'] ?? $so['customer'] ?? null,
                'customer_name' => $so['customer_name'] ?? null,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'notes' => trim((string) $this->request->getPost('notes')),
            ], $lines, auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->to($deliveryUrl)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/sales/deliveries/' . $deliveryId)->with('message', 'Delivery order posted and SO quantities updated.');
    }

    public function show(int $id): string
    {
        $tenant = new TenantContext(session());
        $model = new SalesDeliveryModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        $delivery = $model->find($id);
        if ($delivery === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('sales/deliveries/show', [
            'title' => 'Delivery Order Detail',
            'delivery' => $delivery,
            'existingInvoice' => $this->existingSalesInvoice((int) $delivery['id']),
            'lines' => (new SalesDeliveryLineModel())->where('sales_delivery_id', $id)->orderBy('line_no', 'ASC')->findAll(),
        ]);
    }

    public function reverse(int $id)
    {
        $tenant = new TenantContext(session());
        $model = new SalesDeliveryModel();
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
            (new SalesDeliveryService())->reverse($id, auth()->id(), $reason);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/sales/deliveries/' . $id)->with('message', 'Delivery order reversed and SO quantities recalculated.');
    }

    private function createFromAllocation(TenantContext $tenant, array $so, int $allocationId): string
    {
        $allocation = $this->scopedAllocation($tenant, (int) $so['id'], $allocationId);
        if ($allocation === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $status = (string) ($allocation['status'] ?? 'posted');
        if (! in_array($status, ['posted', 'partial_delivered'], true)) {
            return view('errors/html/error_404', ['message' => 'Only posted or partially delivered allocation can be delivered. Current status: ' . $status]);
        }

        $lines = $this->allocationDeliverableLines($allocationId);

        return view('sales/deliveries/from_allocation', [
            'title' => 'Create Delivery from Allocation',
            'so' => $so,
            'allocation' => $allocation,
            'lines' => $lines,
            'suggestedDeliveryNo' => $this->previewDocumentNumber('DO'),
        ]);
    }

    private function storeFromAllocation(TenantContext $tenant, array $so, int $allocationId)
    {
        $deliveryUrl = '/sales/orders/' . (int) $so['id'] . '/deliver?allocation_id=' . $allocationId;
        if (! $this->validate(['delivery_no' => 'permit_empty|max_length[60]', 'delivery_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->to($deliveryUrl)->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $allocation = $this->scopedAllocation($tenant, (int) $so['id'], $allocationId);
        if ($allocation === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $lines = $this->postedAllocationLines();
        if ($lines === []) {
            return redirect()->to($deliveryUrl)->withInput()->with('error', 'At least one allocation line delivery qty is required.');
        }

        $deliveryDate = (string) $this->request->getPost('delivery_date');
        $deliveryNo = trim((string) $this->request->getPost('delivery_no'));

        try {
            if ($deliveryNo === '') {
                $deliveryNo = $this->issueDocumentNumber('DO', $deliveryDate, (int) $so['company_id'], $so['site_id'] ?? null);
            }

            $deliveryId = (new AllocationDeliveryService())->postFromAllocation($allocationId, [
                'company_id' => $so['company_id'],
                'site_id' => $so['site_id'] ?? null,
                'company' => $so['company'] ?? session('active_company_code'),
                'site' => $so['site'] ?? session('active_site_code'),
                'delivery_no' => $deliveryNo,
                'delivery_date' => $deliveryDate,
                'sales_order_id' => $so['id'],
                'so_no' => $so['so_no'],
                'customer_id' => $so['customer_id'] ?? null,
                'customer_code' => $so['customer_code'] ?? $so['customer'] ?? null,
                'customer_name' => $so['customer_name'] ?? null,
                'notes' => trim((string) $this->request->getPost('notes')),
            ], $lines, auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->to($deliveryUrl)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/sales/deliveries/' . $deliveryId)->with('message', 'Delivery order posted from allocation.');
    }

    private function scopedSo(TenantContext $tenant, int $soId): ?array
    {
        $model = new SalesOrderModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        return $model->find($soId);
    }

    private function scopedAllocation(TenantContext $tenant, int $soId, int $allocationId): ?array
    {
        $model = new AllocationOrderModel();
        if ($tenant->activeCompanyId() !== null) {
            $model->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $model->where('site_id', $tenant->activeSiteId());
        }
        return $model->where('sales_order_id', $soId)->find($allocationId);
    }

    private function existingSalesInvoice(int $salesDeliveryId): ?array
    {
        $db = Database::connect();
        if (! $db->tableExists('sales_invoices')) {
            return null;
        }

        $builder = $db->table('sales_invoices')
            ->where('sales_delivery_id', $salesDeliveryId)
            ->where('status !=', 'cancelled');
        if ($db->fieldExists('deleted_at', 'sales_invoices')) {
            $builder->where('deleted_at', null);
        }

        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    private function postedLines(): array
    {
        $lineIds = (array) $this->request->getPost('sales_order_line_id');
        $qtys = (array) $this->request->getPost('qty_delivered');
        $batchNos = (array) $this->request->getPost('batch_no');
        $lines = [];
        foreach ($lineIds as $index => $lineId) {
            $qty = $this->toNumber($qtys[$index] ?? 0);
            if ((int) $lineId > 0 && $qty > 0) {
                $lines[] = [
                    'sales_order_line_id' => (int) $lineId,
                    'qty_delivered' => $qty,
                    'batch_no' => trim((string) ($batchNos[$index] ?? '')),
                ];
            }
        }
        return $lines;
    }

    private function postedAllocationLines(): array
    {
        $lineIds = (array) $this->request->getPost('allocationline_id');
        $qtys = (array) $this->request->getPost('qty_delivered');
        $lines = [];
        foreach ($lineIds as $index => $lineId) {
            $qty = $this->toNumber($qtys[$index] ?? 0);
            if ((int) $lineId > 0 && $qty > 0) {
                $lines[] = [
                    'allocationline_id' => (int) $lineId,
                    'qty_delivered' => $qty,
                ];
            }
        }
        return $lines;
    }

    private function allocationDeliverableLines(int $allocationId): array
    {
        $rows = (new AllocationLineModel())
            ->where('allocationorder_id', $allocationId)
            ->where('active', 1)
            ->orderBy('line', 'ASC')
            ->findAll();

        $deliverable = [];
        foreach ($rows as $row) {
            $allocated = (float) ($row['allocateqty'] ?? 0);
            $delivered = (float) ($row['delivered_qty'] ?? 0);
            $remaining = max(0.0, $allocated - $delivered);
            if ($remaining <= 0) {
                continue;
            }
            $row['remaining_qty'] = $remaining;
            $deliverable[] = $row;
        }

        return $deliverable;
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
            if (in_array($table, ['items', 'locations'], true)) {
                $builder->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
            } else {
                $builder->where('site_id', $tenant->activeSiteId());
            }
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
        if ($old !== null) {
            return $old;
        }

        foreach ($locations as $location) {
            if ($warehouseId === null || (int) ($location['warehouse_id'] ?? 0) === $warehouseId) {
                return isset($location['id']) ? (int) $location['id'] : null;
            }
        }

        return null;
    }

    private function stockByItemCode(array $lines, TenantContext $tenant, ?int $warehouseId, ?int $locationId): array
    {
        $codes = [];
        foreach ($lines as $line) {
            $code = trim((string) ($line['item_code'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        $codes = array_values(array_unique($codes));
        if ($codes === []) {
            return [];
        }

        $stock = [];
        foreach ($codes as $code) {
            $stock[$code] = ['on_hand' => 0.0, 'reserved' => 0.0, 'available' => 0.0];
        }

        $db = Database::connect();
        if ($db->tableExists('inventory_stock_balances')) {
            $builder = $db->table('inventory_stock_balances')
                ->select('item_code, SUM(qty_on_hand) qty_on_hand, SUM(qty_reserved) qty_reserved, SUM(qty_available) qty_available')
                ->whereIn('item_code', $codes);

            if ($tenant->activeCompanyId() !== null) {
                $builder->where('company_id', $tenant->activeCompanyId());
            }
            if ($tenant->activeSiteId() !== null) {
                $builder->where('site_id', $tenant->activeSiteId());
            }
            $warehouseId === null ? $builder->where('warehouse_id', null) : $builder->where('warehouse_id', $warehouseId);
            $locationId === null ? $builder->where('location_id', null) : $builder->where('location_id', $locationId);

            foreach ($builder->groupBy('item_code')->get()->getResultArray() as $row) {
                $code = (string) $row['item_code'];
                $stock[$code] = [
                    'on_hand' => (float) ($row['qty_on_hand'] ?? 0),
                    'reserved' => (float) ($row['qty_reserved'] ?? 0),
                    'available' => (float) ($row['qty_available'] ?? 0),
                ];
            }
        }

        if ($db->tableExists('inventory_stock_movements')) {
            $movementBuilder = $db->table('inventory_stock_movements')
                ->select("item_code, SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END) AS movement_qty", false)
                ->whereIn('item_code', $codes);

            if ($tenant->activeCompanyId() !== null) {
                $movementBuilder->where('company_id', $tenant->activeCompanyId());
            }
            if ($tenant->activeSiteId() !== null) {
                $movementBuilder->where('site_id', $tenant->activeSiteId());
            }
            if ($warehouseId !== null) {
                $movementBuilder->where('warehouse_id', $warehouseId);
            }
            if ($locationId !== null) {
                $movementBuilder->where('location_id', $locationId);
            }

            foreach ($movementBuilder->groupBy('item_code')->get()->getResultArray() as $row) {
                $code = (string) $row['item_code'];
                $movementQty = (float) ($row['movement_qty'] ?? 0);
                if (($stock[$code]['available'] ?? 0.0) <= 0 && $movementQty > 0) {
                    $stock[$code] = [
                        'on_hand' => $movementQty,
                        'reserved' => 0.0,
                        'available' => $movementQty,
                    ];
                }
            }
        }

        return $stock;
    }

    private function assertStorageLocation(TenantContext $tenant, ?int $warehouseId, ?int $locationId): void
    {
        if ($warehouseId === null || $locationId === null) {
            throw new RuntimeException('Warehouse and location are required before posting sales delivery.');
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
            $location->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
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
