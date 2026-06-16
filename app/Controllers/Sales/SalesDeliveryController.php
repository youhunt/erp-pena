<?php

namespace App\Controllers\Sales;

use App\Controllers\BaseController;
use App\Models\SalesDeliveryLineModel;
use App\Models\SalesDeliveryModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Services\Sales\SalesDeliveryService;
use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
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

        $status = (string) ($so['document_status'] ?? $so['status'] ?? 'draft');
        if (! in_array($status, ['approved', 'reserved', 'partial_delivered'], true)) {
            return view('errors/html/error_404', ['message' => 'Only approved, reserved, or partially delivered SO can be delivered.']);
        }

        $warehouses = $this->masterRows('warehouses');
        $locations = $this->masterRows('locations');
        $warehouseId = $this->oldOrDefaultId('warehouse_id', $warehouses);
        $locationId = $this->oldOrDefaultId('location_id', $locations);
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
        ]);
    }

    public function storeFromSo(int $soId)
    {
        $tenant = new TenantContext(session());
        $so = $this->scopedSo($tenant, $soId);
        if ($so === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->validate(['delivery_no' => 'required|max_length[60]', 'delivery_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $lines = $this->postedLines();
        if ($lines === []) {
            return redirect()->back()->withInput()->with('error', 'At least one delivery line qty is required.');
        }

        try {
            $deliveryId = (new SalesDeliveryService())->post([
                'company_id' => $so['company_id'],
                'site_id' => $so['site_id'] ?? null,
                'company' => $so['company'] ?? session('active_company_code'),
                'site' => $so['site'] ?? session('active_site_code'),
                'delivery_no' => trim((string) $this->request->getPost('delivery_no')),
                'delivery_date' => (string) $this->request->getPost('delivery_date'),
                'sales_order_id' => $so['id'],
                'so_no' => $so['so_no'],
                'customer_id' => $so['customer_id'] ?? null,
                'customer_code' => $so['customer_code'] ?? $so['customer'] ?? null,
                'customer_name' => $so['customer_name'] ?? null,
                'warehouse_id' => $this->nullableInt($this->request->getPost('warehouse_id')) ?? $this->defaultMasterId('warehouses'),
                'location_id' => $this->nullableInt($this->request->getPost('location_id')) ?? $this->defaultMasterId('locations'),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], $lines, auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/sales/deliveries/' . $deliveryId)->with('message', 'Delivery order posted.');
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

        return redirect()->to('/sales/deliveries/' . $id)->with('message', 'Delivery order reversed.');
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

    private function postedLines(): array
    {
        $lineIds = (array) $this->request->getPost('sales_order_line_id');
        $qtys = (array) $this->request->getPost('qty_delivered');
        $batchNos = (array) $this->request->getPost('batch_no');
        $lines = [];
        foreach ($lineIds as $index => $lineId) {
            $qty = (float) ($qtys[$index] ?? 0);
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

    private function defaultMasterId(string $table): ?int
    {
        $rows = $this->masterRows($table);

        return isset($rows[0]['id']) ? (int) $rows[0]['id'] : null;
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

        $db = Database::connect();
        if (! $db->tableExists('inventory_stock_balances')) {
            return [];
        }

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

        $stock = [];
        foreach ($builder->groupBy('item_code')->get()->getResultArray() as $row) {
            $stock[(string) $row['item_code']] = [
                'on_hand' => (float) ($row['qty_on_hand'] ?? 0),
                'reserved' => (float) ($row['qty_reserved'] ?? 0),
                'available' => (float) ($row['qty_available'] ?? 0),
            ];
        }

        return $stock;
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
}
