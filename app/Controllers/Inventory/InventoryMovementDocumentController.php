<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\InventoryMovementDocumentLineModel;
use App\Models\InventoryMovementDocumentModel;
use App\Models\InventoryStockMovementModel;
use App\Services\AuditLogService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Inventory\InventoryStockService;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;
use Throwable;

class InventoryMovementDocumentController extends BaseController
{
    public function reverse(int $id)
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $stock = new InventoryStockService();
        $movementModel = new InventoryStockMovementModel();
        $lineModel = new InventoryMovementDocumentLineModel();
        $documentModel = new InventoryMovementDocumentModel();
        $now = date('Y-m-d H:i:s');
        $reason = trim((string) $this->request->getPost('reversal_reason')) ?: null;

        $db->transBegin();

        try {
            $document = $this->document($id, $tenant);
            $documentType = (string) ($document['document_type'] ?? '');

            if ((string) ($document['status'] ?? '') !== 'posted') {
                throw new RuntimeException('Only posted inventory document can be reversed.');
            }
            if (str_ends_with($documentType, '_reversal')) {
                throw new RuntimeException('Reversal document cannot be reversed from this screen.');
            }
            if (! in_array($documentType, ['inventory_in_out', 'stock_opname'], true)) {
                throw new RuntimeException('This inventory document type does not support reversal yet.');
            }
            if (! empty($document['reversal_document_id'])) {
                throw new RuntimeException('Inventory document has already been reversed.');
            }
            (new PeriodCloseService())->assertOpen(
                'inventory',
                (int) ($document['company_id'] ?? 0),
                (string) ($document['document_date'] ?? date('Y-m-d')),
                ! empty($document['site_id']) ? (int) $document['site_id'] : null
            );

            $lines = $db->table('inventory_movement_document_lines l')
                ->select('l.*, m.direction movement_direction, m.movement_type, m.warehouse_id movement_warehouse_id, m.location_id movement_location_id, m.unit_cost movement_unit_cost')
                ->join('inventory_stock_movements m', 'm.id = l.stock_movement_id', 'left')
                ->where('l.document_id', $id)
                ->orderBy('l.line_no', 'ASC')
                ->get()
                ->getResultArray();

            if ($lines === []) {
                throw new RuntimeException('Cannot reverse inventory document without lines.');
            }

            $reversalNo = $this->nextReversalNo((string) $document['document_no']);
            $documentModel->insert([
                'company_id' => (int) $document['company_id'],
                'site_id' => $document['site_id'] !== null ? (int) $document['site_id'] : null,
                'document_no' => $reversalNo,
                'document_date' => $document['document_date'] ?? $now,
                'document_type' => $documentType . '_reversal',
                'direction' => 'mixed',
                'status' => 'posted',
                'warehouse_id' => $document['warehouse_id'] !== null ? (int) $document['warehouse_id'] : null,
                'location_id' => $document['location_id'] !== null ? (int) $document['location_id'] : null,
                'total_qty' => 0,
                'total_value' => 0,
                'notes' => trim(($reason ?? '') . ' Reversal for document ' . $document['document_no']),
                'posted_at' => $now,
                'posted_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $reversalDocumentId = (int) $documentModel->getInsertID();
            if ($reversalDocumentId < 1) {
                throw new RuntimeException('Failed to create inventory reversal document.');
            }

            $totalQty = 0.0;
            $totalValue = 0.0;
            $directions = [];

            foreach ($lines as $line) {
                if (empty($line['stock_movement_id'])) {
                    throw new RuntimeException('Document line does not have stock movement reference.');
                }
                if (! empty($line['reversal_movement_id'])) {
                    throw new RuntimeException('Document line has already been reversed.');
                }

                $originalDirection = (string) ($line['movement_direction'] ?? '');
                if (! in_array($originalDirection, ['in', 'out'], true)) {
                    throw new RuntimeException('Original stock movement direction is invalid.');
                }

                $reverseDirection = $originalDirection === 'in' ? 'out' : 'in';
                $directions[] = $reverseDirection;
                $lineNo = (int) ($line['line_no'] ?? 1);
                $referenceNo = $reversalNo . '-' . str_pad((string) $lineNo, 3, '0', STR_PAD_LEFT);
                $payload = [
                    'company_id' => (int) $document['company_id'],
                    'site_id' => $document['site_id'] !== null ? (int) $document['site_id'] : null,
                    'warehouse_id' => $line['movement_warehouse_id'] !== null ? (int) $line['movement_warehouse_id'] : null,
                    'location_id' => $line['movement_location_id'] !== null ? (int) $line['movement_location_id'] : null,
                    'item_id' => $line['item_id'] !== null ? (int) $line['item_id'] : null,
                    'item_code' => (string) $line['item_code'],
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'item_name' => $line['item_name'] ?? null,
                    'uom_code' => $line['uom_code'] ?? 'PCS',
                    'qty' => (float) ($line['qty'] ?? 0),
                    'unit_cost' => (float) ($line['movement_unit_cost'] ?? $line['unit_cost'] ?? 0),
                    'movement_date' => $document['document_date'] ?? $now,
                    'movement_type' => $documentType,
                    'direction' => $reverseDirection,
                    'reference_type' => 'inventory_movement_reversal',
                    'reference_id' => $reversalDocumentId,
                    'reference_no' => $referenceNo,
                    'notes' => trim(($reason ?? '') . ' Reversal for ' . $document['document_no']),
                ];

                $reversalMovementId = $reverseDirection === 'in'
                    ? $stock->stockIn($payload, auth()->id())
                    : $stock->stockOut($payload, auth()->id());

                $movement = $movementModel->find($reversalMovementId);
                if ($movement === null) {
                    throw new RuntimeException('Reversal stock movement was posted but cannot be found.');
                }

                $lineModel->update((int) $line['id'], [
                    'reversal_movement_id' => $reversalMovementId,
                    'updated_at' => $now,
                ]);

                $lineQty = (float) ($movement['qty'] ?? $line['qty'] ?? 0);
                $lineValue = (float) ($movement['stock_value'] ?? 0);
                $totalQty += $lineQty;
                $totalValue += $lineValue;

                $lineModel->insert([
                    'document_id' => $reversalDocumentId,
                    'stock_movement_id' => $reversalMovementId,
                    'line_no' => $lineNo,
                    'item_id' => $line['item_id'] ?? null,
                    'item_code' => (string) $line['item_code'],
                    'item_name' => $line['item_name'] ?? null,
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'uom_code' => $line['uom_code'] ?? 'PCS',
                    'system_qty' => null,
                    'counted_qty' => null,
                    'qty' => $lineQty,
                    'unit_cost' => (float) ($movement['unit_cost'] ?? $line['unit_cost'] ?? 0),
                    'stock_value' => $lineValue,
                    'notes' => 'Reversal line for document ' . $document['document_no'],
                ]);
            }

            $reversalDirection = count(array_unique($directions)) === 1 ? $directions[0] : 'mixed';
            $documentModel->update($reversalDocumentId, [
                'direction' => $reversalDirection,
                'total_qty' => $totalQty,
                'total_value' => $totalValue,
                'updated_by' => auth()->id(),
            ]);

            $documentModel->update($id, [
                'status' => 'reversed',
                'reversed_at' => $now,
                'reversed_by' => auth()->id(),
                'reversal_reason' => $reason,
                'reversal_document_id' => $reversalDocumentId,
                'updated_by' => auth()->id(),
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to reverse inventory movement document.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            return redirect()->back()->with('error', $exception->getMessage());
        }

        (new AuditLogService())->log('inventory.stock', 'movement_document.reverse', [
            'company_id' => $document['company_id'] ?? null,
            'site_id' => $document['site_id'] ?? null,
            'user_id' => auth()->id(),
            'table_name' => 'inventory_movement_documents',
            'record_id' => $id,
            'record_code' => $document['document_no'] ?? null,
            'description' => 'Inventory movement document reversed.',
            'old_values' => ['status' => $document['status'] ?? null],
            'new_values' => [
                'status' => 'reversed',
                'reversal_document_id' => $reversalDocumentId,
                'reversal_no' => $reversalNo,
                'reason' => $reason,
            ],
        ]);

        return redirect()->to('/inventory/movement-documents/' . $reversalDocumentId)->with('message', 'Inventory movement document reversed.');
    }

    private function document(int $id, TenantContext $tenant): array
    {
        $builder = Database::connect()->table('inventory_movement_documents')
            ->where('id', $id)
            ->where('deleted_at', null);

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }

        $document = $builder->get()->getRowArray();
        if ($document === null) {
            throw new RuntimeException('Inventory movement document not found.');
        }

        return $document;
    }

    private function nextReversalNo(string $documentNo): string
    {
        $base = substr($documentNo, 0, 42) . '-REV';
        $candidate = $base;
        $counter = 2;
        $model = new InventoryMovementDocumentModel();

        while ($model->where('document_no', $candidate)->where('deleted_at', null)->first() !== null) {
            $suffix = '-' . $counter;
            $candidate = substr($base, 0, 50 - strlen($suffix)) . $suffix;
            $counter++;
        }

        return $candidate;
    }
}
