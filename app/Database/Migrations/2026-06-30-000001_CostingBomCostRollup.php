<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CostingBomCostRollup extends Migration
{
    public function up(): void
    {
        $this->ensureCostingItemCosts();
        $this->ensureLegacyItemCostColumns();
    }

    public function down(): void
    {
        // Non-destructive ERP migration.
    }

    private function ensureCostingItemCosts(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS costing_item_costs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id INT NULL,
            site_id INT NULL,
            site_code VARCHAR(30) NULL,
            item_code VARCHAR(80) NOT NULL,
            item_name VARCHAR(255) NULL,
            department_code VARCHAR(50) NULL,
            warehouse_code VARCHAR(50) NULL,
            description VARCHAR(500) NULL,
            this_item_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
            bom_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
            work_center_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
            total_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            created_by VARCHAR(50) NULL,
            updated_by VARCHAR(50) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_costing_item_costs_item (company_id, site_id, item_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            'company_id' => 'INT NULL',
            'site_id' => 'INT NULL',
            'site_code' => 'VARCHAR(30) NULL',
            'item_code' => 'VARCHAR(80) NOT NULL',
            'item_name' => 'VARCHAR(255) NULL',
            'department_code' => 'VARCHAR(50) NULL',
            'warehouse_code' => 'VARCHAR(50) NULL',
            'description' => 'VARCHAR(500) NULL',
            'this_item_cost' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            'bom_cost' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            'work_center_cost' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            'wc_cost' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            'total_cost' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'draft'",
            'created_by' => 'VARCHAR(50) NULL',
            'updated_by' => 'VARCHAR(50) NULL',
            'created_at' => 'DATETIME NULL',
            'updated_at' => 'DATETIME NULL',
            'deleted_at' => 'DATETIME NULL',
        ] as $column => $definition) {
            if (! $this->columnExists('costing_item_costs', $column)) {
                $this->db->query('ALTER TABLE costing_item_costs ADD COLUMN `' . $column . '` ' . $definition);
            }
        }
    }

    private function ensureLegacyItemCostColumns(): void
    {
        foreach (['item_cost', 'item_costs'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            foreach ([
                'BomCost' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
                'BOMCost' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
                'TotalCost' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
            ] as $column => $definition) {
                if (! $this->columnExists($table, $column)) {
                    $this->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
                }
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->db->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->countAllResults() > 0;
    }
}
