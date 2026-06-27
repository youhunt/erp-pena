<?php

namespace App\Controllers;

use App\Services\TenantContext;
use Config\Database;

class ModulePlaceholderController extends BaseController
{
    public function show(string $slug): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $slug = strtolower(trim($slug));

        if ($slug === 'forecast') {
            return redirect()->to('/production/forecasts');
        }
        if ($slug === 'mrp') {
            return redirect()->to('/production/mrp');
        }
        if ($slug === 'planned-released') {
            return $this->plannedReleased();
        }
        if ($slug === 'mps') {
            return $this->mps();
        }
        if ($slug === 'cost-type') {
            return $this->costTypes();
        }
        if ($slug === 'item-cost') {
            return $this->itemCosts();
        }
        if ($slug === 'calculate-cost') {
            return $this->calculateCost();
        }
        if ($slug === 'cost-purchase-receipt') {
            return $this->costPurchaseReceipt();
        }

        $title = $this->titleFromSlug($slug);

        return view('modules/placeholder', [
            'title' => $title,
            'slug' => $slug,
        ]);
    }

    public function mpsPage(): string
    {
        return $this->mps();
    }

    public function plannedReleasedPage(): string
    {
        return $this->plannedReleased();
    }

    private function costPurchaseReceipt(): string
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();

        if (! $db->tableExists('purchase_receipts') || ! $db->tableExists('purchase_receipt_lines')) {
            return view('purchase/cost_receipts/index', [
                'title' => 'Cost Purchase Receipt',
                'rows' => [],
                'hasTable' => false,
                'summary' => [
                    'receipts' => 0,
                    'qty' => 0,
                    'amount' => 0,
                    'invoiced' => 0,
                ],
            ]);
        }

        $builder = $db->table('purchase_receipts pr')
            ->select([
                'pr.id',
                'pr.receipt_no',
                'pr.receipt_date',
                'pr.po_no',
                'pr.supplier_code',
                'pr.supplier_name',
                'pr.status',
                'pr.gl_entry_id',
                'pr.reversal_gl_entry_id',
                'pr.posted_at',
                'pr.reversed_at',
                'COUNT(prl.id) AS line_count',
                'COALESCE(SUM(prl.qty_received), 0) AS total_qty',
                'COALESCE(SUM(prl.qty_received * prl.unit_cost), 0) AS receipt_amount',
                'GROUP_CONCAT(DISTINCT prl.item_code ORDER BY prl.item_code SEPARATOR ", ") AS item_codes',
            ])
            ->join('purchase_receipt_lines prl', 'prl.purchase_receipt_id = pr.id', 'left')
            ->where('pr.deleted_at', null)
            ->groupBy('pr.id');

        if ($companyId !== null && $db->fieldExists('company_id', 'purchase_receipts')) {
            $builder->where('pr.company_id', $companyId);
        }
        if ($siteId !== null && $db->fieldExists('site_id', 'purchase_receipts')) {
            $builder->where('pr.site_id', $siteId);
        }

        $status = trim((string) $this->request->getGet('status'));
        if ($status !== '') {
            $builder->where('pr.status', $status);
        }
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $builder->groupStart()
                ->like('pr.receipt_no', $q)
                ->orLike('pr.po_no', $q)
                ->orLike('pr.supplier_code', $q)
                ->orLike('pr.supplier_name', $q)
                ->orLike('prl.item_code', $q)
                ->groupEnd();
        }

        $rows = $builder
            ->orderBy('pr.receipt_date', 'DESC')
            ->orderBy('pr.id', 'DESC')
            ->get(300)
            ->getResultArray();

        $invoicedReceiptIds = [];
        if ($db->tableExists('purchase_invoices') && $rows !== []) {
            $receiptNos = array_values(array_filter(array_column($rows, 'receipt_no')));
            if ($receiptNos !== []) {
                $invoiceRows = $db->table('purchase_invoices')
                    ->select('receipt_no')
                    ->whereIn('receipt_no', $receiptNos)
                    ->where('deleted_at', null)
                    ->get(500)
                    ->getResultArray();
                foreach ($invoiceRows as $invoiceRow) {
                    $invoicedReceiptIds[(string) ($invoiceRow['receipt_no'] ?? '')] = true;
                }
            }
        }

        $summary = [
            'receipts' => count($rows),
            'qty' => 0.0,
            'amount' => 0.0,
            'invoiced' => 0,
        ];

        foreach ($rows as &$row) {
            $row['is_invoiced'] = isset($invoicedReceiptIds[(string) ($row['receipt_no'] ?? '')]);
            $summary['qty'] += (float) ($row['total_qty'] ?? 0);
            $summary['amount'] += (float) ($row['receipt_amount'] ?? 0);
            if ($row['is_invoiced']) {
                $summary['invoiced']++;
            }
        }
        unset($row);

        return view('purchase/cost_receipts/index', [
            'title' => 'Cost Purchase Receipt',
            'rows' => $rows,
            'hasTable' => true,
            'summary' => $summary,
            'q' => $q,
            'status' => $status,
        ]);
    }

    private function costTypes(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();

        if (! $db->tableExists('costing_cost_types')) {
            return view('costing/cost_types/index', [
                'title' => 'Cost Type',
                'rows' => [],
                'hasTable' => false,
            ]);
        }

        $action = (string) $this->request->getGet('action');
        if ($action === 'save') {
            $type = trim((string) $this->request->getGet('type'));
            $description = trim((string) $this->request->getGet('description'));
            $group = trim((string) $this->request->getGet('cost_group')) ?: 'Material';
            $id = (int) ($this->request->getGet('id') ?? 0);

            if ($type === '' || ! in_array($group, ['Material', 'Labor', 'Overhead'], true)) {
                return redirect()->to('/modules/cost-type')->with('error', 'Cost Type and Cost Group are required.');
            }

            $payload = [
                'company_id' => $companyId,
                'type' => $type,
                'description' => $description,
                'cost_group' => $group,
                'is_active' => 1,
                'updated_by' => auth()->id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($id > 0) {
                $db->table('costing_cost_types')->where('id', $id)->update($payload);
            } else {
                $payload['created_by'] = auth()->id();
                $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('costing_cost_types')->insert($payload);
            }
            return redirect()->to('/modules/cost-type')->with('message', 'Cost Type saved.');
        }

        if ($action === 'delete') {
            $id = (int) ($this->request->getGet('id') ?? 0);
            if ($id > 0) {
                $db->table('costing_cost_types')->where('id', $id)->update([
                    'is_active' => 0,
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'updated_by' => auth()->id(),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            return redirect()->to('/modules/cost-type')->with('message', 'Cost Type disabled.');
        }

        $builder = $db->table('costing_cost_types')->where('deleted_at', null);
        if ($companyId !== null) {
            $builder->groupStart()->where('company_id', $companyId)->orWhere('company_id', null)->groupEnd();
        }

        return view('costing/cost_types/index', [
            'title' => 'Cost Type',
            'rows' => $builder->orderBy('cost_group', 'ASC')->orderBy('type', 'ASC')->get(500)->getResultArray(),
            'hasTable' => true,
        ]);
    }

    private function itemCosts(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();

        if (! $db->tableExists('costing_item_costs')) {
            return view('costing/item_costs/index', [
                'title' => 'Item Cost',
                'rows' => [],
                'hasTable' => false,
            ]);
        }

        if ((string) $this->request->getGet('action') === 'save') {
            $itemCode = trim((string) $this->request->getGet('item_code'));
            if ($itemCode === '') {
                return redirect()->to('/modules/item-cost')->with('error', 'Item Code is required.');
            }

            $payload = [
                'company_id' => $companyId,
                'site_id' => $siteId,
                'site_code' => trim((string) $this->request->getGet('site_code')),
                'item_code' => $itemCode,
                'item_name' => trim((string) $this->request->getGet('item_name')),
                'department_code' => trim((string) $this->request->getGet('department_code')),
                'warehouse_code' => trim((string) $this->request->getGet('warehouse_code')),
                'description' => trim((string) $this->request->getGet('description')),
                'this_item_cost' => (float) ($this->request->getGet('this_item_cost') ?: 0),
                'updated_by' => auth()->id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $existing = $db->table('costing_item_costs')
                ->where('company_id', $companyId)
                ->where('site_id', $siteId)
                ->where('item_code', $itemCode)
                ->where('department_code', $payload['department_code'])
                ->where('warehouse_code', $payload['warehouse_code'])
                ->where('deleted_at', null)
                ->get(1)->getRowArray();

            if ($existing !== null) {
                $db->table('costing_item_costs')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $payload['status'] = 'draft';
                $payload['created_by'] = auth()->id();
                $payload['created_at'] = date('Y-m-d H:i:s');
                $db->table('costing_item_costs')->insert($payload);
            }

            return redirect()->to('/modules/item-cost')->with('message', 'Item Cost saved.');
        }

        $builder = $db->table('costing_item_costs')->where('deleted_at', null);
        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null) {
            $builder->where('site_id', $siteId);
        }

        return view('costing/item_costs/index', [
            'title' => 'Item Cost',
            'rows' => $builder->orderBy('item_code', 'ASC')->get(500)->getResultArray(),
            'hasTable' => true,
        ]);
    }

    private function calculateCost(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();
        $itemCode = trim((string) $this->request->getGet('item_code'));
        $calculation = ['rows' => [], 'total_cost' => 0.0, 'this_item_cost' => 0.0, 'bom_cost' => 0.0];

        if ($itemCode !== '') {
            $calculation = $this->calculateBomCost($db, $companyId, $siteId, $itemCode);
        }

        if ((string) $this->request->getGet('action') === 'save' && $itemCode !== '') {
            if (! $db->tableExists('costing_item_costs') || ! $db->tableExists('costing_item_cost_lines')) {
                return redirect()->to('/modules/calculate-cost?item_code=' . rawurlencode($itemCode))->with('error', 'Run costing SQL installer first.');
            }
            $this->saveCalculation($db, $companyId, $siteId, $itemCode, $calculation);
            return redirect()->to('/modules/item-cost')->with('message', 'Calculated cost saved to Item Cost.');
        }

        return view('costing/calculate/index', [
            'title' => 'Calculate Cost',
            'itemCode' => $itemCode,
            'calculation' => $calculation,
            'hasTable' => $db->tableExists('costing_item_costs') && $db->tableExists('costing_item_cost_lines'),
        ]);
    }

    private function calculateBomCost($db, ?int $companyId, ?int $siteId, string $itemCode, int $depth = 0, array &$visited = []): array
    {
        $key = strtoupper($itemCode);
        if (isset($visited[$key])) {
            return ['rows' => [], 'total_cost' => 0.0, 'this_item_cost' => 0.0, 'bom_cost' => 0.0, 'warning' => 'Loop BOM detected'];
        }
        $visited[$key] = true;
        $thisCost = $this->baseItemCost($db, $companyId, $siteId, $itemCode);
        $rows = [];
        $bomCost = 0.0;
        $bom = $this->activeBom($db, $companyId, $siteId, $itemCode);
        if ($bom !== null) {
            $lines = $db->table('production_bom_lines')
                ->where('production_bom_id', (int) $bom['id'])
                ->orderBy('child_no', 'ASC')
                ->get(500)
                ->getResultArray();
            foreach ($lines as $line) {
                $childCode = (string) ($line['child_item_code'] ?? '');
                $childCalc = $this->calculateBomCost($db, $companyId, $siteId, $childCode, $depth + 1, $visited);
                $qtyUsed = (float) ($line['qty_used'] ?? 1);
                $factor = (float) ($line['factor'] ?? 1);
                if ($factor == 0.0) {
                    $factor = 1.0;
                }
                $lineTotal = (float) ($childCalc['total_cost'] ?? 0) * $qtyUsed * $factor;
                $bomCost += $lineTotal;
                $rows[] = [
                    'depth' => $depth + 1,
                    'bom_no' => (string) ($bom['parent_item_code'] ?? $itemCode),
                    'item_code' => $childCode,
                    'item_name' => (string) ($line['child_item_name'] ?? ''),
                    'qty_batch' => (float) ($bom['qty_batch'] ?? 1),
                    'qty_used' => $qtyUsed,
                    'uom_code' => (string) ($line['uom_code'] ?? ''),
                    'ratio_percent' => (float) ($bom['ratio_percent'] ?? 100),
                    'factor' => $factor,
                    'this_item_cost' => (float) ($childCalc['this_item_cost'] ?? 0),
                    'bom_cost' => (float) ($childCalc['bom_cost'] ?? 0),
                    'total_cost' => $lineTotal,
                    'notes' => (string) ($line['component_type'] ?? ''),
                ];
                foreach (($childCalc['rows'] ?? []) as $childRow) {
                    $rows[] = $childRow;
                }
            }
        }
        unset($visited[$key]);
        return [
            'rows' => $rows,
            'total_cost' => $thisCost + $bomCost,
            'this_item_cost' => $thisCost,
            'bom_cost' => $bomCost,
            'work_center_cost' => 0.0,
        ];
    }

    private function activeBom($db, ?int $companyId, ?int $siteId, string $itemCode): ?array
    {
        if ($itemCode === '' || ! $db->tableExists('production_boms')) {
            return null;
        }
        $builder = $db->table('production_boms')->where('parent_item_code', $itemCode);
        if ($companyId !== null && $db->fieldExists('company_id', 'production_boms')) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $db->fieldExists('site_id', 'production_boms')) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if ($db->fieldExists('is_active', 'production_boms')) {
            $builder->where('is_active', 1);
        }
        if ($db->fieldExists('deleted_at', 'production_boms')) {
            $builder->where('deleted_at', null);
        }
        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    private function baseItemCost($db, ?int $companyId, ?int $siteId, string $itemCode): float
    {
        if (! $db->tableExists('costing_item_costs')) {
            return 0.0;
        }
        $builder = $db->table('costing_item_costs')->where('item_code', $itemCode)->where('deleted_at', null);
        if ($companyId !== null && $db->fieldExists('company_id', 'costing_item_costs')) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $db->fieldExists('site_id', 'costing_item_costs')) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        $row = $builder->orderBy('id', 'DESC')->get(1)->getRowArray();
        return (float) ($row['this_item_cost'] ?? 0);
    }

    private function saveCalculation($db, ?int $companyId, ?int $siteId, string $itemCode, array $calculation): void
    {
        if (! $db->tableExists('costing_item_costs') || ! $db->tableExists('costing_item_cost_lines')) {
            return;
        }
        $db->transStart();
        $header = [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'site_code' => '',
            'item_code' => $itemCode,
            'item_name' => '',
            'department_code' => '',
            'warehouse_code' => '',
            'description' => 'Calculated from BOM',
            'this_item_cost' => (float) ($calculation['total_cost'] ?? 0),
            'status' => 'calculated',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db->table('costing_item_costs')->insert($header);
        $costId = (int) $db->insertID();
        foreach (($calculation['rows'] ?? []) as $index => $row) {
            $db->table('costing_item_cost_lines')->insert([
                'costing_item_cost_id' => $costId,
                'line_no' => $index + 1,
                'source_type' => 'bom',
                'source_no' => (string) ($row['bom_no'] ?? ''),
                'cost_type' => 'Material',
                'item_code' => (string) ($row['item_code'] ?? ''),
                'item_name' => (string) ($row['item_name'] ?? ''),
                'qty' => (float) ($row['qty_used'] ?? 0),
                'uom_code' => (string) ($row['uom_code'] ?? ''),
                'unit_cost' => (float) ($row['this_item_cost'] ?? 0),
                'amount' => (float) ($row['total_cost'] ?? 0),
                'notes' => (string) ($row['notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $db->transComplete();
    }

    private function mps(): string
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();
        $rows = [];
        if ($db->tableExists('production_forecasts')) {
            $builder = $db->table('production_forecasts');
            if ($companyId !== null) {
                $builder->where('company_id', $companyId);
            }
            if ($siteId !== null) {
                $builder->where('site_id', $siteId);
            }
            $rows = $builder->orderBy('period_month', 'DESC')->orderBy('item_code', 'ASC')->get(300)->getResultArray();
        }
        return view('production/mps/index', [
            'title' => 'Master Production Schedule',
            'rows' => $rows,
        ]);
    }

    private function plannedReleased(): string
    {
        $db = Database::connect();
        $tenant = new TenantContext(session());
        $companyId = $tenant->activeCompanyId();
        $siteId = $tenant->activeSiteId();
        $rows = [];
        if ($db->tableExists('production_mrp_planned_orders')) {
            $builder = $db->table('production_mrp_planned_orders po')
                ->select('po.*, r.run_no, r.run_date')
                ->join('production_mrp_runs r', 'r.id = po.production_mrp_run_id', 'left');
        }
        return view('production/planned_released/index', [
            'title' => 'Planned Released Order',
            'rows' => $rows,
        ]);
    }

    private function titleFromSlug(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }
}
