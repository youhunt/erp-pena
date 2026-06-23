<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

class ErpFinalHealthCheck extends BaseCommand
{
    protected $group = 'ERP';
    protected $name = 'erp:final-healthcheck';
    protected $description = 'Final ERP PENA health check for master hierarchy, PO receipt quantities, inventory stock integrity, and GL setup.';

    public function run(array $params)
    {
        $db = Database::connect();

        $checks = [
            'DEPARTMENT_WITHOUT_SCOPE' => "
                SELECT COUNT(*) AS total
                FROM departments d
                WHERE (d.company_id IS NULL OR d.site_id IS NULL)
                  AND d.deleted_at IS NULL
            ",
            'WAREHOUSE_DEPARTMENT_SCOPE_MISMATCH' => "
                SELECT COUNT(*) AS total
                FROM warehouses w
                LEFT JOIN departments d ON d.id = w.department_id
                WHERE w.department_id IS NOT NULL
                  AND (
                      d.id IS NULL
                      OR NOT (d.company_id <=> w.company_id)
                      OR NOT (d.site_id <=> w.site_id)
                  )
                  AND w.deleted_at IS NULL
            ",
            'LOCATION_WAREHOUSE_SCOPE_MISMATCH' => "
                SELECT COUNT(*) AS total
                FROM locations l
                LEFT JOIN warehouses w ON w.id = l.warehouse_id
                WHERE l.warehouse_id IS NOT NULL
                  AND (
                      w.id IS NULL
                      OR NOT (w.company_id <=> l.company_id)
                      OR NOT (w.site_id <=> l.site_id)
                  )
                  AND l.deleted_at IS NULL
            ",
            'ITEM_LOCATION_WAREHOUSE_LOCATION_MISMATCH' => "
                SELECT COUNT(*) AS total
                FROM item_locations il
                LEFT JOIN locations l ON l.id = il.location_id
                WHERE il.location_id IS NOT NULL
                  AND il.warehouse_id IS NOT NULL
                  AND l.id IS NOT NULL
                  AND l.warehouse_id IS NOT NULL
                  AND l.warehouse_id <> il.warehouse_id
            ",
            'ITEM_LOCATION_ITEM_SCOPE_MISMATCH' => "
                SELECT COUNT(*) AS total
                FROM item_locations il
                LEFT JOIN items i ON i.id = il.item_id
                WHERE il.item_id IS NOT NULL
                  AND (
                      i.id IS NULL
                      OR NOT (i.company_id <=> il.company_id)
                      OR NOT (i.site_id <=> il.site_id)
                  )
            ",
            'GL_ACCOUNT_2300_MISSING_OR_INACTIVE' => "
                SELECT COUNT(*) AS total
                FROM companies c
                LEFT JOIN chart_accounts ca
                    ON ca.company_id = c.id
                   AND ca.account_no = '2300'
                   AND ca.is_active = 1
                WHERE COALESCE(c.is_active, 1) = 1
                  AND ca.id IS NULL
            ",
            'GL_REQUIRED_ACCOUNTS_MISSING_OR_INACTIVE' => "
                SELECT COUNT(*) AS total
                FROM companies c
                JOIN (
                    SELECT '1200' account_no UNION ALL SELECT '1300' UNION ALL SELECT '1400'
                    UNION ALL SELECT '2100' UNION ALL SELECT '2200' UNION ALL SELECT '2300'
                    UNION ALL SELECT '4100' UNION ALL SELECT '5000' UNION ALL SELECT '6200'
                    UNION ALL SELECT '7000' UNION ALL SELECT '8000'
                ) req
                LEFT JOIN chart_accounts ca
                    ON ca.company_id = c.id
                   AND ca.account_no = req.account_no
                   AND ca.is_active = 1
                WHERE COALESCE(c.is_active, 1) = 1
                  AND ca.id IS NULL
            ",
            'GL_POSTING_PROFILES_MISSING_OR_INACTIVE' => "
                SELECT COUNT(*) AS total
                FROM companies c
                JOIN (
                    SELECT 'ap' module_code, 'payable' posting_key UNION ALL SELECT 'ap', 'grni'
                    UNION ALL SELECT 'ap', 'inventory' UNION ALL SELECT 'ap', 'input_vat'
                    UNION ALL SELECT 'ar', 'receivable' UNION ALL SELECT 'ar', 'sales_revenue'
                    UNION ALL SELECT 'ar', 'output_vat' UNION ALL SELECT 'sales', 'cogs'
                    UNION ALL SELECT 'sales', 'inventory' UNION ALL SELECT 'inventory', 'inventory'
                    UNION ALL SELECT 'inventory', 'adjustment_gain' UNION ALL SELECT 'inventory', 'adjustment_loss'
                    UNION ALL SELECT 'cashbank', 'cash_bank'
                ) req
                LEFT JOIN gl_posting_profiles gpp
                    ON gpp.company_id = c.id
                   AND gpp.module_code = req.module_code
                   AND gpp.posting_key = req.posting_key
                   AND gpp.is_active = 1
                   AND gpp.deleted_at IS NULL
                WHERE COALESCE(c.is_active, 1) = 1
                  AND gpp.id IS NULL
            ",
            'PO_LINE_NEGATIVE_QTY' => "
                SELECT COUNT(*) AS total
                FROM purchase_order_lines pol
                WHERE COALESCE(pol.qty_received, 0) < 0
                   OR COALESCE(pol.qty_outstanding, 0) < 0
            ",
            'PO_LINE_OVER_RECEIVED' => "
                SELECT COUNT(*) AS total
                FROM purchase_order_lines pol
                WHERE COALESCE(pol.qty_ordered, 0) > 0
                  AND COALESCE(pol.qty_received, 0) > COALESCE(pol.qty_ordered, 0)
            ",
            'STOCK_MOVEMENT_INVALID_DIRECTION_OR_QTY' => "
                SELECT COUNT(*) AS total
                FROM inventory_stock_movements m
                WHERE m.direction NOT IN ('in', 'out')
                   OR COALESCE(m.qty, 0) <= 0
                   OR COALESCE(m.item_code, '') = ''
            ",
            'STOCK_BALANCE_NEGATIVE_QTY' => "
                SELECT COUNT(*) AS total
                FROM inventory_stock_balances b
                WHERE COALESCE(b.qty_on_hand, 0) < 0
                   OR COALESCE(b.qty_reserved, 0) < 0
                   OR COALESCE(b.qty_available, 0) < 0
                   OR COALESCE(b.stock_value, 0) < 0
            ",
            'STOCK_BALANCE_AVAILABLE_MISMATCH' => "
                SELECT COUNT(*) AS total
                FROM inventory_stock_balances b
                WHERE ABS(COALESCE(b.qty_available, 0) - (COALESCE(b.qty_on_hand, 0) - COALESCE(b.qty_reserved, 0))) > 0.0001
            ",
            'STOCK_MOVEMENT_WITHOUT_BALANCE' => "
                SELECT COUNT(*) AS total
                FROM (
                    SELECT
                        m.company_id,
                        m.site_id,
                        m.warehouse_id,
                        m.location_id,
                        m.item_code,
                        COALESCE(m.batch_no, '') AS batch_no
                    FROM inventory_stock_movements m
                    GROUP BY
                        m.company_id,
                        m.site_id,
                        m.warehouse_id,
                        m.location_id,
                        m.item_code,
                        COALESCE(m.batch_no, '')
                ) x
                LEFT JOIN inventory_stock_balances b
                    ON b.company_id <=> x.company_id
                   AND b.site_id <=> x.site_id
                   AND b.warehouse_id <=> x.warehouse_id
                   AND b.location_id <=> x.location_id
                   AND b.item_code <=> x.item_code
                   AND COALESCE(b.batch_no, '') <=> x.batch_no
                WHERE b.id IS NULL
            ",
            'STOCK_BALANCE_MOVEMENT_QTY_MISMATCH' => "
                SELECT COUNT(*) AS total
                FROM inventory_stock_balances b
                INNER JOIN (
                    SELECT
                        m.company_id,
                        m.site_id,
                        m.warehouse_id,
                        m.location_id,
                        m.item_code,
                        COALESCE(m.batch_no, '') AS batch_no,
                        SUM(CASE WHEN m.direction = 'in' THEN COALESCE(m.qty, 0) ELSE -COALESCE(m.qty, 0) END) AS movement_qty,
                        SUM(CASE WHEN m.direction = 'in' THEN COALESCE(m.stock_value, 0) ELSE -COALESCE(m.stock_value, 0) END) AS movement_value
                    FROM inventory_stock_movements m
                    GROUP BY
                        m.company_id,
                        m.site_id,
                        m.warehouse_id,
                        m.location_id,
                        m.item_code,
                        COALESCE(m.batch_no, '')
                ) x
                    ON b.company_id <=> x.company_id
                   AND b.site_id <=> x.site_id
                   AND b.warehouse_id <=> x.warehouse_id
                   AND b.location_id <=> x.location_id
                   AND b.item_code <=> x.item_code
                   AND COALESCE(b.batch_no, '') <=> x.batch_no
                WHERE ABS(COALESCE(b.qty_on_hand, 0) - COALESCE(x.movement_qty, 0)) > 0.0001
            ",
        ];

        CLI::write('ERP PENA FINAL HEALTH CHECK', 'yellow');
        CLI::write(str_repeat('-', 40));

        $failed = false;

        foreach ($checks as $name => $sql) {
            try {
                $row = $db->query($sql)->getRowArray();
                $total = (int) ($row['total'] ?? 0);
            } catch (Throwable $e) {
                $failed = true;
                CLI::write('[ERROR] ' . $name . ' => ' . $e->getMessage(), 'red');
                continue;
            }

            if ($total === 0) {
                CLI::write('[PASS]  ' . $name . ' = 0', 'green');
            } else {
                $failed = true;
                CLI::write('[FAIL]  ' . $name . ' = ' . $total, 'red');
            }
        }

        CLI::write(str_repeat('-', 40));

        if ($failed) {
            CLI::write('RESULT: FAILED - send the FAIL/ERROR lines for final repair.', 'red');
            return EXIT_ERROR;
        }

        CLI::write('RESULT: FINISHED / FIXED', 'green');
        return EXIT_SUCCESS;
    }
}
