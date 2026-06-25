<?php

namespace App\Controllers\System;

use App\Controllers\BaseController;
use Config\Database;
use Throwable;

class CoreHealthController extends BaseController
{
    public function index(): string
    {
        $db = Database::connect();
        $checks = $this->checks($db);
        $failed = array_values(array_filter($checks, static fn (array $check): bool => ! $check['pass']));

        return view('system/core_health/index', [
            'title' => 'ERP Core Health',
            'checks' => $checks,
            'failed' => $failed,
            'passedCount' => count($checks) - count($failed),
            'failedCount' => count($failed),
            'selectedDatabase' => $db->getDatabase(),
            'generatedAt' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, array{name:string,total:int,expected:int,pass:bool,level:string,note:string}>
     */
    private function checks($db): array
    {
        $checks = [];

        foreach ([
            'companies' => 'CORE_TABLE_COMPANIES',
            'sites' => 'CORE_TABLE_SITES',
            'departments' => 'CORE_TABLE_DEPARTMENTS',
            'warehouses' => 'CORE_TABLE_WAREHOUSES',
            'locations' => 'CORE_TABLE_LOCATIONS',
            'items' => 'CORE_TABLE_ITEMS',
            'uoms' => 'CORE_TABLE_UOMS',
            'transaction_codes' => 'CORE_TABLE_TRANSACTION_CODES',
            'document_number_sequences' => 'CORE_TABLE_DOCUMENT_SEQUENCES',
            'chart_accounts' => 'CORE_TABLE_CHART_ACCOUNTS',
            'gl_entries' => 'CORE_TABLE_GL_ENTRIES',
            'gl_entry_lines' => 'CORE_TABLE_GL_ENTRY_LINES',
            'inventory_stock_balances' => 'CORE_TABLE_STOCK_BALANCES',
            'cash_bank_accounts' => 'CORE_TABLE_CASH_BANK_ACCOUNTS',
            'cash_bank_entries' => 'CORE_TABLE_CASH_BANK_ENTRIES',
            'currencies' => 'CORE_TABLE_CURRENCIES',
            'currency_rates' => 'CORE_TABLE_CURRENCY_RATES',
            'employees' => 'CORE_TABLE_EMPLOYEES',
            'purchase_orders' => 'CORE_TABLE_PURCHASE_ORDERS',
            'purchase_order_lines' => 'CORE_TABLE_PURCHASE_ORDER_LINES',
            'purchase_receipts' => 'CORE_TABLE_PURCHASE_RECEIPTS',
            'sales_orders' => 'CORE_TABLE_SALES_ORDERS',
            'sales_order_lines' => 'CORE_TABLE_SALES_ORDER_LINES',
            'production_boms' => 'CORE_TABLE_BOMS',
            'production_bom_lines' => 'CORE_TABLE_BOM_LINES',
            'production_mrp_runs' => 'CORE_TABLE_MRP_RUNS',
            'production_mrp_lines' => 'CORE_TABLE_MRP_LINES',
            'production_mrp_planned_orders' => 'CORE_TABLE_MRP_PLANNED_ORDERS',
            'costing_cost_types' => 'CORE_TABLE_COST_TYPES',
            'costing_item_costs' => 'CORE_TABLE_ITEM_COSTS',
            'costing_item_cost_lines' => 'CORE_TABLE_ITEM_COST_LINES',
        ] as $table => $name) {
            $checks[] = $this->tableCheck($db, $table, $name);
        }

        $checks[] = $this->columnsCheck($db, 'cash_bank_accounts', [
            'cash_bank_code', 'cash_bank_name', 'account_type', 'currency_code', 'current_balance', 'bank_branch', 'bank_code', 'bank_account', 'pic', 'phone', 'address',
        ], 'CASH_BANK_ACCOUNT_COLUMNS_READY');

        $checks[] = $this->columnsCheck($db, 'cash_bank_entries', [
            'entry_no', 'entry_date', 'entry_type', 'cash_bank_code', 'currency_code', 'amount', 'rate_type', 'exchange_rate', 'base_currency', 'base_amount', 'counter_account_no', 'gl_entry_id',
        ], 'CASH_BANK_ENTRY_COLUMNS_READY');

        $checks[] = $this->columnsCheck($db, 'items', [
            'item_code', 'item_name', 'item_type', 'stockuom', 'company_id',
        ], 'ITEM_MASTER_COLUMNS_READY');

        $checks[] = $this->columnsCheck($db, 'purchase_order_lines', [
            'purchase_order_id', 'item_id', 'item_code', 'item_name', 'uom_code', 'qty_ordered', 'qty_received', 'qty_outstanding',
        ], 'PO_LINE_COLUMNS_READY');

        if ($this->fieldsReady($db, 'items', ['item_type'])) {
            $checks[] = $this->countCheck($db, 'ITEM_TYPE_NULL_OR_BLANK', "
                SELECT COUNT(*) AS total
                FROM items
                WHERE item_type IS NULL OR TRIM(item_type) = ''
            ", 0, 'Run item master cleanup: item_type is required.');
        }

        if ($this->fieldsReady($db, 'items', ['company_id', 'item_code'])) {
            $siteExpr = $db->fieldExists('site_id', 'items') ? 'site_id' : '0';
            $deletedWhere = $db->fieldExists('deleted_at', 'items') ? 'WHERE deleted_at IS NULL' : 'WHERE 1=1';
            $checks[] = $this->countCheck($db, 'ITEM_DUPLICATE_PER_COMPANY_SITE_CODE', "
                SELECT COUNT(*) AS total
                FROM (
                    SELECT company_id, {$siteExpr} AS site_id, item_code
                    FROM items
                    {$deletedWhere}
                      AND COALESCE(item_code, '') <> ''
                    GROUP BY company_id, {$siteExpr}, item_code
                    HAVING COUNT(*) > 1
                ) x
            ", 0, 'Clean duplicate item by company/site/item_code.');
        }

        if ($this->fieldsReady($db, 'items', ['item_code', 'stockuom'])) {
            $deletedWhere = $db->fieldExists('deleted_at', 'items') ? 'deleted_at IS NULL AND' : '';
            $checks[] = $this->countCheck($db, 'ITEM_WITHOUT_STOCK_UOM', "
                SELECT COUNT(*) AS total
                FROM items
                WHERE {$deletedWhere} COALESCE(item_code, '') <> ''
                  AND (stockuom IS NULL OR TRIM(stockuom) = '')
            ", 0, 'Fill stockuom for imported items.');
        }

        if ($db->tableExists('transaction_codes') && $this->fieldsReady($db, 'transaction_codes', ['code'])) {
            $activeExpr = $db->fieldExists('is_active', 'transaction_codes') ? 'COALESCE(tc.is_active, 1) = 1' : '1=1';
            $checks[] = $this->countCheck($db, 'DOCUMENT_NUMBERING_REQUIRED_CODES_MISSING_OR_INACTIVE', "
                SELECT COUNT(*) AS total
                FROM (
                    SELECT 'PO' AS code UNION ALL SELECT 'PR' UNION ALL SELECT 'SO' UNION ALL SELECT 'SD' UNION ALL SELECT 'SI' UNION ALL SELECT 'PI' UNION ALL SELECT 'JV'
                ) req
                LEFT JOIN transaction_codes tc ON tc.code = req.code AND {$activeExpr}
                WHERE tc.id IS NULL
            ", 0, 'Run fix_required_document_numbering_codes SQL.');
        }

        if ($this->fieldsReady($db, 'purchase_order_lines', ['item_code', 'item_name', 'qty_outstanding'])) {
            $checks[] = $this->countCheck($db, 'OPEN_PO_LINES_WITHOUT_ITEM_CODE', "
                SELECT COUNT(*) AS total
                FROM purchase_order_lines
                WHERE COALESCE(qty_outstanding, 0) > 0
                  AND (item_code IS NULL OR TRIM(item_code) = '')
                  AND COALESCE(item_name, '') <> ''
            ", 0, 'Map imported PO line names to Item Master before receipt.');
        }

        if ($this->fieldsReady($db, 'production_bom_lines', ['qty_used'])) {
            $checks[] = $this->countCheck($db, 'BOM_LINES_WITH_ZERO_OR_NEGATIVE_QTY', "
                SELECT COUNT(*) AS total
                FROM production_bom_lines
                WHERE COALESCE(qty_used, 0) <= 0
            ", 0, 'Fix BOM lines with zero/negative qty.');
        }

        if ($this->fieldsReady($db, 'production_boms', ['id', 'company_id']) && $this->fieldsReady($db, 'production_bom_lines', ['production_bom_id', 'child_item_code']) && $this->fieldsReady($db, 'items', ['company_id', 'item_code'])) {
            $bomSiteJoin = $db->fieldExists('site_id', 'production_boms') && $db->fieldExists('site_id', 'items') ? 'AND (i.site_id = b.site_id OR i.site_id IS NULL OR i.site_id = 0)' : '';
            $itemDeleted = $db->fieldExists('deleted_at', 'items') ? 'AND i.deleted_at IS NULL' : '';
            $checks[] = $this->countCheck($db, 'BOM_CHILD_ITEM_WITHOUT_ITEM_MASTER', "
                SELECT COUNT(*) AS total
                FROM production_bom_lines l
                JOIN production_boms b ON b.id = l.production_bom_id
                LEFT JOIN items i
                    ON i.company_id = b.company_id
                   AND i.item_code = l.child_item_code
                   {$bomSiteJoin}
                   {$itemDeleted}
                WHERE COALESCE(l.child_item_code, '') <> ''
                  AND i.id IS NULL
            ", 0, 'Backfill missing BOM child item into item master.');
        }

        if ($this->fieldsReady($db, 'inventory_stock_balances', ['qty_available'])) {
            $checks[] = $this->countCheck($db, 'STOCK_BALANCE_NEGATIVE_AVAILABLE', "
                SELECT COUNT(*) AS total
                FROM inventory_stock_balances
                WHERE COALESCE(qty_available, 0) < 0
            ", 0, 'Review negative available stock.');
        }

        if ($this->fieldsReady($db, 'gl_entry_lines', ['gl_entry_id', 'debit', 'credit'])) {
            $checks[] = $this->countCheck($db, 'GL_UNBALANCED_ENTRIES', "
                SELECT COUNT(*) AS total
                FROM (
                    SELECT gl_entry_id
                    FROM gl_entry_lines
                    GROUP BY gl_entry_id
                    HAVING ABS(SUM(COALESCE(debit, 0)) - SUM(COALESCE(credit, 0))) > 0.0001
                ) x
            ", 0, 'Review unbalanced GL entries.');
        }

        return $checks;
    }

    private function tableCheck($db, string $table, string $name): array
    {
        $exists = $db->tableExists($table);
        return [
            'name' => $name,
            'total' => $exists ? 1 : 0,
            'expected' => 1,
            'pass' => $exists,
            'level' => $exists ? 'ok' : 'critical',
            'note' => $exists ? 'Required table exists.' : 'Missing table: ' . $table,
        ];
    }

    /**
     * @param list<string> $columns
     */
    private function columnsCheck($db, string $table, array $columns, string $name): array
    {
        if (! $db->tableExists($table)) {
            return [
                'name' => $name,
                'total' => 0,
                'expected' => count($columns),
                'pass' => false,
                'level' => 'critical',
                'note' => 'Missing table: ' . $table,
            ];
        }

        $missing = [];
        foreach ($columns as $column) {
            if (! $db->fieldExists($column, $table)) {
                $missing[] = $column;
            }
        }

        return [
            'name' => $name,
            'total' => count($columns) - count($missing),
            'expected' => count($columns),
            'pass' => $missing === [],
            'level' => $missing === [] ? 'ok' : 'critical',
            'note' => $missing === [] ? 'OK' : 'Missing columns: ' . implode(', ', $missing),
        ];
    }

    /**
     * @param list<string> $fields
     */
    private function fieldsReady($db, string $table, array $fields): bool
    {
        if (! $db->tableExists($table)) {
            return false;
        }
        foreach ($fields as $field) {
            if (! $db->fieldExists($field, $table)) {
                return false;
            }
        }
        return true;
    }

    private function countCheck($db, string $name, string $sql, int $expected, string $note): array
    {
        try {
            $row = $db->query($sql)->getRowArray() ?: ['total' => 0];
            $total = (int) ($row['total'] ?? 0);
            return [
                'name' => $name,
                'total' => $total,
                'expected' => $expected,
                'pass' => $total === $expected,
                'level' => $total === $expected ? 'ok' : 'warning',
                'note' => $total === $expected ? 'OK' : $note,
            ];
        } catch (Throwable $e) {
            return [
                'name' => $name,
                'total' => -1,
                'expected' => $expected,
                'pass' => false,
                'level' => 'critical',
                'note' => $e->getMessage(),
            ];
        }
    }
}
