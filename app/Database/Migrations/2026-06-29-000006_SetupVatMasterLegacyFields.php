<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SetupVatMasterLegacyFields extends Migration
{
    public function up(): void
    {
        $this->ensureVatRates();
        $this->ensureMenuItem();
    }

    public function down(): void
    {
        // Non-destructive ERP migration.
    }

    private function ensureVatRates(): void
    {
        if (! $this->db->tableExists('vat_rates')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'null' => true],
                'site_id' => ['type' => 'INT', 'null' => true],
                'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'vat' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'vatpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
                'scpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
                'otherpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
                'optionalpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
                'gl' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'rate' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by' => ['type' => 'INT', 'null' => true],
                'updated_by' => ['type' => 'INT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'site_id', 'vat'], false, false, 'idx_vat_rates_key');
            $this->forge->createTable('vat_rates', true);
        }

        foreach ([
            'company_id' => ['type' => 'INT', 'null' => true],
            'site_id' => ['type' => 'INT', 'null' => true],
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'vat' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'vatpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'scpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'otherpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'optionalpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'gl' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] as $column => $definition) {
            $this->ensureColumn('vat_rates', $column, $definition);
        }

        $this->db->query("UPDATE vat_rates
            SET vat = COALESCE(NULLIF(vat, ''), code),
                description = COALESCE(NULLIF(description, ''), name),
                vatpctg = CASE WHEN vatpctg IS NULL OR vatpctg = 0 THEN COALESCE(rate, 0) ELSE vatpctg END,
                code = COALESCE(NULLIF(code, ''), vat),
                name = COALESCE(NULLIF(name, ''), description),
                rate = CASE WHEN rate IS NULL OR rate = 0 THEN COALESCE(vatpctg, 0) ELSE rate END");
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

    private function ensureMenuItem(): void
    {
        if (! $this->db->tableExists('menu_items')) {
            return;
        }

        $setup = $this->db->table('menu_items')
            ->where('label', 'Setup')
            ->groupStart()
                ->where('parent_id', null)
                ->orWhere('parent_id', 0)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();

        $now = date('Y-m-d H:i:s');
        if ($setup === null) {
            $this->db->table('menu_items')->insert([
                'parent_id' => 0,
                'label' => 'Setup',
                'route' => '#',
                'icon' => 'bx-slider-alt',
                'permission' => 'setup.master.view',
                'sort_order' => 900,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $setupId = (int) $this->db->insertID();
        } else {
            $setupId = (int) $setup['id'];
        }

        $existing = $this->db->table('menu_items')->where('route', 'setup/vat')->get(1)->getRowArray();
        $payload = [
            'parent_id' => $setupId,
            'label' => 'VAT Master',
            'route' => 'setup/vat',
            'icon' => 'bx-receipt',
            'permission' => 'setup.master.view',
            'sort_order' => 236,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if ($existing) {
            $this->db->table('menu_items')->where('id', (int) $existing['id'])->update($payload);
            return;
        }

        $payload['created_at'] = $now;
        $this->db->table('menu_items')->insert($payload);
    }
}
