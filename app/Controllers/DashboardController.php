<?php

namespace App\Controllers;

use App\Services\TenantContext;
use Config\Database;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $user = auth()->user();
        $tenant = new TenantContext(session());
        $hasTenantAccess = true;
        $recentActivities = [];

        if ($user !== null) {
            $tenant->bootstrapDefaultsForUser((int) $user->id);
            $hasTenantAccess = $tenant->accessibleCompanies((int) $user->id) !== [] && ($tenant->activeCompanyId() ?? 0) > 0;
        }

        $metrics = [
            'Total Sales' => 0,
            'Total Purchase' => 0,
            'Total Invoice' => 0,
            'Pending Approval' => 0,
            'Pending OCR Review' => 0,
            'Stock Alert' => 0,
        ];
        $metricLinks = [
            'Total Sales' => 'sales/orders',
            'Total Purchase' => 'purchase/orders',
            'Total Invoice' => 'ar/sales-invoices',
            'Pending Approval' => 'dashboard',
            'Pending OCR Review' => 'ai-documents',
            'Stock Alert' => 'inventory/stock-alerts',
        ];
        $metricMoney = ['Total Sales', 'Total Purchase', 'Total Invoice'];
        $pendingWork = [];
        $workflowQueues = [];
        $financialSnapshot = [];
        $agingShortcuts = [];
        $topPendingInvoices = [];
        $topStockAlerts = [];
        $glUnbalancedEntries = [];
        $monthlyTrend = [];

        if ($hasTenantAccess) {
            $db = Database::connect();
            $builder = $db->table('audit_logs')
                ->orderBy('created_at', 'DESC')
                ->limit(8);

            if ($tenant->activeCompanyId() !== null && $tenant->activeCompanyId() > 0) {
                $builder->groupStart()
                    ->where('company_id', $tenant->activeCompanyId())
                    ->orWhere('company_id', null)
                    ->groupEnd();
            }

            if ($tenant->activeSiteId() !== null && $tenant->activeSiteId() > 0) {
                $builder->groupStart()
                    ->where('site_id', $tenant->activeSiteId())
                    ->orWhere('site_id', null)
                    ->groupEnd();
            }

            $recentActivities = $builder->get()->getResultArray();
            $metrics['Total Sales'] = $this->sumTenantAmount('sales_orders', 'total_amount', $tenant, ['cancelled']);
            $metrics['Total Purchase'] = $this->sumTenantAmount('purchase_orders', 'total_amount', $tenant, ['cancelled']);
            $metrics['Total Invoice'] = $this->sumTenantAmount('sales_invoices', 'total_amount', $tenant)
                + $this->sumTenantAmount('purchase_invoices', 'total_amount', $tenant);
            $metrics['Pending Approval'] = $this->countTenantRows('sales_orders', $tenant, ['document_status' => 'submitted'])
                + $this->countTenantRows('purchase_orders', $tenant, ['document_status' => 'submitted']);
            $metrics['Pending OCR Review'] = $this->countOcrPendingReview($tenant);
            $metrics['Stock Alert'] = $this->countStockAlerts($tenant);

            $financialSnapshot = [
                [
                    'label' => 'AR Outstanding',
                    'value' => $this->sumOpenAmount('sales_invoices', $tenant),
                    'route' => 'ar/aging',
                    'icon' => 'bx bx-trending-up',
                    'tone' => 'primary',
                    'money' => true,
                ],
                [
                    'label' => 'AP Outstanding',
                    'value' => $this->sumOpenAmount('purchase_invoices', $tenant),
                    'route' => 'ap/aging',
                    'icon' => 'bx bx-trending-down',
                    'tone' => 'danger',
                    'money' => true,
                ],
                [
                    'label' => 'Cash/Bank Balance',
                    'value' => $this->sumCashBankBalance($tenant),
                    'route' => 'cash-bank/accounts',
                    'icon' => 'bx bx-wallet',
                    'tone' => 'success',
                    'money' => true,
                ],
                [
                    'label' => 'Inventory Value',
                    'value' => $this->sumInventoryValue($tenant),
                    'route' => 'inventory/stock-balances',
                    'icon' => 'bx bx-package',
                    'tone' => 'info',
                    'money' => true,
                ],
                [
                    'label' => 'Unbalanced GL',
                    'value' => $this->countUnbalancedGlEntries($tenant),
                    'route' => 'gl/entries',
                    'icon' => 'bx bx-error-circle',
                    'tone' => 'warning',
                    'money' => false,
                ],
            ];

            $agingShortcuts = [
                [
                    'label' => 'AR Aging',
                    'description' => 'Open customer receivables ready for collection review',
                    'amount' => $this->sumOpenAmount('sales_invoices', $tenant),
                    'count' => $this->countOpenInvoices('sales_invoices', $tenant),
                    'route' => 'ar/aging',
                    'icon' => 'bx bx-receipt',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'AP Aging',
                    'description' => 'Open vendor payables ready for payment planning',
                    'amount' => $this->sumOpenAmount('purchase_invoices', $tenant),
                    'count' => $this->countOpenInvoices('purchase_invoices', $tenant),
                    'route' => 'ap/aging',
                    'icon' => 'bx bx-file',
                    'tone' => 'danger',
                ],
            ];

            $topPendingInvoices = array_merge(
                $this->getTopPendingInvoices('sales_invoices', 'AR', 'ar/sales-invoices', $tenant, 5),
                $this->getTopPendingInvoices('purchase_invoices', 'AP', 'ap/purchase-invoices', $tenant, 5)
            );
            usort($topPendingInvoices, static fn (array $left, array $right): int => ($right['amount'] ?? 0) <=> ($left['amount'] ?? 0));
            $topPendingInvoices = array_slice($topPendingInvoices, 0, 8);
            $topStockAlerts = $this->getTopStockAlerts($tenant, 8);
            $glUnbalancedEntries = $this->getUnbalancedGlEntryDetails($tenant, 8);
            $monthlyTrend = $this->getMonthlySalesPurchaseTrend($tenant, 6);

            $pendingWork = [
                [
                    'label' => 'SO Pending Approval',
                    'count' => $this->countTenantRows('sales_orders', $tenant, ['document_status' => 'submitted']),
                    'route' => 'sales/orders',
                    'badge' => 'warning',
                ],
                [
                    'label' => 'PO Pending Approval',
                    'count' => $this->countTenantRows('purchase_orders', $tenant, ['document_status' => 'submitted']),
                    'route' => 'purchase/orders',
                    'badge' => 'warning',
                ],
                [
                    'label' => 'OCR Pending Review',
                    'count' => $metrics['Pending OCR Review'],
                    'route' => 'ai-documents',
                    'badge' => 'info',
                ],
                [
                    'label' => 'Stock Alerts',
                    'count' => $metrics['Stock Alert'],
                    'route' => 'inventory/stock-alerts',
                    'badge' => 'danger',
                ],
            ];

            $workflowQueues = [
                [
                    'label' => 'PO To Receive',
                    'description' => 'Approved PO with outstanding qty',
                    'count' => $this->countOutstandingSourceDocuments('purchase_orders', 'purchase_order_lines', 'purchase_order_id', $tenant, ['approved', 'partial_received']),
                    'route' => 'purchase/orders?status=approved',
                    'badge' => 'primary',
                ],
                [
                    'label' => 'Receipt To Invoice',
                    'description' => 'Posted receipt not yet invoiced',
                    'count' => $this->countTenantRows('purchase_receipts', $tenant, ['status' => 'posted']),
                    'route' => 'purchase/receipts?status=posted',
                    'badge' => 'success',
                ],
                [
                    'label' => 'AP To Pay',
                    'description' => 'Open purchase invoices',
                    'count' => $this->countOpenInvoices('purchase_invoices', $tenant),
                    'route' => 'ap/aging',
                    'badge' => 'danger',
                ],
                [
                    'label' => 'SO To Deliver',
                    'description' => 'Approved/reserved SO with outstanding qty',
                    'count' => $this->countOutstandingSourceDocuments('sales_orders', 'sales_order_lines', 'sales_order_id', $tenant, ['approved', 'reserved', 'partial_delivered']),
                    'route' => 'sales/orders?status=approved',
                    'badge' => 'primary',
                ],
                [
                    'label' => 'Delivery To Invoice',
                    'description' => 'Posted delivery not yet invoiced',
                    'count' => $this->countTenantRows('sales_deliveries', $tenant, ['status' => 'posted']),
                    'route' => 'sales/deliveries?status=posted',
                    'badge' => 'success',
                ],
                [
                    'label' => 'AR To Collect',
                    'description' => 'Open sales invoices',
                    'count' => $this->countOpenInvoices('sales_invoices', $tenant),
                    'route' => 'ar/aging',
                    'badge' => 'danger',
                ],
            ];
        }

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'hasTenantAccess' => $hasTenantAccess,
            'recentActivities' => $recentActivities,
            'metrics' => $metrics,
            'metricLinks' => $metricLinks,
            'metricMoney' => $metricMoney,
            'pendingWork' => $pendingWork,
            'workflowQueues' => $workflowQueues,
            'financialSnapshot' => $financialSnapshot,
            'agingShortcuts' => $agingShortcuts,
            'topPendingInvoices' => $topPendingInvoices,
            'topStockAlerts' => $topStockAlerts,
            'glUnbalancedEntries' => $glUnbalancedEntries,
            'monthlyTrend' => $monthlyTrend,
        ]);
    }

    /**
     * @param list<string> $excludedStatuses
     */
    private function sumTenantAmount(string $table, string $field, TenantContext $tenant, array $excludedStatuses = []): float
    {
        $db = Database::connect();
        if (! $db->tableExists($table) || ! $db->fieldExists($field, $table)) {
            return 0.0;
        }

        $builder = $db->table($table)->selectSum($field, 'amount');
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $table)) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        if ($excludedStatuses !== [] && $db->fieldExists('status', $table)) {
            $builder->whereNotIn('status', $excludedStatuses);
        }

        return (float) ($builder->get()->getRowArray()['amount'] ?? 0);
    }

    /**
     * @param array<string, mixed> $where
     */
    private function countTenantRows(string $table, TenantContext $tenant, array $where = []): int
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return 0;
        }

        $builder = $db->table($table);
        $this->applyTenantFilters($builder, $table, $tenant);
        foreach ($where as $field => $value) {
            if ($db->fieldExists($field, $table)) {
                $builder->where($field, $value);
            }
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }

        return $builder->countAllResults();
    }

    /**
     * @param list<string> $statuses
     */
    private function countOutstandingSourceDocuments(string $headerTable, string $lineTable, string $lineForeignKey, TenantContext $tenant, array $statuses): int
    {
        $db = Database::connect();
        if (! $db->tableExists($headerTable) || ! $db->tableExists($lineTable)) {
            return 0;
        }
        if (! $db->fieldExists('qty_outstanding', $lineTable) || ! $db->fieldExists($lineForeignKey, $lineTable)) {
            return 0;
        }

        $builder = $db->table($headerTable . ' h')
            ->select('COUNT(DISTINCT h.id) AS total')
            ->join($lineTable . ' l', 'l.' . $lineForeignKey . ' = h.id', 'inner')
            ->where('l.qty_outstanding >', 0);

        if ($statuses !== [] && $db->fieldExists('document_status', $headerTable)) {
            $builder->whereIn('h.document_status', $statuses);
        }
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $headerTable)) {
            $builder->where('h.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $headerTable)) {
            $builder->where('h.site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', $headerTable)) {
            $builder->where('h.deleted_at', null);
        }

        return (int) ($builder->get()->getRowArray()['total'] ?? 0);
    }

    private function countOpenInvoices(string $table, TenantContext $tenant): int
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return 0;
        }

        $builder = $db->table($table);
        if ($db->fieldExists('outstanding_amount', $table)) {
            $builder->where('outstanding_amount >', 0);
        }
        $this->applyTenantFilters($builder, $table, $tenant);
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        if ($db->fieldExists('status', $table)) {
            $builder->whereIn('status', ['open', 'partial']);
        }

        return $builder->countAllResults();
    }

    private function sumOpenAmount(string $table, TenantContext $tenant): float
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return 0.0;
        }

        $amountField = $this->firstExistingField($table, ['outstanding_amount', 'total_amount', 'grand_total', 'net_amount']);

        if ($amountField === null) {
            return 0.0;
        }

        $builder = $db->table($table)->selectSum($amountField, 'amount');
        if ($amountField === 'outstanding_amount') {
            $builder->where('outstanding_amount >', 0);
        }
        if ($db->fieldExists('status', $table)) {
            $builder->whereIn('status', ['open', 'partial']);
        }
        $this->applyTenantFilters($builder, $table, $tenant);
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }

        return (float) ($builder->get()->getRowArray()['amount'] ?? 0);
    }

    private function sumCashBankBalance(TenantContext $tenant): float
    {
        $db = Database::connect();
        if (! $db->tableExists('cash_bank_accounts') || ! $db->fieldExists('current_balance', 'cash_bank_accounts')) {
            return 0.0;
        }

        $builder = $db->table('cash_bank_accounts')->selectSum('current_balance', 'amount');
        $this->applyTenantFilters($builder, 'cash_bank_accounts', $tenant);
        if ($db->fieldExists('deleted_at', 'cash_bank_accounts')) {
            $builder->where('deleted_at', null);
        }
        if ($db->fieldExists('is_active', 'cash_bank_accounts')) {
            $builder->where('is_active', 1);
        }

        return (float) ($builder->get()->getRowArray()['amount'] ?? 0);
    }

    private function sumInventoryValue(TenantContext $tenant): float
    {
        $db = Database::connect();
        if (! $db->tableExists('inventory_stock_balances') || ! $db->fieldExists('stock_value', 'inventory_stock_balances')) {
            return 0.0;
        }

        $builder = $db->table('inventory_stock_balances')->selectSum('stock_value', 'amount');
        $this->applyTenantFilters($builder, 'inventory_stock_balances', $tenant);

        return (float) ($builder->get()->getRowArray()['amount'] ?? 0);
    }

    private function countUnbalancedGlEntries(TenantContext $tenant): int
    {
        $db = Database::connect();
        if (! $db->tableExists('gl_entries')) {
            return 0;
        }
        if (! $db->fieldExists('total_debit', 'gl_entries') || ! $db->fieldExists('total_credit', 'gl_entries')) {
            return 0;
        }

        $builder = $db->table('gl_entries')
            ->where('ABS(COALESCE(total_debit, 0) - COALESCE(total_credit, 0)) >', 0.01, false);
        $this->applyTenantFilters($builder, 'gl_entries', $tenant);
        if ($db->fieldExists('deleted_at', 'gl_entries')) {
            $builder->where('deleted_at', null);
        }
        if ($db->fieldExists('status', 'gl_entries')) {
            $builder->whereNotIn('status', ['cancelled', 'void']);
        }

        return $builder->countAllResults();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getTopPendingInvoices(string $table, string $type, string $route, TenantContext $tenant, int $limit = 5): array
    {
        $db = Database::connect();
        if (! $db->tableExists($table)) {
            return [];
        }

        $amountField = $this->firstExistingField($table, ['outstanding_amount', 'total_amount', 'grand_total', 'net_amount']);
        if ($amountField === null) {
            return [];
        }

        $documentField = $this->firstExistingField($table, ['invoice_no', 'invoice_number', 'document_no', 'number', 'code']);
        $dateField = $this->firstExistingField($table, ['due_date', 'invoice_date', 'document_date', 'date', 'created_at']);
        $partnerField = $type === 'AR'
            ? $this->firstExistingField($table, ['customer_name', 'customer_code', 'customer_id'])
            : $this->firstExistingField($table, ['vendor_name', 'supplier_name', 'vendor_code', 'supplier_code', 'vendor_id', 'supplier_id']);

        $builder = $db->table($table)
            ->select('id')
            ->select($amountField . ' AS amount')
            ->where($amountField . ' >', 0)
            ->orderBy($amountField, 'DESC')
            ->limit($limit);

        if ($documentField !== null) {
            $builder->select($documentField . ' AS document_no');
        }
        if ($dateField !== null) {
            $builder->select($dateField . ' AS due_date');
        }
        if ($partnerField !== null) {
            $builder->select($partnerField . ' AS partner_name');
        }
        if ($db->fieldExists('status', $table)) {
            $builder->select('status')->whereIn('status', ['open', 'partial']);
        }
        $this->applyTenantFilters($builder, $table, $tenant);
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }

        return array_map(static function (array $row) use ($type, $route): array {
            return [
                'type' => $type,
                'document_no' => $row['document_no'] ?? ('#' . ($row['id'] ?? '-')),
                'partner_name' => $row['partner_name'] ?? '-',
                'due_date' => $row['due_date'] ?? '-',
                'amount' => (float) ($row['amount'] ?? 0),
                'status' => $row['status'] ?? '-',
                'route' => $route,
            ];
        }, $builder->get()->getResultArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getTopStockAlerts(TenantContext $tenant, int $limit = 8): array
    {
        $db = Database::connect();
        if (! $db->tableExists('item_locations')) {
            return [];
        }

        $stockSelect = null;
        if ($db->tableExists('inventory_stock_balances')) {
            $stockSelect = $db->table('inventory_stock_balances')
                ->select('company_id, site_id, warehouse_id, location_id, item_id, SUM(qty_available) AS qty_available')
                ->groupBy('company_id, site_id, warehouse_id, location_id, item_id')
                ->getCompiledSelect();
        }

        $builder = $db->table('item_locations il')
            ->select('il.item_id, il.location_id, il.warehouse_id, il.min_qty, il.reorder_qty')
            ->where('il.is_active', 1)
            ->limit($limit);

        if ($db->fieldExists('deleted_at', 'item_locations')) {
            $builder->where('il.deleted_at', null);
        }

        if ($stockSelect !== null) {
            $builder->select('COALESCE(b.qty_available, 0) AS qty_available', false)
                ->join(
                    '(' . $stockSelect . ') b',
                    'b.company_id = il.company_id AND ' .
                    '(b.site_id <=> il.site_id) AND ' .
                    '(b.warehouse_id <=> il.warehouse_id) AND ' .
                    'b.location_id = il.location_id AND b.item_id = il.item_id',
                    'left',
                    false
                );
        } else {
            $builder->select('0 AS qty_available', false);
        }

        if ($db->tableExists('items')) {
            $itemCode = $this->firstExistingField('items', ['item_code', 'code', 'sku']);
            $itemName = $this->firstExistingField('items', ['item_name', 'name', 'description']);
            $itemSelect = [];
            if ($itemCode !== null) {
                $itemSelect[] = 'i.' . $itemCode . ' AS item_code';
            }
            if ($itemName !== null) {
                $itemSelect[] = 'i.' . $itemName . ' AS item_name';
            }
            if ($itemSelect !== []) {
                $builder->select(implode(', ', $itemSelect));
            }
            $builder->join('items i', 'i.id = il.item_id', 'left');
        }

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('il.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('il.site_id', $tenant->activeSiteId());
        }

        $availableExpression = $stockSelect !== null ? 'COALESCE(b.qty_available, 0)' : '0';
        $builder->groupStart()
            ->groupStart()
            ->where('il.min_qty >', 0)
            ->where($availableExpression . ' < il.min_qty', null, false)
            ->groupEnd()
            ->orGroupStart()
            ->where('il.reorder_qty >', 0)
            ->where($availableExpression . ' <= il.reorder_qty', null, false)
            ->groupEnd()
            ->groupEnd()
            ->orderBy($availableExpression, 'ASC', false);

        return array_map(static function (array $row): array {
            $minQty = (float) ($row['min_qty'] ?? 0);
            $reorderQty = (float) ($row['reorder_qty'] ?? 0);
            $qtyAvailable = (float) ($row['qty_available'] ?? 0);
            $threshold = $minQty > 0 ? $minQty : $reorderQty;

            return [
                'item_code' => $row['item_code'] ?? ('Item #' . ($row['item_id'] ?? '-')),
                'item_name' => $row['item_name'] ?? '-',
                'qty_available' => $qtyAvailable,
                'threshold' => $threshold,
                'shortage' => max(0, $threshold - $qtyAvailable),
                'route' => 'inventory/stock-alerts',
            ];
        }, $builder->get()->getResultArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getUnbalancedGlEntryDetails(TenantContext $tenant, int $limit = 8): array
    {
        $db = Database::connect();
        if (! $db->tableExists('gl_entries') || ! $db->fieldExists('total_debit', 'gl_entries') || ! $db->fieldExists('total_credit', 'gl_entries')) {
            return [];
        }

        $entryField = $this->firstExistingField('gl_entries', ['entry_no', 'journal_no', 'document_no', 'number', 'code']);
        $dateField = $this->firstExistingField('gl_entries', ['entry_date', 'journal_date', 'document_date', 'date', 'created_at']);

        $builder = $db->table('gl_entries')
            ->select('id, total_debit, total_credit, ABS(COALESCE(total_debit, 0) - COALESCE(total_credit, 0)) AS variance', false)
            ->where('ABS(COALESCE(total_debit, 0) - COALESCE(total_credit, 0)) >', 0.01, false)
            ->orderBy('variance', 'DESC')
            ->limit($limit);

        if ($entryField !== null) {
            $builder->select($entryField . ' AS entry_no');
        }
        if ($dateField !== null) {
            $builder->select($dateField . ' AS entry_date');
        }
        if ($db->fieldExists('status', 'gl_entries')) {
            $builder->select('status')->whereNotIn('status', ['cancelled', 'void']);
        }
        $this->applyTenantFilters($builder, 'gl_entries', $tenant);
        if ($db->fieldExists('deleted_at', 'gl_entries')) {
            $builder->where('deleted_at', null);
        }

        return array_map(static function (array $row): array {
            return [
                'entry_no' => $row['entry_no'] ?? ('#' . ($row['id'] ?? '-')),
                'entry_date' => $row['entry_date'] ?? '-',
                'debit' => (float) ($row['total_debit'] ?? 0),
                'credit' => (float) ($row['total_credit'] ?? 0),
                'variance' => (float) ($row['variance'] ?? 0),
                'status' => $row['status'] ?? '-',
                'route' => 'gl/entries',
            ];
        }, $builder->get()->getResultArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getMonthlySalesPurchaseTrend(TenantContext $tenant, int $months = 6): array
    {
        $labels = [];
        $current = new \DateTimeImmutable('first day of this month 00:00:00');
        for ($index = $months - 1; $index >= 0; $index--) {
            $labels[] = $current->modify('-' . $index . ' months')->format('Y-m');
        }

        $sales = $this->sumMonthlyAmount('sales_invoices', $tenant, $labels);
        $purchase = $this->sumMonthlyAmount('purchase_invoices', $tenant, $labels);

        return array_map(static function (string $month) use ($sales, $purchase): array {
            return [
                'month' => $month,
                'sales' => $sales[$month] ?? 0.0,
                'purchase' => $purchase[$month] ?? 0.0,
                'net' => ($sales[$month] ?? 0.0) - ($purchase[$month] ?? 0.0),
            ];
        }, $labels);
    }

    /**
     * @param list<string> $months
     * @return array<string, float>
     */
    private function sumMonthlyAmount(string $table, TenantContext $tenant, array $months): array
    {
        $db = Database::connect();
        if (! $db->tableExists($table) || $months === []) {
            return [];
        }

        $dateField = $this->firstExistingField($table, ['invoice_date', 'document_date', 'date', 'created_at']);
        $amountField = $this->firstExistingField($table, ['total_amount', 'grand_total', 'net_amount', 'outstanding_amount']);
        if ($dateField === null || $amountField === null) {
            return [];
        }

        $startDate = $months[0] . '-01';
        $endDate = (new \DateTimeImmutable(end($months) . '-01'))->modify('first day of next month')->format('Y-m-d');

        $builder = $db->table($table)
            ->select("DATE_FORMAT({$dateField}, '%Y-%m') AS period", false)
            ->selectSum($amountField, 'amount')
            ->where($dateField . ' >=', $startDate)
            ->where($dateField . ' <', $endDate)
            ->groupBy('period')
            ->orderBy('period', 'ASC');

        $this->applyTenantFilters($builder, $table, $tenant);
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        if ($db->fieldExists('status', $table)) {
            $builder->whereNotIn('status', ['cancelled', 'void']);
        }

        $result = array_fill_keys($months, 0.0);
        foreach ($builder->get()->getResultArray() as $row) {
            if (isset($row['period'], $result[$row['period']])) {
                $result[$row['period']] = (float) ($row['amount'] ?? 0);
            }
        }

        return $result;
    }

    private function countStockAlerts(TenantContext $tenant): int
    {
        $db = Database::connect();
        if (! $db->tableExists('item_locations')) {
            return 0;
        }

        $stockSelect = null;
        if ($db->tableExists('inventory_stock_balances')) {
            $stockSelect = $db->table('inventory_stock_balances')
                ->select('company_id, site_id, warehouse_id, location_id, item_id, SUM(qty_available) AS qty_available')
                ->groupBy('company_id, site_id, warehouse_id, location_id, item_id')
                ->getCompiledSelect();
        }

        $builder = $db->table('item_locations il')
            ->where('il.deleted_at', null)
            ->where('il.is_active', 1);

        if ($stockSelect !== null) {
            $builder->join(
                '(' . $stockSelect . ') b',
                'b.company_id = il.company_id AND ' .
                '(b.site_id <=> il.site_id) AND ' .
                '(b.warehouse_id <=> il.warehouse_id) AND ' .
                'b.location_id = il.location_id AND b.item_id = il.item_id',
                'left',
                false
            );
        }

        if ($tenant->activeCompanyId() !== null) {
            $builder->where('il.company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null) {
            $builder->where('il.site_id', $tenant->activeSiteId());
        }

        $availableExpression = $stockSelect !== null ? 'COALESCE(b.qty_available, 0)' : '0';
        $builder->groupStart()
            ->groupStart()
            ->where('il.min_qty >', 0)
            ->where($availableExpression . ' < il.min_qty', null, false)
            ->groupEnd()
            ->orGroupStart()
            ->where('il.reorder_qty >', 0)
            ->where($availableExpression . ' <= il.reorder_qty', null, false)
            ->groupEnd()
            ->groupEnd();

        return $builder->countAllResults();
    }

    private function countOcrPendingReview(TenantContext $tenant): int
    {
        $db = Database::connect();
        if ($db->tableExists('document_extractions')) {
            $builder = $db->table('document_extractions e')
                ->select('COUNT(DISTINCT e.id) AS total')
                ->join('document_uploads d', 'd.id = e.document_upload_id', 'left')
                ->where('e.review_status', 'pending_review');
            if ($tenant->activeCompanyId() !== null) {
                $builder->where('d.company_id', $tenant->activeCompanyId());
            }
            if ($tenant->activeSiteId() !== null) {
                $builder->where('d.site_id', $tenant->activeSiteId());
            }
            if ($db->fieldExists('deleted_at', 'document_uploads')) {
                $builder->where('d.deleted_at', null);
            }

            return (int) ($builder->get()->getRowArray()['total'] ?? 0);
        }

        return $this->countTenantRows('document_uploads', $tenant, ['status' => 'uploaded']);
    }

    /**
     * @param list<string> $candidates
     */
    private function firstExistingField(string $table, array $candidates): ?string
    {
        $db = Database::connect();
        foreach ($candidates as $field) {
            if ($db->fieldExists($field, $table)) {
                return $field;
            }
        }

        return null;
    }

    private function applyTenantFilters(object $builder, string $table, TenantContext $tenant): void
    {
        $db = Database::connect();
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $table)) {
            $builder->where('site_id', $tenant->activeSiteId());
        }
    }
}
