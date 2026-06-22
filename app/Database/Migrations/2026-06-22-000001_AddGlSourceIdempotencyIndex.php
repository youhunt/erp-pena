<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddGlSourceIdempotencyIndex extends Migration
{
    private const INDEX_NAME = 'uq_gl_entries_company_source';

    public function up(): void
    {
        if (! $this->db->tableExists('gl_entries') || $this->indexExists()) {
            return;
        }

        $duplicate = $this->db->query(
            'SELECT company_id, source_module, source_type, source_id, COUNT(*) AS total
             FROM gl_entries
             WHERE source_id IS NOT NULL
             GROUP BY company_id, source_module, source_type, source_id
             HAVING COUNT(*) > 1
             LIMIT 1'
        )->getRowArray();

        if ($duplicate !== null) {
            throw new RuntimeException(sprintf(
                'Duplicate GL source exists for company %s, %s/%s/%s. Resolve it before adding the idempotency index.',
                $duplicate['company_id'],
                $duplicate['source_module'],
                $duplicate['source_type'],
                $duplicate['source_id']
            ));
        }

        $this->db->query(
            'ALTER TABLE `gl_entries` ADD UNIQUE INDEX `' . self::INDEX_NAME . '` '
            . '(`company_id`, `source_module`, `source_type`, `source_id`)'
        );
    }

    public function down(): void
    {
        if ($this->db->tableExists('gl_entries') && $this->indexExists()) {
            $this->db->query('ALTER TABLE `gl_entries` DROP INDEX `' . self::INDEX_NAME . '`');
        }
    }

    private function indexExists(): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['gl_entries', self::INDEX_NAME]
        )->getRowArray();

        return (int) ($row['total'] ?? 0) > 0;
    }
}
