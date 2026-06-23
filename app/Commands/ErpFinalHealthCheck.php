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
    protected $description = 'Final ERP PENA health check for master hierarchy and PO receipt quantities.';

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
