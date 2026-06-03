<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\TenantContext;
use Config\Database;

class StockBalanceController extends BaseController
{
    public function index(): string
    {
        $tenant = new TenantContext(session());
        $db = Database::connect();
        $builder = $db->table('inventory_stock_balances b')
            ->select('b.*, w.code AS warehouse_code, w.name AS warehouse_name, l.code AS location_code, l.name AS location_name')
            ->join('warehouses w', 'w.id = b.warehouse_id', 'left')
            ->join('locations l', 'l.id = b.location_id', 'left');

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('b.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('b.site_id', $tenant->activeSiteId());
        }

        $keyword = trim((string) $this->request->getGet('q'));
        if ($keyword !== '') {
            $builder->groupStart()
                ->like('b.item_code', $keyword)
                ->orLike('w.code', $keyword)
                ->orLike('w.name', $keyword)
                ->orLike('l.code', $keyword)
                ->orLike('l.name', $keyword)
                ->groupEnd();
        }

        $balances = $builder
            ->orderBy('b.item_code', 'ASC')
            ->orderBy('w.code', 'ASC')
            ->orderBy('l.code', 'ASC')
            ->get(200)
            ->getResultArray();

        return view('inventory/stock_balances/index', [
            'title' => 'Stock Balance',
            'balances' => $balances,
            'keyword' => $keyword,
            'summary' => $this->summary($balances),
        ]);
    }

    private function summary(array $balances): array
    {
        $items = [];
        $onHand = 0.0;
        $reserved = 0.0;
        $available = 0.0;
        $value = 0.0;

        foreach ($balances as $row) {
            $items[$row['item_code']] = true;
            $onHand += (float) ($row['qty_on_hand'] ?? 0);
            $reserved += (float) ($row['qty_reserved'] ?? 0);
            $available += (float) ($row['qty_available'] ?? 0);
            $value += (float) ($row['stock_value'] ?? 0);
        }

        return [
            'item_count' => count($items),
            'qty_on_hand' => $onHand,
            'qty_reserved' => $reserved,
            'qty_available' => $available,
            'stock_value' => $value,
        ];
    }
}
