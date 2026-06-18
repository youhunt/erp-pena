<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\AuditLogService;
use App\Services\Finance\PeriodCloseService;
use App\Services\Inventory\InventoryStockService;
use App\Services\TenantContext;
use Config\Database;
use RuntimeException;
use Throwable;

class InventoryTransferController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('inventory_transfer_headers h')
            ->select('h.*, fw.code from_warehouse_code, fl.code from_location_code, tw.code to_warehouse_code, tl.code to_location_code, COUNT(l.id) line_count, COALESCE(SUM(l.qty), 0) total_qty')
            ->join('inventory_transfer_lines l', 'l.header_id = h.id', 'left')
            ->join('warehouses fw', 'fw.id = h.from_warehouse_id', 'left')
            ->join('locations fl', 'fl.id = h.from_location_id', 'left')
            ->join('warehouses tw', 'tw.id = h.to_warehouse_id', 'left')
            ->join('locations tl', 'tl.id = h.to_location_id', 'left')
            ->where('h.deleted_at', null)
            ->groupBy('h.id')
            ->orderBy('h.id', 'DESC');

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('h.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('h.site_id', $tenant->activeSiteId());
        }

        return view('inventory/transfers/index', [
            'title' => 'Inventory Transfers',
            'transfers' => $builder->get(50)->getResultArray(),
        ]);
    }

    public function create(): string
    {
        return view('inventory/transfers/create', $this->formData('New Inventory Transfer'));
    }

    public function show(int $id): string
    {
        $transfer = $this->transferHeader($id);
        $lines = Database::connect()->table('inventory_transfer_lines')
            ->where('header_id', $id)
            ->orderBy('line_no', 'ASC')
            ->get()
            ->getResultArray();

        return view('inventory/transfers/show', [
            'title' => 'Inventory Transfer ' . $transfer['transfer_no'],
            'transfer' => $transfer,
            'lines' => $lines,
        ]);
    }

    public function store()
    {
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();

        if ($companyId === null || $companyId < 1 || $siteId === null || $siteId < 1) {
            return redirect()->back()->withInput()->with('error', 'Active company and active site are required.');
        }

        $fromWarehouseId = $this->nullableInt($this->request->getPost('from_warehouse_id'));
        $fromLocationId = $this->nullableInt($this->request->getPost('from_location_id'));
        $toWarehouseId = $this->nullableInt($this->request->getPost('to_warehouse_id'));
        $toLocationId = $this->nullableInt($this->request->getPost('to_location_id'));

        if ($fromWarehouseId === $toWarehouseId && $fromLocationId === $toLocationId) {
            return redirect()->back()->withInput()->with('error', 'Source and destination cannot be the same.');
        }

        try {
            $lines = $this->postedLines($companyId);
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        if ($lines === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid item line is required.');
        }

        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $transferNo = trim((string) ($this->request->getPost('transfer_no') ?: $this->nextTransferNo()));
        $transferDate = trim((string) ($this->request->getPost('transfer_date') ?: date('Y-m-d')));

        $db->transBegin();

        try {
            $db->table('inventory_transfer_headers')->insert([
                'company_id' => $companyId,
                'site_id' => $siteId,
                'transfer_no' => $transferNo,
                'transfer_date' => $transferDate . ' 00:00:00',
                'from_warehouse_id' => $fromWarehouseId,
                'from_location_id' => $fromLocationId,
                'to_warehouse_id' => $toWarehouseId,
                'to_location_id' => $toLocationId,
                'status' => 'draft',
                'notes' => trim((string) $this->request->getPost('notes')),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $headerId = (int) $db->insertID();

            if ($headerId < 1) {
                throw new RuntimeException('Failed to create transfer header.');
            }

            foreach ($lines as $index => $line) {
                $db->table('inventory_transfer_lines')->insert([
                    'header_id' => $headerId,
                    'line_no' => $index + 1,
                    'item_id' => $line['item_id'],
                    'item_code' => $line['item_code'],
                    'batch_no' => $line['batch_no'] ?? '',
                    'item_name' => $line['item_name'],
                    'uom_code' => $line['uom_code'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'notes' => $line['notes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to save inventory transfer draft.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        $this->auditTransfer('transfer.create', [
            'id' => $headerId,
            'company_id' => $companyId,
            'site_id' => $siteId,
            'transfer_no' => $transferNo,
        ], [
            'status' => 'draft',
            'transfer_date' => $transferDate,
            'line_count' => count($lines),
            'total_qty' => array_sum(array_column($lines, 'qty')),
            'from_warehouse_id' => $fromWarehouseId,
            'from_location_id' => $fromLocationId,
            'to_warehouse_id' => $toWarehouseId,
            'to_location_id' => $toLocationId,
        ]);

        return redirect()->to('/inventory/transfers/' . $headerId)->with('message', 'Inventory transfer draft saved.');
    }

    public function submit(int $id)
    {
        try {
            $transfer = $this->transferHeader($id);
            if ((string) $transfer['status'] !== 'draft') {
                throw new RuntimeException('Only draft transfer can be submitted.');
            }

            $now = date('Y-m-d H:i:s');
            Database::connect()->table('inventory_transfer_headers')
                ->where('id', $id)
                ->update([
                    'status' => 'submitted',
                    'submitted_at' => $now,
                    'submitted_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'updated_at' => $now,
                ]);

            $this->auditTransfer('transfer.submit', $transfer, [
                'old_status' => $transfer['status'],
                'new_status' => 'submitted',
                'submitted_at' => $now,
                'submitted_by' => auth()->id(),
            ], [
                'status' => $transfer['status'],
            ]);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->to('/inventory/transfers/' . $id)->with('message', 'Inventory transfer submitted.');
    }

    public function post(int $id)
    {
        $db = Database::connect();
        $stock = new InventoryStockService();
        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            $transfer = $this->transferHeader($id);
            if (! in_array((string) $transfer['status'], ['draft', 'submitted'], true)) {
                throw new RuntimeException('Only draft or submitted transfer can be posted.');
            }
            $this->assertTransferPeriodOpen($transfer);

            $lines = $db->table('inventory_transfer_lines')
                ->where('header_id', $id)
                ->orderBy('line_no', 'ASC')
                ->get()
                ->getResultArray();

            if ($lines === []) {
                throw new RuntimeException('Cannot post transfer without lines.');
            }

            foreach ($lines as $line) {
                if (! empty($line['transfer_out_movement_id']) || ! empty($line['transfer_in_movement_id'])) {
                    throw new RuntimeException('Transfer line has already been posted.');
                }

                $referenceNo = $transfer['transfer_no'] . '-' . str_pad((string) $line['line_no'], 3, '0', STR_PAD_LEFT);
                $base = [
                    'company_id' => (int) $transfer['company_id'],
                    'site_id' => $transfer['site_id'] !== null ? (int) $transfer['site_id'] : null,
                    'warehouse_id' => $transfer['from_warehouse_id'] !== null ? (int) $transfer['from_warehouse_id'] : null,
                    'location_id' => $transfer['from_location_id'] !== null ? (int) $transfer['from_location_id'] : null,
                    'item_id' => $line['item_id'] !== null ? (int) $line['item_id'] : null,
                    'item_code' => $line['item_code'],
                    'batch_no' => $line['batch_no'] ?? '',
                    'item_name' => $line['item_name'],
                    'uom_code' => $line['uom_code'],
                    'qty' => (float) $line['qty'],
                    'unit_cost' => (float) $line['unit_cost'],
                    'movement_date' => $transfer['transfer_date'],
                    'reference_type' => 'inventory_transfer',
                    'reference_id' => $id,
                    'reference_no' => $referenceNo,
                    'notes' => trim((string) ($transfer['notes'] ?? '')),
                ];

                $outId = $stock->stockOut($base + [
                    'movement_type' => 'transfer_out',
                    'direction' => 'out',
                ], auth()->id());

                $inId = $stock->stockIn($base + [
                    'warehouse_id' => $transfer['to_warehouse_id'] !== null ? (int) $transfer['to_warehouse_id'] : null,
                    'location_id' => $transfer['to_location_id'] !== null ? (int) $transfer['to_location_id'] : null,
                    'movement_type' => 'transfer_in',
                    'direction' => 'in',
                ], auth()->id());

                $db->table('inventory_transfer_lines')
                    ->where('id', $line['id'])
                    ->update([
                        'transfer_out_movement_id' => $outId,
                        'transfer_in_movement_id' => $inId,
                        'updated_at' => $now,
                    ]);
            }

            $db->table('inventory_transfer_headers')
                ->where('id', $id)
                ->update([
                    'status' => 'posted',
                    'posted_at' => $now,
                    'posted_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'updated_at' => $now,
                ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to post inventory transfer.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            return redirect()->back()->with('error', $exception->getMessage());
        }

        $this->auditTransfer('transfer.post', $transfer, [
            'old_status' => $transfer['status'],
            'new_status' => 'posted',
            'posted_at' => $now,
            'posted_by' => auth()->id(),
            'line_count' => count($lines),
            'movement_count' => count($lines) * 2,
        ], [
            'status' => $transfer['status'],
        ]);

        return redirect()->to('/inventory/transfers/' . $id)->with('message', 'Inventory transfer posted.');
    }

    public function cancel(int $id)
    {
        try {
            $transfer = $this->transferHeader($id);
            if ((string) $transfer['status'] === 'posted') {
                throw new RuntimeException('Posted transfer cannot be cancelled from this screen. Create reversal transfer instead.');
            }
            if ((string) $transfer['status'] === 'cancelled') {
                throw new RuntimeException('Transfer is already cancelled.');
            }
            $this->assertTransferPeriodOpen($transfer);

            $now = date('Y-m-d H:i:s');
            $cancelReason = trim((string) $this->request->getPost('cancel_reason')) ?: null;
            Database::connect()->table('inventory_transfer_headers')
                ->where('id', $id)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $now,
                    'cancelled_by' => auth()->id(),
                    'cancel_reason' => $cancelReason,
                    'updated_by' => auth()->id(),
                    'updated_at' => $now,
                ]);

            $this->auditTransfer('transfer.cancel', $transfer, [
                'old_status' => $transfer['status'],
                'new_status' => 'cancelled',
                'cancelled_at' => $now,
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $cancelReason,
            ], [
                'status' => $transfer['status'],
            ]);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->to('/inventory/transfers/' . $id)->with('message', 'Inventory transfer cancelled.');
    }

    public function reverse(int $id)
    {
        $db = Database::connect();
        $stock = new InventoryStockService();
        $now = date('Y-m-d H:i:s');
        $reason = trim((string) $this->request->getPost('reversal_reason')) ?: null;

        $db->transBegin();

        try {
            $transfer = $this->transferHeader($id);
            if ((string) $transfer['status'] !== 'posted') {
                throw new RuntimeException('Only posted transfer can be reversed.');
            }
            $this->assertTransferPeriodOpen($transfer);

            $lines = $db->table('inventory_transfer_lines')
                ->where('header_id', $id)
                ->orderBy('line_no', 'ASC')
                ->get()
                ->getResultArray();

            if ($lines === []) {
                throw new RuntimeException('Cannot reverse transfer without lines.');
            }

            foreach ($lines as $line) {
                if (empty($line['transfer_out_movement_id']) || empty($line['transfer_in_movement_id'])) {
                    throw new RuntimeException('Transfer line is not fully posted.');
                }
                if (! empty($line['reversal_out_movement_id']) || ! empty($line['reversal_in_movement_id'])) {
                    throw new RuntimeException('Transfer line has already been reversed.');
                }

                $referenceNo = $transfer['transfer_no'] . '-REV-' . str_pad((string) $line['line_no'], 3, '0', STR_PAD_LEFT);
                $base = [
                    'company_id' => (int) $transfer['company_id'],
                    'site_id' => $transfer['site_id'] !== null ? (int) $transfer['site_id'] : null,
                    'warehouse_id' => $transfer['to_warehouse_id'] !== null ? (int) $transfer['to_warehouse_id'] : null,
                    'location_id' => $transfer['to_location_id'] !== null ? (int) $transfer['to_location_id'] : null,
                    'item_id' => $line['item_id'] !== null ? (int) $line['item_id'] : null,
                    'item_code' => $line['item_code'],
                    'batch_no' => $line['batch_no'],
                    'item_name' => $line['item_name'],
                    'uom_code' => $line['uom_code'],
                    'qty' => (float) $line['qty'],
                    'unit_cost' => (float) $line['unit_cost'],
                    'movement_date' => $transfer['transfer_date'] ?? $now,
                    'reference_type' => 'inventory_transfer_reversal',
                    'reference_id' => $id,
                    'reference_no' => $referenceNo,
                    'notes' => trim(($reason ?? '') . ' Reversal for transfer ' . $transfer['transfer_no']),
                ];

                $reversalOutId = $stock->stockOut($base + [
                    'movement_type' => 'transfer_reversal_out',
                    'direction' => 'out',
                ], auth()->id());

                $reversalInId = $stock->stockIn($base + [
                    'warehouse_id' => $transfer['from_warehouse_id'] !== null ? (int) $transfer['from_warehouse_id'] : null,
                    'location_id' => $transfer['from_location_id'] !== null ? (int) $transfer['from_location_id'] : null,
                    'movement_type' => 'transfer_reversal_in',
                    'direction' => 'in',
                ], auth()->id());

                $db->table('inventory_transfer_lines')
                    ->where('id', $line['id'])
                    ->update([
                        'reversal_out_movement_id' => $reversalOutId,
                        'reversal_in_movement_id' => $reversalInId,
                        'updated_at' => $now,
                    ]);
            }

            $db->table('inventory_transfer_headers')
                ->where('id', $id)
                ->update([
                    'status' => 'reversed',
                    'reversed_at' => $now,
                    'reversed_by' => auth()->id(),
                    'reversal_reason' => $reason,
                    'updated_by' => auth()->id(),
                    'updated_at' => $now,
                ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to reverse inventory transfer.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            return redirect()->back()->with('error', $exception->getMessage());
        }

        $this->auditTransfer('transfer.reverse', $transfer, [
            'old_status' => $transfer['status'],
            'new_status' => 'reversed',
            'reversed_at' => $now,
            'reversed_by' => auth()->id(),
            'reversal_reason' => $reason,
            'line_count' => count($lines),
            'movement_count' => count($lines) * 2,
        ], [
            'status' => $transfer['status'],
        ]);

        return redirect()->to('/inventory/transfers/' . $id)->with('message', 'Inventory transfer reversed.');
    }

    private function transferHeader(int $id): array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('inventory_transfer_headers h')
            ->select('h.*, fw.code from_warehouse_code, fw.name from_warehouse_name, fl.code from_location_code, fl.name from_location_name, tw.code to_warehouse_code, tw.name to_warehouse_name, tl.code to_location_code, tl.name to_location_name')
            ->join('warehouses fw', 'fw.id = h.from_warehouse_id', 'left')
            ->join('locations fl', 'fl.id = h.from_location_id', 'left')
            ->join('warehouses tw', 'tw.id = h.to_warehouse_id', 'left')
            ->join('locations tl', 'tl.id = h.to_location_id', 'left')
            ->where('h.id', $id)
            ->where('h.deleted_at', null);

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('h.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('h.site_id', $tenant->activeSiteId());
        }

        $transfer = $builder->get()->getRowArray();
        if ($transfer === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Inventory transfer not found.');
        }

        return $transfer;
    }

    private function postedLines(int $companyId): array
    {
        $itemCodes = (array) $this->request->getPost('item_code');
        $batchNos = (array) $this->request->getPost('batch_no');
        $qtys = (array) $this->request->getPost('qty');
        $uoms = (array) $this->request->getPost('uom_code');
        $unitCosts = (array) $this->request->getPost('unit_cost');
        $notes = (array) $this->request->getPost('line_notes');
        $lines = [];

        foreach ($itemCodes as $index => $itemCode) {
            $itemCode = trim((string) $itemCode);
            $qty = (float) ($qtys[$index] ?? 0);
            if ($itemCode === '' || $qty <= 0) {
                continue;
            }

            $item = $this->itemByCode($itemCode, $companyId);
            if ($item === null) {
                throw new RuntimeException("Item code {$itemCode} was not found.");
            }

            $lines[] = [
                'item_id' => isset($item['id']) ? (int) $item['id'] : null,
                'item_code' => $item['item_code'] ?? $item['code'] ?? $itemCode,
                'batch_no' => trim((string) ($batchNos[$index] ?? '')),
                'item_name' => $item['item_name'] ?? $item['name'] ?? $itemCode,
                'uom_code' => trim((string) ($uoms[$index] ?? ($item['stockuom'] ?? 'PCS'))) ?: 'PCS',
                'qty' => $qty,
                'unit_cost' => (float) ($unitCosts[$index] ?? ($item['item_price'] ?? 0)),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $lines;
    }

    private function formData(string $title): array
    {
        return [
            'title' => $title,
            'transferNo' => $this->nextTransferNo(),
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

    private function itemByCode(string $code, int $companyId): ?array
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('items')
            ->where('company_id', $companyId)
            ->groupStart()
                ->where('item_code', $code)
                ->orWhere('code', $code)
            ->groupEnd();

        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', 'items')) {
            $builder->where('site_id', $tenant->activeSiteId());
        }

        return $builder->get()->getRowArray();
    }

    private function nextTransferNo(): string
    {
        return 'TRF-' . date('Ymd-His');
    }

    /**
     * @param array<string, mixed> $transfer
     * @param array<string, mixed> $newValues
     * @param array<string, mixed>|null $oldValues
     */
    private function auditTransfer(string $action, array $transfer, array $newValues, ?array $oldValues = null): void
    {
        (new AuditLogService())->log('inventory.transfer', $action, [
            'company_id' => $transfer['company_id'] ?? null,
            'site_id' => $transfer['site_id'] ?? null,
            'table_name' => 'inventory_transfer_headers',
            'record_id' => $transfer['id'] ?? null,
            'record_code' => $transfer['transfer_no'] ?? null,
            'description' => $this->auditDescription($action, (string) ($transfer['transfer_no'] ?? '-')),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    private function assertTransferPeriodOpen(array $transfer): void
    {
        (new PeriodCloseService())->assertOpen(
            'inventory',
            (int) ($transfer['company_id'] ?? 0),
            (string) ($transfer['transfer_date'] ?? date('Y-m-d')),
            ! empty($transfer['site_id']) ? (int) $transfer['site_id'] : null
        );
    }

    private function auditDescription(string $action, string $transferNo): string
    {
        return match ($action) {
            'transfer.create' => "Inventory transfer {$transferNo} draft created.",
            'transfer.submit' => "Inventory transfer {$transferNo} submitted.",
            'transfer.post' => "Inventory transfer {$transferNo} posted and stock moved.",
            'transfer.cancel' => "Inventory transfer {$transferNo} cancelled.",
            'transfer.reverse' => "Inventory transfer {$transferNo} reversed.",
            default => "Inventory transfer {$transferNo} updated.",
        };
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
