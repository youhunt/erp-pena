<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class RebuildStockBalancesCommand extends BaseCommand
{
    protected $group = 'Inventory';
    protected $name = 'inventory:rebuild-balances';
    protected $description = 'Rebuild inventory_stock_balances from inventory_stock_movements.';
    protected $usage = 'inventory:rebuild-balances [--company=1] [--site=1] [--dry-run]';
    protected $options = [
        '--company' => 'Optional company_id scope.',
        '--site' => 'Optional site_id scope.',
        '--dry-run' => 'Show rebuild summary without deleting/inserting balances.',
    ];

    public function run(array $params): void
    {
        $db = Database::connect();

        if (! $db->tableExists('inventory_stock_movements') || ! $db->tableExists('inventory_stock_balances')) {
            CLI::error('Required tables inventory_stock_movements / inventory_stock_balances are not available.');
            return;
        }

        $companyId = $this->positiveInt(CLI::getOption('company'));
        $siteId = $this->positiveInt(CLI::getOption('site'));
        $dryRun = CLI::getOption('dry-run') !== null;
        $valueExpression = $this->movementValueExpression($db);

        CLI::write('Inventory Stock Balance Rebuild', 'green');
        CLI::write('Source : inventory_stock_movements');
        CLI::write('Target : inventory_stock_balances');
        CLI::write('Company: ' . ($companyId !== null ? (string) $companyId : 'ALL'));
        CLI::write('Site   : ' . ($siteId !== null ? (string) $siteId : 'ALL'));
        CLI::write('Mode   : ' . ($dryRun ? 'DRY RUN' : 'REBUILD'));
        CLI::write('Value  : ' . $valueExpression['label']);
        CLI::newLine();

        $rows = $this->movementRows($db, $companyId, $siteId, $valueExpression['sql']);
        CLI::write('Movement groups found: ' . count($rows));

        if ($rows === []) {
            CLI::write('No valid movement rows found. Nothing to rebuild.', 'yellow');
            return;
        }

        $negativeRows = array_filter($rows, static fn (array $row): bool => (float) ($row['qty_on_hand'] ?? 0) < -0.0001);
        if ($negativeRows !== []) {
            CLI::write('Warning: ' . count($negativeRows) . ' item/location balance group(s) are negative.', 'yellow');
        }

        if ($dryRun) {
            $this->printPreview($rows);
            CLI::newLine();
            CLI::write('Dry run finished. Re-run without --dry-run to rebuild balances.', 'cyan');
            return;
        }

        $db->transStart();

        $delete = $db->table('inventory_stock_balances');
        if ($companyId !== null) {
            $delete->where('company_id', $companyId);
        }
        if ($siteId !== null) {
            $delete->where('site_id', $siteId);
        }
        $delete->delete();

        $insertRows = [];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $qtyOnHand = round((float) ($row['qty_on_hand'] ?? 0), 6);
            $stockValue = round((float) ($row['stock_value'] ?? 0), 6);
            $avgCost = abs($qtyOnHand) > 0.000001 ? round($stockValue / $qtyOnHand, 6) : 0.0;

            $payload = [
                'company_id' => (int) $row['company_id'],
                'site_id' => $row['site_id'] !== null ? (int) $row['site_id'] : null,
                'warehouse_id' => (int) $row['warehouse_id'],
                'location_id' => (int) $row['location_id'],
                'item_id' => ! empty($row['item_id']) ? (int) $row['item_id'] : null,
                'item_code' => (string) $row['item_code'],
                'item_name' => (string) ($row['item_name'] ?? $row['item_code']),
                'uom_code' => (string) ($row['uom_code'] ?? 'PCS'),
                'qty_on_hand' => $qtyOnHand,
                'qty_reserved' => 0,
                'qty_available' => $qtyOnHand,
                'avg_cost' => $avgCost,
                'stock_value' => $stockValue,
                'last_movement_date' => $row['last_movement_date'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $insertRows[] = $this->filterExistingFields($db, 'inventory_stock_balances', $payload);
        }

        foreach (array_chunk($insertRows, 200) as $chunk) {
            if ($chunk !== []) {
                $db->table('inventory_stock_balances')->insertBatch($chunk);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            CLI::error('Rebuild failed. Transaction was rolled back.');
            return;
        }

        CLI::write('Rebuild finished successfully.', 'green');
        CLI::write('Inserted balance rows: ' . count($insertRows));
        $this->printPreview(array_slice($rows, 0, 10));
    }

    private function movementRows($db, ?int $companyId, ?int $siteId, string $valueSql): array
    {
        $where = [
            'm.warehouse_id IS NOT NULL',
            'm.location_id IS NOT NULL',
            "m.item_code IS NOT NULL",
            "m.item_code <> ''",
        ];
        $bindings = [];

        if ($companyId !== null) {
            $where[] = 'm.company_id = ?';
            $bindings[] = $companyId;
        }
        if ($siteId !== null) {
            $where[] = 'm.site_id = ?';
            $bindings[] = $siteId;
        }

        $itemIdSelect = $db->fieldExists('item_id', 'inventory_stock_movements') ? 'MAX(m.item_id) AS item_id' : 'NULL AS item_id';
        $uomSelect = $db->fieldExists('uom_code', 'inventory_stock_movements') ? 'MAX(m.uom_code) AS uom_code' : "'PCS' AS uom_code";
        $itemNameSelect = $db->fieldExists('item_name', 'inventory_stock_movements') ? 'MAX(m.item_name) AS item_name' : 'm.item_code AS item_name';
        $dateSelect = $db->fieldExists('movement_date', 'inventory_stock_movements') ? 'MAX(m.movement_date) AS last_movement_date' : 'MAX(m.created_at) AS last_movement_date';

        $sql = "
            SELECT
                m.company_id,
                m.site_id,
                m.warehouse_id,
                m.location_id,
                {$itemIdSelect},
                m.item_code,
                {$itemNameSelect},
                {$uomSelect},
                SUM(CASE WHEN m.direction = 'in' THEN m.qty ELSE -m.qty END) AS qty_on_hand,
                SUM(CASE WHEN m.direction = 'in' THEN {$valueSql} ELSE -{$valueSql} END) AS stock_value,
                {$dateSelect}
            FROM inventory_stock_movements m
            WHERE " . implode(' AND ', $where) . "
            GROUP BY m.company_id, m.site_id, m.warehouse_id, m.location_id, m.item_code
            HAVING ABS(qty_on_hand) > 0.0001 OR ABS(stock_value) > 0.01
            ORDER BY m.company_id, m.site_id, m.warehouse_id, m.location_id, m.item_code
        ";

        return $db->query($sql, $bindings)->getResultArray();
    }

    private function movementValueExpression($db): array
    {
        $candidates = [
            'value_amount' => 'value_amount',
            'stock_value' => 'stock_value',
            'movement_value' => 'movement_value',
            'total_cost' => 'total_cost',
            'line_total' => 'line_total',
        ];

        foreach ($candidates as $field => $label) {
            if ($db->fieldExists($field, 'inventory_stock_movements')) {
                return [
                    'sql' => 'COALESCE(m.' . $field . ', 0)',
                    'label' => $field,
                ];
            }
        }

        if ($db->fieldExists('unit_cost', 'inventory_stock_movements')) {
            return [
                'sql' => 'COALESCE(m.unit_cost, 0) * COALESCE(m.qty, 0)',
                'label' => 'unit_cost * qty',
            ];
        }

        if ($db->fieldExists('avg_cost', 'inventory_stock_movements')) {
            return [
                'sql' => 'COALESCE(m.avg_cost, 0) * COALESCE(m.qty, 0)',
                'label' => 'avg_cost * qty',
            ];
        }

        return [
            'sql' => '0',
            'label' => '0 (no movement value column found)',
        ];
    }

    private function filterExistingFields($db, string $table, array $payload): array
    {
        foreach (array_keys($payload) as $field) {
            if (! $db->fieldExists($field, $table)) {
                unset($payload[$field]);
            }
        }

        return $payload;
    }

    private function positiveInt(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function printPreview(array $rows): void
    {
        CLI::newLine();
        CLI::write('Preview rows:', 'cyan');
        $rows = array_slice($rows, 0, 10);
        foreach ($rows as $row) {
            CLI::write(sprintf(
                '- company:%s site:%s warehouse:%s location:%s item:%s qty:%s value:%s',
                (string) ($row['company_id'] ?? '-'),
                (string) ($row['site_id'] ?? '-'),
                (string) ($row['warehouse_id'] ?? '-'),
                (string) ($row['location_id'] ?? '-'),
                (string) ($row['item_code'] ?? '-'),
                number_format((float) ($row['qty_on_hand'] ?? 0), 4),
                number_format((float) ($row['stock_value'] ?? 0), 2)
            ));
        }
    }
}
