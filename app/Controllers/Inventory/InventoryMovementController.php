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
use Throwable;

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
        $siteId = $tenant->activeSiteId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        $direction = (string) $this->request->getPost('direction');
        if (! in_array($direction, ['in', 'out'], true)) {
            return redirect()->back()->withInput()->with('error', 'Direction must be in or out.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $referenceNo = trim((string) ($this->request->getPost('reference_no') ?: 'IO-' . date('Ymd-His')));
            $movementDate = $this->movementDate();
            $warehouseId = $this->nullableInt($this->request->getPost('warehouse_id'));
            $locationId = $this->nullableInt($this->request->getPost('location_id'));
            $notes = trim((string) $this->request->getPost('notes'));
            $base = [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'direction' => $direction,
                'movement_type' => $direction === 'in' ? 'manual_in' : 'manual_out',
                'reference_type' => 'inventory_in_out',
                'reference_no' => $referenceNo,
                'movement_date' => $movementDate,
                'notes' => $notes,
            ];

            $this->assertDocumentNoAvailable($companyId, $siteId, $referenceNo);
            $lines = $this->inOutLines($companyId, $base);
            if ($lines === []) {
                throw new RuntimeException('At least one valid item line is required.');
            }

            $documentModel = new InventoryMovementDocumentModel();
            $documentModel->insert([
                'company_id' => $companyId,
                'site_id' => $siteId,
                'document_no' => $referenceNo,
                'document_date' => $movementDate,
                'document_type' => 'inventory_in_out',
                'direction' => $direction,
                'status' => 'posted',
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'total_qty' => 0,
                'total_value' => 0,
                'notes' => $notes,
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $documentId = (int) $documentModel->getInsertID();
            if ($documentId < 1) {
                throw new RuntimeException('Failed to create inventory movement document.');
            }

            $stock = new InventoryStockService();
            $movementModel = new InventoryStockMovementModel();
            $lineModel = new InventoryMovementDocumentLineModel();
            $totalQty = 0.0;
            $totalValue = 0.0;

            foreach ($lines as $index => $line) {
                $payload = array_merge($base, $line, ['reference_id' => $documentId]);
                $movementId = $direction === 'in' ? $stock->stockIn($payload, auth()->id()) : $stock->stockOut($payload, auth()->id());
                $movement = $movementModel->find($movementId);
                if ($movement === null) {
                    throw new RuntimeException('Stock movement was posted but cannot be found for document line.');
                }

                $lineQty = (float) ($movement['qty'] ?? $line['qty']);
                $lineValue = (float) ($movement['stock_value'] ?? 0);
                $totalQty += $lineQty;
                $totalValue += $lineValue;

                $lineModel->insert([
                    'document_id' => $documentId,
                    'stock_movement_id' => $movementId,
                    'line_no' => $index + 1,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'],
                    'item_name' => $line['item_name'] ?? null,
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'uom_code' => $line['uom_code'] ?? 'PCS',
                    'qty' => $lineQty,
                    'unit_cost' => (float) ($movement['unit_cost'] ?? $line['unit_cost'] ?? 0),
                    'stock_value' => $lineValue,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            $documentModel->update($documentId, [
                'total_qty' => $totalQty,
                'total_value' => $totalValue,
                'updated_by' => auth()->id(),
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post inventory movement document.');
            }

            $db->transCommit();

            (new AuditLogService())->log('inventory.stock', 'inventory_in_out.post', [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'user_id' => auth()->id(),
                'table_name' => 'inventory_movement_documents',
                'record_id' => $documentId,
                'record_code' => $referenceNo,
                'description' => 'Multi-line inventory in/out document posted.',
                'new_values' => [
                    'direction' => $direction,
                    'line_count' => count($lines),
                    'total_qty' => $totalQty,
                    'total_value' => $totalValue,
                ],
            ]);
        } catch (Throwable $exception) {
            $db->transRollback();
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
        $siteId = $tenant->activeSiteId();
        if ($companyId === null || $companyId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company is required.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $referenceNo = trim((string) ($this->request->getPost('reference_no') ?: 'OPN-' . date('Ymd-His')));
            $movementDate = $this->movementDate();
            $notes = trim((string) $this->request->getPost('notes'));
            $base = [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'movement_type' => 'stock_opname',
                'reference_type' => 'stock_opname',
                'reference_no' => $referenceNo,
                'movement_date' => $movementDate,
                'notes' => $notes,
            ];

            $this->assertDocumentNoAvailable($companyId, $siteId, $referenceNo);
            $lines = $this->stockOpnameLines($companyId, $base);
            if ($lines === []) {
                throw new RuntimeException('No variance to post.');
            }

            $directions = array_unique(array_column($lines, 'direction'));
            $documentDirection = count($directions) === 1 ? (string) $directions[0] : 'mixed';

            $documentModel = new InventoryMovementDocumentModel();
            $documentModel->insert([
                'company_id' => $companyId,
                'site_id' => $siteId,
                'document_no' => $referenceNo,
                'document_date' => $movementDate,
                'document_type' => 'stock_opname',
                'direction' => $documentDirection,
                'status' => 'posted',
                'total_qty' => 0,
                'total_value' => 0,
                'notes' => $notes,
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $documentId = (int) $documentModel->getInsertID();
            if ($documentId < 1) {
                throw new RuntimeException('Failed to create stock opname document.');
            }

            $stock = new InventoryStockService();
            $movementModel = new InventoryStockMovementModel();
            $lineModel = new InventoryMovementDocumentLineModel();
            $totalQty = 0.0;
            $totalValue = 0.0;
            $firstWarehouseId = null;
            $firstLocationId = null;

            foreach ($lines as $index => $line) {
                $payload = array_merge($base, $line, ['reference_id' => $documentId]);
                $movementId = $stock->adjust($payload, auth()->id());
                $movement = $movementModel->find($movementId);
                if ($movement === null) {
                    throw new RuntimeException('Stock movement was posted but cannot be found for opname line.');
                }

                $lineQty = (float) ($movement['qty'] ?? abs((float) $line['qty']));
                $lineValue = (float) ($movement['stock_value'] ?? 0);
                $totalQty += $lineQty;
                $totalValue += $lineValue;
                $firstWarehouseId ??= $line['warehouse_id'] ?? null;
                $firstLocationId ??= $line['location_id'] ?? null;

                $lineModel->insert([
                    'document_id' => $documentId,
                    'stock_movement_id' => $movementId,
                    'line_no' => $index + 1,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => $line['item_code'],
                    'item_name' => $line['item_name'] ?? null,
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'uom_code' => $line['uom_code'] ?? 'PCS',
                    'system_qty' => $line['system_qty'],
                    'counted_qty' => $line['counted_qty'],
                    'qty' => $lineQty,
                    'unit_cost' => (float) ($movement['unit_cost'] ?? $line['unit_cost'] ?? 0),
                    'stock_value' => $lineValue,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            $documentModel->update($documentId, [
                'warehouse_id' => $firstWarehouseId,
                'location_id' => $firstLocationId,
                'total_qty' => $totalQty,
                'total_value' => $totalValue,
                'updated_by' => auth()->id(),
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post stock opname document.');
            }

            $db->transCommit();

            (new AuditLogService())->log('inventory.stock', 'stock_opname.post', [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'user_id' => auth()->id(),
                'table_name' => 'inventory_movement_documents',
                'record_id' => $documentId,
                'record_code' => $referenceNo,
                'description' => 'Multi-line stock opname document posted.',
                'new_values' => [
                    'direction' => $documentDirection,
                    'line_count' => count($lines),
                    'total_qty' => $totalQty,
                    'total_value' => $totalValue,
                ],
            ]);
        } catch (Throwable $exception) {
            $db->transRollback();
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

        $item = $this->lookupInventoryItem($companyId, $itemCode);

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

    private function inOutLines(int $companyId, array $base): array
    {
        $itemCodes = (array) $this->request->getPost('line_item_code');
        $manualCodes = (array) $this->request->getPost('line_manual_item_code');
        $itemNames = (array) $this->request->getPost('line_item_name');
        $uomCodes = (array) $this->request->getPost('line_uom_code');
        $batchNos = (array) $this->request->getPost('line_batch_no');
        $qtys = (array) $this->request->getPost('line_qty');
        $unitCosts = (array) $this->request->getPost('line_unit_cost');
        $notes = (array) $this->request->getPost('line_notes');

        $lines = [];
        $max = max(count($itemCodes), count($manualCodes), count($qtys));
        for ($index = 0; $index < $max; $index++) {
            $itemCode = trim((string) ($itemCodes[$index] ?? ''));
            if ($itemCode === '') {
                $itemCode = trim((string) ($manualCodes[$index] ?? ''));
            }

            $qty = (float) ($qtys[$index] ?? 0);
            if ($itemCode === '' && $qty <= 0) {
                continue;
            }
            if ($itemCode === '') {
                throw new RuntimeException('Item code is required on line ' . ($index + 1) . '.');
            }
            if ($qty <= 0) {
                throw new RuntimeException('Qty must be greater than zero on line ' . ($index + 1) . '.');
            }

            $item = $this->lookupInventoryItem($companyId, $itemCode);
            $uom = trim((string) ($uomCodes[$index] ?? ''));
            if ($uom === '') {
                $uom = (string) ($item['stockuom'] ?? 'PCS');
            }

            $unitCost = (float) ($unitCosts[$index] ?? 0);
            if ($unitCost <= 0 && $item !== null) {
                $unitCost = (float) ($item['item_price'] ?? $item['standard_cost'] ?? 0);
            }

            $code = $itemCode !== '' ? $itemCode : (string) ($item['item_code'] ?? $item['code'] ?? '');
            $name = trim((string) ($itemNames[$index] ?? ''));
            if ($name === '') {
                $name = (string) ($item['item_name'] ?? $item['name'] ?? $code);
            }

            $lineNote = trim((string) ($notes[$index] ?? ''));
            $combinedNote = trim((string) ($base['notes'] ?? ''));
            if ($lineNote !== '') {
                $combinedNote = trim($combinedNote . ' ' . $lineNote);
            }

            $lines[] = [
                'item_id' => isset($item['id']) ? (int) $item['id'] : null,
                'item_code' => $code,
                'batch_no' => trim((string) ($batchNos[$index] ?? '')),
                'item_name' => $name,
                'uom_code' => $uom,
                'qty' => abs($qty),
                'unit_cost' => $unitCost,
                'notes' => $combinedNote,
            ];
        }

        return $lines;
    }

    private function lookupInventoryItem(int $companyId, string $itemCode): ?array
    {
        $itemCode = trim($itemCode);
        if ($itemCode === '') {
            return null;
        }

        $db = Database::connect();

        return $db->table('items')
            ->where('company_id', $companyId)
            ->groupStart()
                ->where('item_code', $itemCode)
                ->orWhere('code', $itemCode)
            ->groupEnd()
            ->get()
            ->getRowArray() ?: null;
    }

    private function stockOpnameLines(int $companyId, array $base): array
    {
        $itemCodes = (array) $this->request->getPost('line_item_code');
        $itemNames = (array) $this->request->getPost('line_item_name');
        $warehouseIds = (array) $this->request->getPost('line_warehouse_id');
        $locationIds = (array) $this->request->getPost('line_location_id');
        $batchNos = (array) $this->request->getPost('line_batch_no');
        $uomCodes = (array) $this->request->getPost('line_uom_code');
        $countedQtys = (array) $this->request->getPost('line_counted_qty');
        $unitCosts = (array) $this->request->getPost('line_unit_cost');
        $notes = (array) $this->request->getPost('line_notes');

        $lines = [];
        $max = max(count($itemCodes), count($countedQtys));

        for ($index = 0; $index < $max; $index++) {
            $itemCode = trim((string) ($itemCodes[$index] ?? ''));
            if ($itemCode === '') {
                continue;
            }

            $countedQty = (float) ($countedQtys[$index] ?? 0);
            $item = $this->lookupInventoryItem($companyId, $itemCode);
            $payload = [
                'company_id' => $base['company_id'],
                'site_id' => $base['site_id'] ?? null,
                'warehouse_id' => $this->nullableInt($warehouseIds[$index] ?? null),
                'location_id' => $this->nullableInt($locationIds[$index] ?? null),
                'item_id' => isset($item['id']) ? (int) $item['id'] : null,
                'item_code' => $itemCode,
                'batch_no' => trim((string) ($batchNos[$index] ?? '')),
            ];
            $systemQty = $this->currentStockQty($payload);
            $variance = round($countedQty - $systemQty, 12);

            if (abs($variance) < 0.0000001) {
                continue;
            }

            $uom = trim((string) ($uomCodes[$index] ?? ''));
            if ($uom === '') {
                $uom = (string) ($item['stockuom'] ?? 'PCS');
            }

            $unitCost = (float) ($unitCosts[$index] ?? 0);
            if ($unitCost <= 0 && $item !== null) {
                $unitCost = (float) ($item['item_price'] ?? $item['standard_cost'] ?? 0);
            }

            $name = trim((string) ($itemNames[$index] ?? ''));
            if ($name === '') {
                $name = (string) ($item['item_name'] ?? $item['name'] ?? $itemCode);
            }

            $lineNote = trim((string) ($notes[$index] ?? ''));
            $combinedNote = trim((string) ($base['notes'] ?? ''));
            if ($lineNote !== '') {
                $combinedNote = trim($combinedNote . ' ' . $lineNote);
            }
            $combinedNote = trim($combinedNote . ' System qty: ' . number_format($systemQty, 4, '.', '') . '; counted qty: ' . number_format($countedQty, 4, '.', ''));

            $lines[] = $payload + [
                'item_name' => $name,
                'uom_code' => $uom,
                'qty' => $variance,
                'unit_cost' => $unitCost,
                'system_qty' => $systemQty,
                'counted_qty' => $countedQty,
                'direction' => $variance > 0 ? 'in' : 'out',
                'notes' => $combinedNote,
            ];
        }

        return $lines;
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
            ->select('(SELECT i.item_name FROM items i WHERE i.company_id = b.company_id AND i.item_code = b.item_code LIMIT 1) item_name', false)
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
