<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddPeriodCloseSiteScope extends Migration
{
    private const OLD_INDEX = 'uq_period_closes_company_module_period';
    private const NEW_INDEX = 'uq_period_closes_scope_module_period';

    public function up(): void
    {
        if (! $this->db->tableExists('period_closes')) {
            return;
        }

        if (! $this->db->fieldExists('site_scope_id', 'period_closes')) {
            $this->forge->addColumn('period_closes', [
                'site_scope_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'default' => 0,
                    'after' => 'site_id',
                ],
            ]);
        }

        $this->db->query('UPDATE period_closes SET site_scope_id = COALESCE(site_id, 0)');

        $duplicate = $this->db->query(
            'SELECT company_id, site_scope_id, module_code, period, COUNT(*) AS total '
            . 'FROM period_closes GROUP BY company_id, site_scope_id, module_code, period '
            . 'HAVING COUNT(*) > 1 LIMIT 1'
        )->getRowArray();
        if ($duplicate !== null) {
            throw new RuntimeException('Duplicate period close scope exists. Resolve duplicate records before migration.');
        }

        if (! $this->indexExists(self::NEW_INDEX)) {
            $this->db->query(
                'ALTER TABLE `period_closes` ADD UNIQUE INDEX `' . self::NEW_INDEX . '` '
                . '(`company_id`, `site_scope_id`, `module_code`, `period`)'
            );
        }
        if ($this->indexExists(self::OLD_INDEX)) {
            $this->db->query('ALTER TABLE `period_closes` DROP INDEX `' . self::OLD_INDEX . '`');
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('period_closes')) {
            return;
        }

        $crossSite = $this->db->query(
            'SELECT company_id, module_code, period, COUNT(*) AS total '
            . 'FROM period_closes GROUP BY company_id, module_code, period HAVING COUNT(*) > 1 LIMIT 1'
        )->getRowArray();
        if ($crossSite !== null) {
            throw new RuntimeException('Cannot roll back period close site scope while cross-site period records exist.');
        }

        if (! $this->indexExists(self::OLD_INDEX)) {
            $this->db->query(
                'ALTER TABLE `period_closes` ADD UNIQUE INDEX `' . self::OLD_INDEX . '` '
                . '(`company_id`, `module_code`, `period`)'
            );
        }
        if ($this->indexExists(self::NEW_INDEX)) {
            $this->db->query('ALTER TABLE `period_closes` DROP INDEX `' . self::NEW_INDEX . '`');
        }
        if ($this->db->fieldExists('site_scope_id', 'period_closes')) {
            $this->forge->dropColumn('period_closes', 'site_scope_id');
        }
    }

    private function indexExists(string $indexName): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['period_closes', $indexName]
        )->getRowArray();

        return (int) ($row['total'] ?? 0) > 0;
    }
}
