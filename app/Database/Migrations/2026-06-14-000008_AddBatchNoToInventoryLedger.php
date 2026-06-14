<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBatchNoToInventoryLedger extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('inventory_stock_balances')) {
            if (! $this->db->fieldExists('batch_no', 'inventory_stock_balances')) {
                $this->forge->addColumn('inventory_stock_balances', [
                    'batch_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => '', 'after' => 'item_code'],
                ]);
            }

            $this->dropIndexIfExists('inventory_stock_balances', 'uq_inventory_stock_scope_item');
            $this->db->query('ALTER TABLE `inventory_stock_balances` ADD UNIQUE KEY `uq_inventory_stock_scope_item_batch` (`company_id`, `site_id`, `warehouse_id`, `location_id`, `item_code`, `batch_no`)');
            $this->db->query("UPDATE `inventory_stock_balances` SET `batch_no` = '' WHERE `batch_no` IS NULL");
        }

        if ($this->db->tableExists('inventory_stock_movements') && ! $this->db->fieldExists('batch_no', 'inventory_stock_movements')) {
            $this->forge->addColumn('inventory_stock_movements', [
                'batch_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => '', 'after' => 'item_code'],
            ]);
            $this->forge->addKey(['company_id', 'item_code', 'batch_no'], false, false, 'idx_inventory_stock_movements_item_batch');
        }

        if ($this->db->tableExists('inventory_transfer_lines') && ! $this->db->fieldExists('batch_no', 'inventory_transfer_lines')) {
            $this->forge->addColumn('inventory_transfer_lines', [
                'batch_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => '', 'after' => 'item_code'],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('inventory_transfer_lines') && $this->db->fieldExists('batch_no', 'inventory_transfer_lines')) {
            $this->forge->dropColumn('inventory_transfer_lines', 'batch_no');
        }

        if ($this->db->tableExists('inventory_stock_movements') && $this->db->fieldExists('batch_no', 'inventory_stock_movements')) {
            $this->forge->dropColumn('inventory_stock_movements', 'batch_no');
        }

        if ($this->db->tableExists('inventory_stock_balances') && $this->db->fieldExists('batch_no', 'inventory_stock_balances')) {
            $this->dropIndexIfExists('inventory_stock_balances', 'uq_inventory_stock_scope_item_batch');
            $this->forge->dropColumn('inventory_stock_balances', 'batch_no');
            $this->db->query('ALTER TABLE `inventory_stock_balances` ADD UNIQUE KEY `uq_inventory_stock_scope_item` (`company_id`, `site_id`, `warehouse_id`, `location_id`, `item_code`)');
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = $this->db->query(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        )->getRowArray();

        if ((int) ($exists['total'] ?? 0) > 0) {
            $this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
}
