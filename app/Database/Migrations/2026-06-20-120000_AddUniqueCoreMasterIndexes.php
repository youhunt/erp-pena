<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUniqueCoreMasterIndexes extends Migration
{
    public function up(): void
    {
        $this->addUniqueIndexIfMissing('customers', 'uniq_customers_company_site_code', ['company_id', 'site_id', 'code']);
        $this->addUniqueIndexIfMissing('suppliers', 'uniq_suppliers_company_site_code', ['company_id', 'site_id', 'code']);
        $this->addUniqueIndexIfMissing('items', 'uniq_items_company_site_code', ['company_id', 'site_id', 'code']);
        $this->addUniqueIndexIfMissing('warehouses', 'uniq_warehouses_company_site_code', ['company_id', 'site_id', 'code']);
        $this->addUniqueIndexIfMissing('locations', 'uniq_locations_company_site_warehouse_code', ['company_id', 'site_id', 'warehouse_id', 'code']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('locations', 'uniq_locations_company_site_warehouse_code');
        $this->dropIndexIfExists('warehouses', 'uniq_warehouses_company_site_code');
        $this->dropIndexIfExists('items', 'uniq_items_company_site_code');
        $this->dropIndexIfExists('suppliers', 'uniq_suppliers_company_site_code');
        $this->dropIndexIfExists('customers', 'uniq_customers_company_site_code');
    }

    /**
     * @param list<string> $columns
     */
    private function addUniqueIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        if ($this->indexExists($table, $indexName)) {
            return;
        }
        foreach ($columns as $column) {
            if (! $this->db->fieldExists($column, $table)) {
                return;
            }
        }

        $fieldList = implode('`, `', $columns);
        $this->db->query("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (`{$fieldList}`)");
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->db->tableExists($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        $this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(1) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        )->getRowArray();

        return (int) ($row['total'] ?? 0) > 0;
    }
}
