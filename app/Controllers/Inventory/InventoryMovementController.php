<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\Inventory\InventoryStockService;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;

class InventoryMovementController extends BaseController
{
    public function inOut(): string
    {
        return view('inventory/movements/in_out', $this->formData('Inventory In Out') + [
            'recentMovements' => $this->recentMovements(['manual_in', 'manual_out', 'inventory_in_out']),
        ]);
    }

    public function storeInOut()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        $direction = (string) $this->request->getPost('direction');
        if (! in_array($direction, ['in', 'out'], true)) {
            return redirect()->back()->withInput()->with('error', 'Direction must be in or out.');
        }

        try {
            $payload = $this->movementPayload($companyId, $tenant, [
                'direction' => $direction,
                'movement_type' => $direction === 'in' ? 'manual_in' : 'manual_out',
                'reference_type' => 'inventory_in_out',
                'reference_no' => trim((string) ($this->request->getPost('reference_no') ?: 'IO-' . date('Ymd-His'))),
            ]);
            $stock = new InventoryStockService();
            $direction === 'in' ? $stock->stockIn($payload, auth()->id()) : $stock->stockOut($payload, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/inventory/in-out')->with('message', 'Inventory movement posted.');
    }

    public function transfer(): string
    {
        return view('inventory/movements/transfer', $this->formData('Inventory Transfer') + [
            'recentMovements' => $this->recentMovements(['transfer_out', 'transfer_in']),
        ]);
    }

    public function storeTransfer()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        $stock = new InventoryStockService();
        $db = Database::connect();
        $db->transBegin();

        try {
            $referenceNo = trim((string) ($this->request->getPost('reference_no') ?: 'TRF-' . date('Ymd-His')));
            $base = $this->movementPayload($companyId, $tenant, [
                'reference_type' => 'inventory_transfer',
                'reference_no' => $referenceNo,
            ]);
            $toWarehouseId = $this->nullableInt($this->request->getPost('to_warehouse_id'));
            $toLocationId = $this->nullableInt($this->request->getPost('to_location_id'));

            if (($base['warehouse_id'] ?? null) === $toWarehouseId && ($base['location_id'] ?? null) === $toLocationId) {
                throw new RuntimeException('Source and destination cannot be the same.');
            }

            $stock->stockOut($base + [
                'movement_type' => 'transfer_out',
                'direction' => 'out',
                'notes' => trim(($base['notes'] ?? '') . ' Transfer out to destination.'),
            ], auth()->id());
            $stock->stockIn($base + [
                'warehouse_id' => $toWarehouseId,
                'location_id' => $toLocationId,
                'movement_type' => 'transfer_in',
                'direction' => 'in',
                'notes' => trim(($base['notes'] ?? '') . ' Transfer in from source.'),
            ], auth()->id());

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post inventory transfer.');
            }

            $db->transCommit();
        } catch (RuntimeException $exception) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/inventory/transfers')->with('message', 'Inventory transfer posted.');
    }

    public function stockOpname(): string
    {
        return view('inventory/movements/stock_opname', $this->formData('Inventory Stock Opname') + [
            'balances' => $this->stockBalances(),
            'recentMovements' => $this->recentMovements(['stock_opname']),
        ]);
    }

    public function storeStockOpname()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        $systemQty = (float) $this->request->getPost('system_qty');
        $countedQty = (float) $this->request->getPost('counted_qty');
        $variance = $countedQty - $systemQty;
        if ($variance === 0.0) {
            return redirect()->back()->withInput()->with('error', 'No variance to post.');
        }

        try {
            $payload = $this->movementPayload($companyId, $tenant, [
                'qty' => $variance,
                'movement_type' => 'stock_opname',
                'reference_type' => 'stock_opname',
                'reference_no' => trim((string) ($this->request->getPost('reference_no') ?: 'OPN-' . date('Ymd-His'))),
            ]);
            (new InventoryStockService())->adjust($payload, auth()->id());
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/inventory/stock-opname')->with('message', 'Stock opname variance posted.');
    }

    private function movementPayload(int $companyId, TenantContext $tenant, array $overrides = []): array
    {
        $qty = (float) ($overrides['qty'] ?? $this->request->getPost('qty'));
        if ($qty === 0.0) {
            throw new RuntimeException('Quantity cannot be zero.');
        }

        $itemCode = trim((string) ($this->request->getPost('item_code') ?: $this->request->getPost('manual_item_code')));
        if ($itemCode === '') {
            throw new RuntimeException('Item code is required.');
        }

        $db = Database::connect();
        $item = $db->table('items')
            ->where('company_id', $companyId)
            ->groupStart()
                ->where('item_code', $itemCode)
                ->orWhere('code', $itemCode)
            ->groupEnd()
            ->get()
            ->getRowArray();

        return $overrides + [
            'company_id' => $companyId,
            'site_id' => $tenant->activeSiteId(),
            'warehouse_id' => $this->nullableInt($this->request->getPost('warehouse_id')),
            'location_id' => $this->nullableInt($this->request->getPost('location_id')),
            'item_id' => isset($item['id']) ? (int) $item['id'] : null,
            'item_code' => $itemCode,
            'batch_no' => trim((string) $this->request->getPost('batch_no')),
            'item_name' => $item['item_name'] ?? $item['name'] ?? trim((string) $this->request->getPost('item_name')) ?: $itemCode,
            'uom_code' => trim((string) ($this->request->getPost('uom_code') ?: ($item['stockuom'] ?? 'PCS'))),
            'qty' => array_key_exists('qty', $overrides) ? $qty : abs($qty),
            'unit_cost' => (float) ($this->request->getPost('unit_cost') ?: ($item['item_price'] ?? 0)),
            'movement_date' => date('Y-m-d H:i:s'),
            'notes' => trim((string) $this->request->getPost('notes')),
        ];
    }

    private function formData(string $title): array
    {
        return [
            'title' => $title,
            'items' => $this->masterRows('items'),
            'warehouses' => $this->masterRows('warehouses'),
            'locations' => $this->masterRows('locations'),
        ];
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

        return $builder->orderBy($db->fieldExists('code', $table) ? 'code' : 'id', 'ASC')->get()->getResultArray();
    }

    private function stockBalances(): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('inventory_stock_balances b')
            ->select('b.*, w.code warehouse_code, l.code location_code')
            ->join('warehouses w', 'w.id = b.warehouse_id', 'left')
            ->join('locations l', 'l.id = b.location_id', 'left')
            ->where('b.qty_on_hand !=', 0);
        if ($tenant->activeCompanyId() !== null) {
            $builder->where('b.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('b.site_id', $tenant->activeSiteId());
        }

        return $builder->orderBy('b.item_code', 'ASC')->get(100)->getResultArray();
    }

    private function recentMovements(array $types): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('inventory_stock_movements');
        if ($tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        if ($types !== []) {
            $builder->whereIn('movement_type', $types);
        }

        return $builder->orderBy('id', 'DESC')->get(20)->getResultArray();
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
