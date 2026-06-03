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
        return view('inventory/stock_adjustments/form', [
            'title' => 'Stock Adjustment',
            'items' => $this->masterRows('items'),
            'warehouses' => $this->masterRows('warehouses'),
            'locations' => $this->masterRows('locations'),
            'recentMovements' => $this->recentMovements(),
        ]);
    }

    public function store()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();

        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        $qty = (float) $this->request->getPost('qty');
        if ($qty === 0.0) {
            return redirect()->back()->withInput()->with('error', 'Adjustment quantity cannot be zero.');
        }

        $itemCode = trim((string) ($this->request->getPost('item_code') ?: $this->request->getPost('manual_item_code')));
        if ($itemCode === '') {
            return redirect()->back()->withInput()->with('error', 'Item code is required. Select item or fill manual item code.');
        }

        if (! $this->validate(['qty' => 'required|decimal'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $db = Database::connect();
        $item = $db->table('items')->where('code', $itemCode)->get()->getRowArray();
        $itemName = $item['name'] ?? trim((string) $this->request->getPost('item_name'));

        if ($itemName === '') {
            $itemName = $itemCode;
        }

        try {
            (new InventoryStockService())->adjust([
                'company_id' => $companyId,
                'site_id' => $tenant->activeSiteId(),
                'warehouse_id' => $this->nullableInt($this->request->getPost('warehouse_id')),
                'location_id' => $this->nullableInt($this->request->getPost('location_id')),
                'item_id' => isset($item['id']) ? (int) $item['id'] : null,
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'uom_code' => trim((string) ($this->request->getPost('uom_code') ?: 'PCS')),
                'qty' => $qty,
                'unit_cost' => (float) ($this->request->getPost('unit_cost') ?: 0),
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

    private function recentMovements(): array
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

        return $builder->orderBy('id', 'DESC')->get(20)->getResultArray();
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
