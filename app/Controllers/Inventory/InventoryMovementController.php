<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\InventoryMovementDocumentLineModel;
use App\Models\InventoryMovementDocumentModel;
use App\Models\InventoryStockMovementModel;
use App\Services\AuditLogService;
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
            'recentDocuments' => $this->recentDocuments(['inventory_in_out']),
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
            $this->assertDocumentNoAvailable((int) $payload['company_id'], $payload['site_id'] ?? null, (string) $payload['reference_no']);
            $stock = new InventoryStockService();
            $movementId = $direction === 'in' ? $stock->stockIn($payload, auth()->id()) : $stock->stockOut($payload, auth()->id());
            $documentId = $this->createPostedDocument($payload + [
                'document_type' => 'inventory_in_out',
                'direction' => $direction,
                'movement_id' => $movementId,
            ], auth()->id());
            (new InventoryStockMovementModel())->update($movementId, ['reference_id' => $documentId]);
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
            'recentDocuments' => $this->recentDocuments(['stock_opname']),
        ]);
    }

    public function storeStockOpname()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        $countedQty = (float) $this->request->getPost('counted_qty');

        try {
            $payload = $this->movementPayload($companyId, $tenant, [
                'qty' => 1,
                'movement_type' => 'stock_opname',
                'reference_type' => 'stock_opname',
                'reference_no' => trim((string) ($this->request->getPost('reference_no') ?: 'OPN-' . date('Ymd-His'))),
            ]);
            $this->assertDocumentNoAvailable((int) $payload['company_id'], $payload['site_id'] ?? null, (string) $payload['reference_no']);
            $systemQty = $this->currentStockQty($payload);
            $variance = $countedQty - $systemQty;
            if (abs($variance) < 0.0000001) {
                return redirect()->back()->withInput()->with('error', 'No variance to post.');
            }

            $payload['qty'] = $variance;
            $payload['notes'] = trim(($payload['notes'] ?? '') . ' System qty: ' . number_format($systemQty, 4, '.', '') . '; counted qty: ' . number_format($countedQty, 4, '.', ''));
            $movementId = (new InventoryStockService())->adjust($payload, auth()->id());
            $documentId = $this->createPostedDocument($payload + [
                'document_type' => 'stock_opname',
                'direction' => $variance > 0 ? 'in' : 'out',
                'movement_id' => $movementId,
                'system_qty' => $systemQty,
                'counted_qty' => $countedQty,
            ], auth()->id());
            (new InventoryStockMovementModel())->update($movementId, ['reference_id' => $documentId]);
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/inventory/stock-opname')->with('message', 'Stock opname variance posted.');
    }

    public function showDocument(int $id): string
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('inventory_movement_documents d')
            ->select('d.*, w.code warehouse_code, w.name warehouse_name, l.code location_code, l.name location_name')
            ->join('warehouses w', 'w.id = d.warehouse_id', 'left')
            ->join('locations l', 'l.id = d.location_id', 'left')
            ->where('d.id', $id)
            ->where('d.deleted_at', null);

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('d.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('d.site_id', $tenant->activeSiteId());
        }

        $document = $builder->get()->getRowArray();
        if ($document === null) {
            throw new RuntimeException('Inventory movement document not found.');
        }

        $lines = $db->table('inventory_movement_document_lines l')
            ->select('l.*, m.gl_entry_id, m.direction movement_direction')
            ->join('inventory_stock_movements m', 'm.id = l.stock_movement_id', 'left')
            ->where('l.document_id', $id)
            ->orderBy('l.line_no', 'ASC')
            ->get()
            ->getResultArray();

        return view('inventory/movements/show_document', [
            'title' => 'Inventory Document ' . $document['document_no'],
            'document' => $document,
            'lines' => $lines,
        ]);
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
            'movement_date' => $this->movementDate(),
            'notes' => trim((string) $this->request->getPost('notes')),
        ];
    }

    private function movementDate(): string
    {
        $date = trim((string) $this->request->getPost('movement_date'));
        if ($date === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            throw new RuntimeException('Movement date is invalid.');
        }

        return date('Y-m-d H:i:s', $timestamp);
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

    private function currentStockQty(array $payload): float
    {
        $db = Database::connect();
        if (! $db->tableExists('inventory_stock_balances')) {
            return 0.0;
        }

        $builder = $db->table('inventory_stock_balances')
            ->selectSum('qty_on_hand', 'qty_on_hand')
            ->where('company_id', $payload['company_id'])
            ->where('item_code', $payload['item_code'])
            ->where('batch_no', trim((string) ($payload['batch_no'] ?? '')));

        foreach (['site_id', 'warehouse_id', 'location_id'] as $field) {
            empty($payload[$field])
                ? $builder->where($field, null)
                : $builder->where($field, (int) $payload[$field]);
        }

        return (float) ($builder->get()->getRowArray()['qty_on_hand'] ?? 0);
    }

    private function createPostedDocument(array $payload, ?int $userId): int
    {
        $movement = (new InventoryStockMovementModel())->find((int) $payload['movement_id']);
        if ($movement === null) {
            throw new RuntimeException('Stock movement was posted but cannot be found for document snapshot.');
        }

        $now = date('Y-m-d H:i:s');
        $qty = (float) ($movement['qty'] ?? abs((float) $payload['qty']));
        $stockValue = (float) ($movement['stock_value'] ?? 0);
        $documentNo = trim((string) ($payload['reference_no'] ?? 'INV-' . date('Ymd-His')));

        $documentModel = new InventoryMovementDocumentModel();

        $documentModel->insert([
            'company_id' => (int) $payload['company_id'],
            'site_id' => ! empty($payload['site_id']) ? (int) $payload['site_id'] : null,
            'document_no' => $documentNo,
            'document_date' => (string) ($payload['movement_date'] ?? $now),
            'document_type' => (string) ($payload['document_type'] ?? 'inventory_in_out'),
            'direction' => $payload['direction'] ?? null,
            'status' => 'posted',
            'warehouse_id' => $payload['warehouse_id'] ?? null,
            'location_id' => $payload['location_id'] ?? null,
            'total_qty' => $qty,
            'total_value' => $stockValue,
            'notes' => $payload['notes'] ?? null,
            'posted_at' => $now,
            'posted_by' => $userId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        $documentId = (int) $documentModel->getInsertID();
        if ($documentId < 1) {
            throw new RuntimeException('Failed to create inventory movement document.');
        }

        (new InventoryMovementDocumentLineModel())->insert([
            'document_id' => $documentId,
            'stock_movement_id' => (int) $payload['movement_id'],
            'line_no' => 1,
            'item_id' => $payload['item_id'] ?? null,
            'item_code' => (string) $payload['item_code'],
            'item_name' => $payload['item_name'] ?? null,
            'batch_no' => trim((string) ($payload['batch_no'] ?? '')),
            'uom_code' => $payload['uom_code'] ?? 'PCS',
            'system_qty' => $payload['system_qty'] ?? null,
            'counted_qty' => $payload['counted_qty'] ?? null,
            'qty' => $qty,
            'unit_cost' => (float) ($movement['unit_cost'] ?? $payload['unit_cost'] ?? 0),
            'stock_value' => $stockValue,
            'notes' => $payload['notes'] ?? null,
        ]);

        (new AuditLogService())->log('inventory.stock', 'movement_document.post', [
            'company_id' => $payload['company_id'] ?? null,
            'site_id' => $payload['site_id'] ?? null,
            'user_id' => $userId,
            'table_name' => 'inventory_movement_documents',
            'record_id' => $documentId,
            'record_code' => $documentNo,
            'description' => 'Inventory movement document snapshot created.',
            'new_values' => [
                'document_type' => $payload['document_type'] ?? 'inventory_in_out',
                'movement_id' => $payload['movement_id'],
                'qty' => $qty,
                'stock_value' => $stockValue,
            ],
        ]);

        return $documentId;
    }

    private function assertDocumentNoAvailable(int $companyId, ?int $siteId, string $documentNo): void
    {
        $documentNo = trim($documentNo);
        if ($documentNo === '' || ! Database::connect()->tableExists('inventory_movement_documents')) {
            return;
        }

        $query = (new InventoryMovementDocumentModel())
            ->where('company_id', $companyId)
            ->where('document_no', $documentNo)
            ->where('deleted_at', null);

        $siteId === null || $siteId < 1
            ? $query->where('site_id', null)
            : $query->where('site_id', $siteId);

        if ($query->first() !== null) {
            throw new RuntimeException('Inventory document number already exists.');
        }
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

    private function recentDocuments(array $types): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        if (! $db->tableExists('inventory_movement_documents')) {
            return [];
        }

        $builder = $db->table('inventory_movement_documents')
            ->where('deleted_at', null);
        if ($tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        if ($types !== []) {
            $builder->whereIn('document_type', $types);
        }

        return $builder->orderBy('id', 'DESC')->get(10)->getResultArray();
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
