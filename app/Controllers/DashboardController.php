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

        $amountField = $db->fieldExists('outstanding_amount', $table)
            ? 'outstanding_amount'
            : ($db->fieldExists('total_amount', $table) ? 'total_amount' : null);

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
