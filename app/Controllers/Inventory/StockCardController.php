<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;

class StockCardController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $filters = $this->filters();
        $opening = $this->openingBalance($tenant, $filters);
        $movements = $this->movements($tenant, $filters);

        return view('inventory/stock_cards/index', [
            'title' => 'Stock Card',
            'filters' => $filters,
            'opening' => $opening,
            'movements' => $this->withRunningBalance($movements, $opening),
            'items' => $this->itemOptions($tenant),
            'warehouses' => $this->masterRows('warehouses', $tenant),
            'locations' => $this->masterRows('locations', $tenant),
            'summary' => $this->summary($movements, $opening),
        ]);
    }

    /**
     * @return array{item_code: string, warehouse_id: ?int, location_id: ?int, date_from: string, date_to: string}
     */
    private function filters(): array
    {
        $dateFrom = trim((string) ($this->request->getGet('date_from') ?: date('Y-m-01')));
        $dateTo = trim((string) ($this->request->getGet('date_to') ?: date('Y-m-d')));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }

        return [
            'item_code' => trim((string) $this->request->getGet('item_code')),
            'warehouse_id' => $this->nullableInt($this->request->getGet('warehouse_id')),
            'location_id' => $this->nullableInt($this->request->getGet('location_id')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function openingBalance(TenantContext $tenant, array $filters): array
    {
        $builder = $this->movementBuilder($tenant, $filters, false)
            ->select("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END), 0) qty")
            ->select("COALESCE(SUM(CASE WHEN direction = 'in' THEN stock_value ELSE -stock_value END), 0) stock_value")
            ->where('movement_date <', $filters['date_from'] . ' 00:00:00');

        $row = $builder->get()->getRowArray() ?? [];

        return [
            'qty' => (float) ($row['qty'] ?? 0),
            'stock_value' => (float) ($row['stock_value'] ?? 0),
        ];
    }

    private function movements(TenantContext $tenant, array $filters): array
    {
        return $this->movementBuilder($tenant, $filters)
            ->select('m.*, w.code warehouse_code, l.code location_code')
            ->join('warehouses w', 'w.id = m.warehouse_id', 'left')
            ->join('locations l', 'l.id = m.location_id', 'left')
            ->where('m.movement_date >=', $filters['date_from'] . ' 00:00:00')
            ->where('m.movement_date <=', $filters['date_to'] . ' 23:59:59')
            ->orderBy('m.movement_date', 'ASC')
            ->orderBy('m.id', 'ASC')
            ->get(500)
            ->getResultArray();
    }

    private function movementBuilder(TenantContext $tenant, array $filters, bool $aliased = true)
    {
        $db = Database::connect();
        $table = $aliased ? 'inventory_stock_movements m' : 'inventory_stock_movements';
        $prefix = $aliased ? 'm.' : '';
        $builder = $db->table($table);

        if ($tenant->activeCompanyId() !== null) {
            $builder->where($prefix . 'company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where($prefix . 'site_id', $tenant->activeSiteId());
        }
        if ($filters['item_code'] !== '') {
            $builder->where($prefix . 'item_code', $filters['item_code']);
        }
        if ($filters['warehouse_id'] !== null) {
            $builder->where($prefix . 'warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['location_id'] !== null) {
            $builder->where($prefix . 'location_id', $filters['location_id']);
        }

        return $builder;
    }

    private function withRunningBalance(array $movements, array $opening): array
    {
        $qtyBalance = (float) $opening['qty'];
        $valueBalance = (float) $opening['stock_value'];

        foreach ($movements as &$movement) {
            $qty = (float) ($movement['qty'] ?? 0);
            $value = (float) ($movement['stock_value'] ?? 0);
            $isIn = (string) ($movement['direction'] ?? '') === 'in';
            $movement['qty_in'] = $isIn ? $qty : 0;
            $movement['qty_out'] = $isIn ? 0 : $qty;
            $movement['value_in'] = $isIn ? $value : 0;
            $movement['value_out'] = $isIn ? 0 : $value;
            $qtyBalance += $isIn ? $qty : -$qty;
            $valueBalance += $isIn ? $value : -$value;
            $movement['running_qty'] = $qtyBalance;
            $movement['running_value'] = $valueBalance;
        }

        return $movements;
    }

    private function summary(array $movements, array $opening): array
    {
        $qtyIn = 0.0;
        $qtyOut = 0.0;
        $valueIn = 0.0;
        $valueOut = 0.0;

        foreach ($movements as $movement) {
            $isIn = (string) ($movement['direction'] ?? '') === 'in';
            $qty = (float) ($movement['qty'] ?? 0);
            $value = (float) ($movement['stock_value'] ?? 0);
            $qtyIn += $isIn ? $qty : 0;
            $qtyOut += $isIn ? 0 : $qty;
            $valueIn += $isIn ? $value : 0;
            $valueOut += $isIn ? 0 : $value;
        }

        return [
            'opening_qty' => (float) $opening['qty'],
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'ending_qty' => (float) $opening['qty'] + $qtyIn - $qtyOut,
            'opening_value' => (float) $opening['stock_value'],
            'value_in' => $valueIn,
            'value_out' => $valueOut,
            'ending_value' => (float) $opening['stock_value'] + $valueIn - $valueOut,
        ];
    }

    private function itemOptions(TenantContext $tenant): array
    {
        $builder = Database::connect()->table('inventory_stock_movements')
            ->select('item_code, MAX(item_name) item_name')
            ->groupBy('item_code')
            ->orderBy('item_code', 'ASC');

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('site_id', $tenant->activeSiteId());
        }

        return $builder->get(500)->getResultArray();
    }

    private function masterRows(string $table, TenantContext $tenant): array
    {
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

        return $builder->orderBy($db->fieldExists('code', $table) ? 'code' : 'id', 'ASC')->get(500)->getResultArray();
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
