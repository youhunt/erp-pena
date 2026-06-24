<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

class ErpCoreHealthCheck extends BaseCommand
{
    protected $group = 'ERP';
    protected $name = 'erp:core-health';
    protected $description = 'Run ERP core database/data health checks before continuing UAT or MRP planning.';

    public function run(array $params): void
    {
        $db = Database::connect();
        $checks = [];

        $checks[] = $this->tableCheck($db, 'companies', 'CORE_TABLE_COMPANIES');
        $checks[] = $this->tableCheck($db, 'sites', 'CORE_TABLE_SITES');
        $checks[] = $this->tableCheck($db, 'items', 'CORE_TABLE_ITEMS');
        $checks[] = $this->tableCheck($db, 'production_boms', 'CORE_TABLE_BOMS');
        $checks[] = $this->tableCheck($db, 'production_bom_lines', 'CORE_TABLE_BOM_LINES');
        $checks[] = $this->tableCheck($db, 'transaction_codes', 'CORE_TABLE_TRANSACTION_CODES');
        $checks[] = $this->tableCheck($db, 'document_number_sequences', 'CORE_TABLE_DOCUMENT_SEQUENCES');

        if ($db->tableExists('items')) {
            $checks[] = $this->countCheck($db, 'ITEM_TYPE_NULL_OR_BLANK', "
                SELECT COUNT(*) AS total
                FROM items
                WHERE item_type IS NULL OR TRIM(item_type) = ''
            ", 0, 'Run guard_items_item_type SQL or fix item_type defaults.');

            $checks[] = $this->countCheck($db, 'ITEM_DUPLICATE_PER_COMPANY_SITE_CODE', "
                SELECT COUNT(*) AS total
                FROM (
                    SELECT company_id, site_id, item_code
                    FROM items
                    WHERE (deleted_at IS NULL OR deleted_at IS NULL)
                      AND COALESCE(item_code, '') <> ''
                    GROUP BY company_id, site_id, item_code
                    HAVING COUNT(*) > 1
                ) x
            ", 0, 'Duplicate item master by company/site/item_code must be cleaned.');

            $checks[] = $this->countCheck($db, 'ITEM_WITHOUT_STOCK_UOM', "
                SELECT COUNT(*) AS total
                FROM items
                WHERE (deleted_at IS NULL OR deleted_at IS NULL)
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
            ", 0, 'Seed/activate required transaction_codes.');
        }

        if ($db->tableExists('production_boms') && $db->tableExists('production_bom_lines')) {
            $checks[] = $this->countCheck($db, 'BOM_LINES_WITH_ZERO_OR_NEGATIVE_QTY', "
                SELECT COUNT(*) AS total
                FROM production_bom_lines
                WHERE COALESCE(qty_used, 0) <= 0
            ", 0, 'Fix BOM qty_used <= 0.');

            if ($db->tableExists('items')) {
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
                ", 0, 'Backfill missing child material into item master.');
            }
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

        CLI::write('ERP Core Health Check', 'yellow');
        CLI::write(str_repeat('=', 80));

        $failed = 0;
        foreach ($checks as $check) {
            $status = $check['pass'] ? 'PASS' : 'FAIL';
            $color = $check['pass'] ? 'green' : 'red';
            if (! $check['pass']) {
                $failed++;
            }
            CLI::write(str_pad($status, 6) . ' ' . str_pad($check['name'], 45) . ' total=' . $check['total'], $color);
            if (! $check['pass'] && $check['note'] !== '') {
                CLI::write('       ' . $check['note'], 'yellow');
            }
        }

        CLI::write(str_repeat('=', 80));
        if ($failed === 0) {
            CLI::write('CORE HEALTH: PASS. ERP core is ready for next UAT/MRP step.', 'green');
            return;
        }

        CLI::write('CORE HEALTH: FAIL. Fix failed checks before continuing.', 'red');
    }

    private function tableCheck($db, string $table, string $name): array
    {
        return [
            'name' => $name,
            'total' => $db->tableExists($table) ? 1 : 0,
            'pass' => $db->tableExists($table),
            'note' => 'Required table is missing: ' . $table,
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
                'pass' => $total === $expected,
                'note' => $note,
            ];
        } catch (Throwable $e) {
            return [
                'name' => $name,
                'total' => -1,
                'pass' => false,
                'note' => $e->getMessage(),
            ];
        }
    }
}
