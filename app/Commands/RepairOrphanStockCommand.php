<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class RepairOrphanStockCommand extends BaseCommand
{
    protected $group = 'Inventory';
    protected $name = 'inventory:repair-orphan-stock';
    protected $description = 'Merge orphan stock balance/movement rows into a valid warehouse/location.';
    protected $usage = 'inventory:repair-orphan-stock --item=ITEM-0003 --warehouse=MAIN --location=A01 [--dry-run]';
    protected $options = [
        '--item' => 'Required item_code to repair.',
        '--warehouse' => 'Target warehouse code. Default: MAIN.',
        '--location' => 'Target location code. Default: A01.',
        '--dry-run' => 'Preview rows without updating data.',
    ];

    public function run(array $params): void
    {
        $db = Database::connect();

        if (! $db->tableExists('inventory_stock_balances') || ! $db->tableExists('inventory_stock_movements')) {
            CLI::error('Required inventory tables are not available.');
            return;
        }

        $itemCode = trim((string) CLI::getOption('item'));
        $warehouseCode = trim((string) (CLI::getOption('warehouse') ?: 'MAIN'));
        $locationCode = trim((string) (CLI::getOption('location') ?: 'A01'));
        $dryRun = CLI::getOption('dry-run') !== null;

        if ($itemCode === '') {
            CLI::error('Missing required option --item=ITEM-CODE');
            return;
        }

        $warehouse = $db->table('warehouses')->where('code', $warehouseCode)->orderBy('id', 'ASC')->get(1)->getRowArray();
        if ($warehouse === null) {
            CLI::error('Warehouse not found: ' . $warehouseCode);
            return;
        }

        $location = $db->table('locations')
            ->where('warehouse_id', (int) $warehouse['id'])
            ->where('code', $locationCode)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();
        if ($location === null) {
            CLI::error('Location not found under ' . $warehouseCode . ': ' . $locationCode);
            return;
        }

        CLI::write('Inventory Orphan Stock Repair', 'green');
        CLI::write('Item      : ' . $itemCode);
        CLI::write('Warehouse : ' . $warehouseCode . ' #' . $warehouse['id']);
        CLI::write('Location  : ' . $locationCode . ' #' . $location['id']);
        CLI::write('Mode      : ' . ($dryRun ? 'DRY RUN' : 'REPAIR'));
        CLI::newLine();

        $orphanBalances = $this->orphanBalances($db, $itemCode);
        $orphanMovements = $this->orphanMovementCount($db, $itemCode);

        CLI::write('Orphan balance rows : ' . count($orphanBalances));
        CLI::write('Orphan movement rows: ' . $orphanMovements);

        $this->printBalanceRows($orphanBalances);

        if ($dryRun) {
            CLI::write('Dry run finished. Re-run without --dry-run to repair.', 'cyan');
            return;
        }

        $db->transStart();

        $targetWarehouseId = (int) $warehouse['id'];
        $targetLocationId = (int) $location['id'];

        foreach ($orphanBalances as $orphan) {
            $batchNo = (string) ($orphan['batch_no'] ?? '');
            $target = $db->table('inventory_stock_balances')
                ->where('company_id', (int) $orphan['company_id'])
                ->where('warehouse_id', $targetWarehouseId)
                ->where('location_id', $targetLocationId)
                ->where('item_code', $itemCode)
                ->groupStart()
                    ->where('batch_no', $batchNo)
                    ->orWhere('batch_no', $batchNo === '' ? null : $batchNo)
                ->groupEnd();

            if ($orphan['site_id'] === null) {
                $target->where('site_id', null);
            } else {
                $target->where('site_id', (int) $orphan['site_id']);
            }

            $targetRow = $target->get(1)->getRowArray();

            if ($targetRow !== null) {
                $newQtyOnHand = (float) ($targetRow['qty_on_hand'] ?? 0) + (float) ($orphan['qty_on_hand'] ?? 0);
                $newQtyReserved = (float) ($targetRow['qty_reserved'] ?? 0) + (float) ($orphan['qty_reserved'] ?? 0);
                $newQtyAvailable = (float) ($targetRow['qty_available'] ?? 0) + (float) ($orphan['qty_available'] ?? 0);
                $newStockValue = (float) ($targetRow['stock_value'] ?? 0) + (float) ($orphan['stock_value'] ?? 0);
                $newAvgCost = abs($newQtyOnHand) > 0.000001 ? round($newStockValue / $newQtyOnHand, 6) : 0.0;

                $payload = [
                    'qty_on_hand' => $newQtyOnHand,
                    'qty_reserved' => $newQtyReserved,
                    'qty_available' => $newQtyAvailable,
                    'stock_value' => $newStockValue,
                    'avg_cost' => $newAvgCost,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $db->table('inventory_stock_balances')
                    ->where('id', (int) $targetRow['id'])
                    ->update($this->filterExistingFields($db, 'inventory_stock_balances', $payload));

                $db->table('inventory_stock_balances')->where('id', (int) $orphan['id'])->delete();
            } else {
                $payload = [
                    'warehouse_id' => $targetWarehouseId,
                    'location_id' => $targetLocationId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $db->table('inventory_stock_balances')
                    ->where('id', (int) $orphan['id'])
                    ->update($this->filterExistingFields($db, 'inventory_stock_balances', $payload));
            }
        }

        $movementPayload = [
            'warehouse_id' => $targetWarehouseId,
            'location_id' => $targetLocationId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db->table('inventory_stock_movements')
            ->where('item_code', $itemCode)
            ->groupStart()
                ->where('warehouse_id', null)
                ->orWhere('location_id', null)
                ->orWhere('warehouse_id', 0)
                ->orWhere('location_id', 0)
            ->groupEnd()
            ->update($this->filterExistingFields($db, 'inventory_stock_movements', $movementPayload));

        $db->transComplete();

        if ($db->transStatus() === false) {
            CLI::error('Repair failed. Transaction was rolled back.');
            return;
        }

        CLI::write('Repair finished successfully.', 'green');
        $this->printBalanceRows($this->currentTargetBalances($db, $itemCode, $targetWarehouseId, $targetLocationId));
    }

    private function orphanBalances($db, string $itemCode): array
    {
        return $db->table('inventory_stock_balances')
            ->where('item_code', $itemCode)
            ->groupStart()
                ->where('warehouse_id', null)
                ->orWhere('location_id', null)
                ->orWhere('warehouse_id', 0)
                ->orWhere('location_id', 0)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function orphanMovementCount($db, string $itemCode): int
    {
        return (int) $db->table('inventory_stock_movements')
            ->where('item_code', $itemCode)
            ->groupStart()
                ->where('warehouse_id', null)
                ->orWhere('location_id', null)
                ->orWhere('warehouse_id', 0)
                ->orWhere('location_id', 0)
            ->groupEnd()
            ->countAllResults();
    }

    private function currentTargetBalances($db, string $itemCode, int $warehouseId, int $locationId): array
    {
        return $db->table('inventory_stock_balances')
            ->where('item_code', $itemCode)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
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

    private function printBalanceRows(array $rows): void
    {
        if ($rows === []) {
            CLI::write('No balance rows to show.', 'yellow');
            return;
        }

        CLI::newLine();
        CLI::write('Balance rows:', 'cyan');
        foreach ($rows as $row) {
            CLI::write(sprintf(
                '- id:%s company:%s site:%s wh:%s loc:%s item:%s batch:%s onhand:%s reserved:%s available:%s',
                (string) ($row['id'] ?? '-'),
                (string) ($row['company_id'] ?? '-'),
                (string) ($row['site_id'] ?? '-'),
                (string) ($row['warehouse_id'] ?? '-'),
                (string) ($row['location_id'] ?? '-'),
                (string) ($row['item_code'] ?? '-'),
                (string) ($row['batch_no'] ?? ''),
                number_format((float) ($row['qty_on_hand'] ?? 0), 4),
                number_format((float) ($row['qty_reserved'] ?? 0), 4),
                number_format((float) ($row['qty_available'] ?? 0), 4)
            ));
        }
    }
}
