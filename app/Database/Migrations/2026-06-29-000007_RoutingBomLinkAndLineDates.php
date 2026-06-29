<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RoutingBomLinkAndLineDates extends Migration
{
    public function up(): void
    {
        $this->ensureProductionRoutingLineDates();
        $this->ensureBomRoutingLink();
    }

    public function down(): void
    {
        // Non-destructive ERP migration.
    }

    private function ensureProductionRoutingLineDates(): void
    {
        if (! $this->db->tableExists('production_routing_lines')) {
            return;
        }

        $this->ensureColumn('production_routing_lines', 'active_date', ['type' => 'DATE', 'null' => true]);
        $this->ensureColumn('production_routing_lines', 'inactive_date', ['type' => 'DATE', 'null' => true]);

        $this->db->query("UPDATE production_routing_lines SET inactive_date = '9999-12-31' WHERE inactive_date IS NULL");
    }

    private function ensureBomRoutingLink(): void
    {
        if (! $this->db->tableExists('production_boms')) {
            return;
        }

        $this->ensureColumn('production_boms', 'routing_id', ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true]);
        $this->ensureIndex('production_boms', 'idx_production_boms_routing_id', ['routing_id']);
    }

    private function ensureColumn(string $table, string $column, array $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $this->forge->addColumn($table, [$column => $definition]);
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->db->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->countAllResults() > 0;
    }

    private function ensureIndex(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $this->forge->addKey($columns, false, false, $indexName);
        $this->forge->processIndexes($table);
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return (int) $this->db->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->countAllResults() > 0;
    }
}
