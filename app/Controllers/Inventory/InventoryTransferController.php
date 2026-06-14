<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
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

        $lines = $db->table('inventory_transfer_lines')
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

        $lines = $this->postedLines($companyId);
        if ($lines === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid item line is required.');
        }

        $db = Database::connect();
        $stock = new InventoryStockService();
        $now = date('Y-m-d H:i:s');
        $transferNo = trim((string) ($this->request->getPost('transfer_no') ?: $this->nextTransferNo()));
        $transferDate = trim((string) ($this->request->getPost('transfer_date') ?: date('Y-m-d')));

        $db->transBegin();

        try {
            $headerId = (int) $db->table('inventory_transfer_headers')->insert([
                'company_id' => $companyId,
                'site_id' => $siteId,
                'transfer_no' => $transferNo,
                'transfer_date' => $transferDate . ' 00:00:00',
                'from_warehouse_id' => $fromWarehouseId,
                'from_location_id' => $fromLocationId,
                'to_warehouse_id' => $toWarehouseId,
                'to_location_id' => $toLocationId,
                'status' => 'posted',
                'notes' => trim((string) $this->request->getPost('notes')),
                'posted_at' => $now,
                'posted_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ], true);

            foreach ($lines as $index => $line) {
                $referenceNo = $transferNo . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                $base = [
                    'company_id' => $companyId,
                    'site_id' => $siteId,
                    'warehouse_id' => $fromWarehouseId,
                    'location_id' => $fromLocationId,
                    'item_id' => $line['item_id'],
                    'item_code' => $line['item_code'],
                    'item_name' => $line['item_name'],
                    'uom_code' => $line['uom_code'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'movement_date' => $transferDate . ' 00:00:00',
                    'reference_type' => 'inventory_transfer',
                    'reference_id' => $headerId,
                    'reference_no' => $referenceNo,
                    'notes' => trim((string) $this->request->getPost('notes')),
                ];

                $outId = $stock->stockOut($base + [
                    'movement_type' => 'transfer_out',
                    'direction' => 'out',
                ], auth()->id());

                $inId = $stock->stockIn($base + [
                    'warehouse_id' => $toWarehouseId,
                    'location_id' => $toLocationId,
                    'movement_type' => 'transfer_in',
                    'direction' => 'in',
                ], auth()->id());

                $db->table('inventory_transfer_lines')->insert([
                    'header_id' => $headerId,
                    'line_no' => $index + 1,
                    'item_id' => $line['item_id'],
                    'item_code' => $line['item_code'],
                    'item_name' => $line['item_name'],
                    'uom_code' => $line['uom_code'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'transfer_out_movement_id' => $outId,
                    'transfer_in_movement_id' => $inId,
                    'notes' => $line['notes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Failed to save inventory transfer document.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to('/inventory/transfers/' . $headerId)->with('message', 'Inventory transfer posted.');
    }

    private function postedLines(int $companyId): array
    {
        $itemCodes = (array) $this->request->getPost('item_code');
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

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
