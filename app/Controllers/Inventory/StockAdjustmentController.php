<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\Inventory\InventoryStockService;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;

class StockAdjustmentController extends BaseController
{
    public function create(): string
    {
        $contextItemCodes = $this->contextItemCodes();

        return view('inventory/stock_adjustments/form', [
            'title' => $contextItemCodes !== [] ? 'SO Stock Adjustment' : 'Stock Adjustment',
            'items' => $this->masterRows('items', $contextItemCodes),
            'warehouses' => $this->masterRows('warehouses'),
            'locations' => $this->masterRows('locations'),
            'recentMovements' => $this->recentMovements($contextItemCodes),
            'contextItemCodes' => $contextItemCodes,
            'contextQtyByCode' => $this->contextQtyByCode(),
            'sourceSoId' => $this->nullableInt($this->request->getGet('source_so_id')),
            'sourceSoNo' => trim((string) $this->request->getGet('source_so_no')),
        ]);
    }

    public function store()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();

        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        if (is_array($this->request->getPost('item_code'))) {
            return $this->storeBulkSoAdjustment($tenant, $companyId);
        }

        if (! $this->validate([
            'warehouse_id' => 'required|is_natural_no_zero',
            'qty' => 'required|decimal',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $warehouseId = $this->nullableInt($this->request->getPost('warehouse_id'));
        if ($warehouseId === null) {
            return redirect()->back()->withInput()->with('error', 'Warehouse is required for stock adjustment.');
        }

        $locationId = $this->resolveLocationId($warehouseId, $this->request->getPost('location_id'), $tenant);

        $qty = $this->toNumber($this->request->getPost('qty'));
        if ($qty === 0.0) {
            return redirect()->back()->withInput()->with('error', 'Adjustment quantity cannot be zero. Use positive qty to add stock or negative qty to reduce stock.');
        }

        $itemCode = strtoupper(trim((string) ($this->request->getPost('item_code') ?: $this->request->getPost('manual_item_code'))));
        if ($itemCode === '') {
            return redirect()->back()->withInput()->with('error', 'Item code is required. Select item or fill manual item code.');
        }

        $unitCost = $this->toNumber($this->request->getPost('unit_cost'));
        if ($qty > 0 && $unitCost <= 0) {
            return redirect()->back()->withInput()->with('error', 'Unit cost must be greater than zero when adding stock so stock value and GL can be calculated.');
        }

        $item = $this->findItem($itemCode, $tenant);
        $itemName = trim((string) ($item['name'] ?? $item['item_name'] ?? $this->request->getPost('item_name')));

        if ($itemName === '') {
            $itemName = $itemCode;
        }

        try {
            $this->assertWarehouseLocation($warehouseId, $locationId, $tenant);

            (new InventoryStockService())->adjust([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'item_id' => isset($item['id']) ? (int) $item['id'] : null,
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'uom_code' => trim((string) ($this->request->getPost('uom_code') ?: ($item['uom_code'] ?? $item['base_uom_code'] ?? 'PCS'))),
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'movement_type' => 'stock_adjustment',
                'movement_date' => date('Y-m-d H:i:s'),
                'reference_type' => 'stock_adjustment',
                'reference_no' => trim((string) ($this->request->getPost('reference_no') ?: 'ADJ-' . date('Ymd-His'))),
                'notes' => trim((string) $this->request->getPost('notes')),
            ], auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/inventory/stock-adjustment')->with('message', 'Stock adjustment posted.');
    }

    private function storeBulkSoAdjustment(TenantContext $tenant, int $companyId)
    {
        $warehouseId = $this->nullableInt($this->request->getPost('warehouse_id'));
        if ($warehouseId === null) {
            return redirect()->back()->withInput()->with('error', 'Warehouse is required for stock adjustment.');
        }

        $locationId = $this->resolveLocationId($warehouseId, $this->request->getPost('location_id'), $tenant);
        $referenceNo = trim((string) ($this->request->getPost('reference_no') ?: 'ADJ-' . date('Ymd-His')));
        $notes = trim((string) $this->request->getPost('notes'));
        $sourceSoId = $this->nullableInt($this->request->getPost('source_so_id'));
        $sourceSoNo = trim((string) $this->request->getPost('source_so_no'));

        $itemCodes = (array) $this->request->getPost('item_code');
        $itemNames = (array) $this->request->getPost('item_name');
        $uoms = (array) $this->request->getPost('uom_code');
        $qtys = (array) $this->request->getPost('qty');
        $unitCosts = (array) $this->request->getPost('unit_cost');

        $posted = 0;
        $service = new InventoryStockService();

        try {
            $this->assertWarehouseLocation($warehouseId, $locationId, $tenant);

            foreach ($itemCodes as $index => $rawCode) {
                $itemCode = strtoupper(trim((string) $rawCode));
                $qty = $this->toNumber($qtys[$index] ?? 0);

                if ($itemCode === '' && $qty <= 0) {
                    continue;
                }
                if ($itemCode === '') {
                    throw new RuntimeException('Item code is required on every SO stock adjustment line.');
                }
                if ($qty <= 0) {
                    continue;
                }

                $unitCost = $this->toNumber($unitCosts[$index] ?? 0);
                if ($unitCost <= 0) {
                    throw new RuntimeException('Unit cost must be greater than zero for item ' . $itemCode . '.');
                }

                $item = $this->findItem($itemCode, $tenant);
                $itemName = trim((string) ($itemNames[$index] ?? $item['name'] ?? $item['item_name'] ?? ''));
                if ($itemName === '') {
                    $itemName = $itemCode;
                }

                $service->adjust([
                    'company_id' => $companyId,
                    'site_id' => $tenant->activeSiteId(),
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'item_id' => isset($item['id']) ? (int) $item['id'] : null,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'uom_code' => trim((string) ($uoms[$index] ?? $item['uom_code'] ?? $item['base_uom_code'] ?? 'PCS')),
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'movement_type' => 'stock_adjustment',
                    'movement_date' => date('Y-m-d H:i:s'),
                    'reference_type' => 'so_stock_adjustment',
                    'reference_no' => $referenceNo,
                    'notes' => $notes !== '' ? $notes : trim('SO stock adjustment ' . $sourceSoNo),
                ], auth()->id());

                $posted++;
            }
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        if ($posted < 1) {
            return redirect()->back()->withInput()->with('error', 'At least one SO item qty must be greater than zero.');
        }

        $message = 'SO stock adjustment posted for ' . $posted . ' item(s).';
        if ($sourceSoId !== null) {
            return redirect()->to('/sales/orders/' . $sourceSoId . '/deliver?warehouse_id=' . $warehouseId . '&location_id=' . $locationId)->with('message', $message . ' Stock is refreshed for delivery.');
        }

        return redirect()->to('/inventory/stock-adjustment')->with('message', $message);
    }

    private function findItem(string $itemCode, TenantContext $tenant): ?array
    {
        $db = Database::connect();
        if (! $db->tableExists('items')) {
            return null;
        }

        $builder = $db->table('items');
        $builder->groupStart();
        if ($db->fieldExists('code', 'items')) {
            $builder->where('code', $itemCode);
        }
        if ($db->fieldExists('item_code', 'items')) {
            $builder->orWhere('item_code', $itemCode);
        }
        $builder->groupEnd();

        if ($db->fieldExists('deleted_at', 'items')) {
            $builder->where('deleted_at', null);
        }
        if ($db->fieldExists('is_active', 'items')) {
            $builder->where('is_active', 1);
        }
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', 'items')) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', 'items')) {
            $builder->groupStart()
                ->where('site_id', $tenant->activeSiteId())
                ->orWhere('site_id', null)
                ->groupEnd();
        }

        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    private function resolveLocationId(int $warehouseId, mixed $postedLocationId, TenantContext $tenant): int
    {
        $posted = trim((string) $postedLocationId);
        if ($posted !== '' && $posted !== '__auto__' && (int) $posted > 0) {
            return (int) $posted;
        }

        $db = Database::connect();
        if (! $db->tableExists('locations')) {
            throw new RuntimeException('Location master table is not available.');
        }

        $location = $db->table('locations')->where('warehouse_id', $warehouseId);
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', 'locations')) {
            $location->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', 'locations')) {
            $location->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        }
        if ($db->fieldExists('deleted_at', 'locations')) {
            $location->where('deleted_at', null);
        }
        if ($db->fieldExists('is_active', 'locations')) {
            $location->where('is_active', 1);
        }

        $existing = $location->orderBy('id', 'ASC')->get(1)->getRowArray();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $warehouse = $db->table('warehouses')->where('id', $warehouseId)->get(1)->getRowArray();
        if ($warehouse === null) {
            throw new RuntimeException('Selected warehouse is not valid.');
        }

        $payload = [
            'company_id' => $tenant->activeCompanyId(),
            'site_id' => $tenant->activeSiteId(),
            'warehouse_id' => $warehouseId,
            'code' => 'MAIN',
            'name' => 'Main Location',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        foreach (array_keys($payload) as $field) {
            if (! $db->fieldExists($field, 'locations')) {
                unset($payload[$field]);
            }
        }

        $db->table('locations')->insert($payload);
        $locationId = (int) $db->insertID();
        if ($locationId < 1) {
            throw new RuntimeException('Failed to auto-create default location for selected warehouse.');
        }

        return $locationId;
    }

    private function assertWarehouseLocation(int $warehouseId, int $locationId, TenantContext $tenant): void
    {
        $db = Database::connect();

        if (! $db->tableExists('warehouses') || ! $db->tableExists('locations')) {
            throw new RuntimeException('Warehouse/location master table is not available.');
        }

        $warehouse = $db->table('warehouses')->where('id', $warehouseId);
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', 'warehouses')) {
            $warehouse->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', 'warehouses')) {
            $warehouse->where('site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', 'warehouses')) {
            $warehouse->where('deleted_at', null);
        }
        if ($db->fieldExists('is_active', 'warehouses')) {
            $warehouse->where('is_active', 1);
        }
        if ($warehouse->get(1)->getRowArray() === null) {
            throw new RuntimeException('Selected warehouse is not valid for active company/site.');
        }

        $location = $db->table('locations')->where('id', $locationId)->where('warehouse_id', $warehouseId);
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', 'locations')) {
            $location->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', 'locations')) {
            $location->groupStart()->where('site_id', $tenant->activeSiteId())->orWhere('site_id', null)->groupEnd();
        }
        if ($db->fieldExists('deleted_at', 'locations')) {
            $location->where('deleted_at', null);
        }
        if ($db->fieldExists('is_active', 'locations')) {
            $location->where('is_active', 1);
        }
        if ($location->get(1)->getRowArray() === null) {
            throw new RuntimeException('Selected location does not belong to selected warehouse or active company/site.');
        }
    }

    private function masterRows(string $table, array $contextItemCodes = []): array
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
            if ($table === 'items' || $table === 'locations') {
                $builder->groupStart()
                    ->where('site_id', $tenant->activeSiteId())
                    ->orWhere('site_id', null)
                    ->groupEnd();
            } else {
                $builder->where('site_id', $tenant->activeSiteId());
            }
        }
        if ($table === 'items' && $contextItemCodes !== []) {
            $codeField = $db->fieldExists('item_code', 'items') ? 'item_code' : 'code';
            $builder->whereIn($codeField, $contextItemCodes);
        }

        return $builder->orderBy($db->fieldExists('code', $table) ? 'code' : 'id', 'ASC')->get()->getResultArray();
    }

    private function recentMovements(array $contextItemCodes = []): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();

        if (! $db->tableExists('inventory_stock_movements')) {
            return [];
        }

        $builder = $db->table('inventory_stock_movements');
        if ($tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        if ($contextItemCodes !== []) {
            $builder->whereIn('item_code', $contextItemCodes);
        }

        return $builder->orderBy('id', 'DESC')->get(20)->getResultArray();
    }

    private function contextItemCodes(): array
    {
        $raw = $this->request->getGet('item_codes') ?? $this->request->getGet('item_code') ?? '';
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        $codes = [];
        foreach ($values as $value) {
            $code = strtoupper(trim((string) $value));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    private function contextQtyByCode(): array
    {
        $raw = $this->request->getGet('item_qtys') ?? '';
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        $qtyByCode = [];
        foreach ($values as $value) {
            $parts = explode(':', (string) $value, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $code = strtoupper(trim($parts[0]));
            $qty = $this->toNumber($parts[1]);
            if ($code !== '' && $qty > 0) {
                $qtyByCode[$code] = $qty;
            }
        }

        return $qtyByCode;
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
