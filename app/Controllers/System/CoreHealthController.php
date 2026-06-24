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
            'items' => 'CORE_TABLE_ITEMS',
            'production_boms' => 'CORE_TABLE_BOMS',
            'production_bom_lines' => 'CORE_TABLE_BOM_LINES',
            'transaction_codes' => 'CORE_TABLE_TRANSACTION_CODES',
            'document_number_sequences' => 'CORE_TABLE_DOCUMENT_SEQUENCES',
        ] as $table => $name) {
            $checks[] = $this->tableCheck($db, $table, $name);
        }

        if ($db->tableExists('items')) {
            $checks[] = $this->countCheck($db, 'ITEM_TYPE_NULL_OR_BLANK', "
                SELECT COUNT(*) AS total
                FROM items
                WHERE item_type IS NULL OR TRIM(item_type) = ''
            ", 0, 'Run guard/fix item type SQL.');

            $checks[] = $this->countCheck($db, 'ITEM_DUPLICATE_PER_COMPANY_SITE_CODE', "
                SELECT COUNT(*) AS total
                FROM (
                    SELECT company_id, site_id, item_code
                    FROM items
                    WHERE deleted_at IS NULL
                      AND COALESCE(item_code, '') <> ''
                    GROUP BY company_id, site_id, item_code
                    HAVING COUNT(*) > 1
                ) x
            ", 0, 'Clean duplicate item by company/site/item_code.');

            $checks[] = $this->countCheck($db, 'ITEM_WITHOUT_STOCK_UOM', "
                SELECT COUNT(*) AS total
                FROM items
                WHERE deleted_at IS NULL
                  AND COALESCE(item_code, '') <> ''
                  AND (stockuom IS NULL OR TRIM(stockuom) = '')
            ", 0, 'Fill stockuom for imported items.');
        }

        if ($db->tableExists('transaction_codes')) {
            $checks[] = $this->countCheck($db, 'DOCUMENT_NUMBERING_REQUIRED_CODES_MISSING_OR_INACTIVE', "
                SELECT COUNT(*) AS total
                FROM (
                    SELECT 'PO' AS code UNION ALL SELECT 'PR' UNION ALL SELECT 'SO' UNION ALL SELECT 'SD' UNION ALL SELECT 'SI' UNION ALL SELECT 'PI' UNION ALL SELECT 'JV'
                ) req
                LEFT JOIN transaction_codes tc ON tc.code = req.code AND COALESCE(tc.is_active, 1) = 1
                WHERE tc.id IS NULL
            ", 0, 'Run fix_required_document_numbering_codes SQL.');
        }

        if ($db->tableExists('production_bom_lines')) {
            $checks[] = $this->countCheck($db, 'BOM_LINES_WITH_ZERO_OR_NEGATIVE_QTY', "
                SELECT COUNT(*) AS total
                FROM production_bom_lines
                WHERE COALESCE(qty_used, 0) <= 0
            ", 0, 'Fix BOM lines with zero/negative qty.');
        }

        if ($db->tableExists('production_boms') && $db->tableExists('production_bom_lines') && $db->tableExists('items')) {
            $checks[] = $this->countCheck($db, 'BOM_CHILD_ITEM_WITHOUT_ITEM_MASTER', "
                SELECT COUNT(*) AS total
                FROM production_bom_lines l
                JOIN production_boms b ON b.id = l.production_bom_id
                LEFT JOIN items i
                    ON i.company_id = b.company_id
                   AND i.item_code = l.child_item_code
                   AND (i.site_id = b.site_id OR i.site_id IS NULL OR i.site_id = 0)
                   AND i.deleted_at IS NULL
                WHERE COALESCE(l.child_item_code, '') <> ''
                  AND i.id IS NULL
            ", 0, 'Backfill missing BOM child item into item master.');
        }

        if ($db->tableExists('inventory_stock_balances')) {
            $checks[] = $this->countCheck($db, 'STOCK_BALANCE_NEGATIVE_AVAILABLE', "
                SELECT COUNT(*) AS total
                FROM inventory_stock_balances
                WHERE COALESCE(qty_available, 0) < 0
            ", 0, 'Review negative available stock.');
        }

        if ($db->tableExists('gl_entry_lines')) {
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
