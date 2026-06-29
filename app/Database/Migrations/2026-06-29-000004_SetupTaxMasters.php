<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SetupTaxMasters extends Migration
{
    public function up(): void
    {
        $this->ensureItemVatRates();
        $this->ensureChargeVatRates();
        $this->ensureWhtRates();
    }

    public function down(): void
    {
        // Non-destructive ERP migration.
    }

    private function ensureItemVatRates(): void
    {
        if (! $this->db->tableExists('item_vat_rates')) {
            $this->forge->addField($this->baseFields() + [
                'vatpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
                'scpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
                'whtpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
                'otherpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
                'optionalpctg' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
                'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'vat_rate_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            ] + $this->auditFields());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'site_id', 'vat'], false, false, 'idx_item_vat_rates_key');
            $this->forge->createTable('item_vat_rates', true);
        }

        $this->ensureCommonColumns('item_vat_rates');
        foreach (['vatpctg', 'scpctg', 'whtpctg', 'otherpctg', 'optionalpctg'] as $column) {
            $this->ensureColumn('item_vat_rates', $column, ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0]);
        }
        $this->ensureColumn('item_vat_rates', 'site_id', ['type' => 'INT', 'null' => true]);
        $this->ensureColumn('item_vat_rates', 'item_id', ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true]);
        $this->ensureColumn('item_vat_rates', 'vat_rate_id', ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true]);
    }

    private function ensureChargeVatRates(): void
    {
        if (! $this->db->tableExists('charge_vat_rates')) {
            $this->forge->addField($this->baseFields() + $this->fivePctFields() + $this->auditFields());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'site_id', 'vat'], false, false, 'idx_charge_vat_rates_key');
            $this->forge->createTable('charge_vat_rates', true);
        }

        $this->ensureCommonColumns('charge_vat_rates');
        foreach (array_keys($this->fivePctFields()) as $column) {
            $this->ensureColumn('charge_vat_rates', $column, ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0]);
        }
    }

    private function ensureWhtRates(): void
    {
        if (! $this->db->tableExists('wht_rates')) {
            $this->forge->addField($this->baseFields() + $this->fivePctFields() + [
                'code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'rate' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0],
            ] + $this->auditFields());
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'site_id', 'vat'], false, false, 'idx_wht_rates_key');
            $this->forge->createTable('wht_rates', true);
        }

        $this->ensureCommonColumns('wht_rates');
        foreach (array_keys($this->fivePctFields()) as $column) {
            $this->ensureColumn('wht_rates', $column, ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0]);
        }
        $this->ensureColumn('wht_rates', 'code', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true]);
        $this->ensureColumn('wht_rates', 'name', ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true]);
        $this->ensureColumn('wht_rates', 'rate', ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0]);
    }

    private function ensureCommonColumns(string $table): void
    {
        $this->ensureColumn($table, 'company_id', ['type' => 'INT', 'null' => true]);
        $this->ensureColumn($table, 'site_id', ['type' => 'INT', 'null' => true]);
        $this->ensureColumn($table, 'company', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true]);
        $this->ensureColumn($table, 'site', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true]);
        $this->ensureColumn($table, 'vat', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true]);
        $this->ensureColumn($table, 'description', ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true]);
        $this->ensureColumn($table, 'gl', ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true]);
        $this->ensureColumn($table, 'is_active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
        $this->ensureColumn($table, 'created_by', ['type' => 'INT', 'null' => true]);
        $this->ensureColumn($table, 'updated_by', ['type' => 'INT', 'null' => true]);
        $this->ensureColumn($table, 'created_at', ['type' => 'DATETIME', 'null' => true]);
        $this->ensureColumn($table, 'updated_at', ['type' => 'DATETIME', 'null' => true]);
        $this->ensureColumn($table, 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
    }

    private function ensureColumn(string $table, string $column, array $definition): void
    {
        if ($this->db->fieldExists($column, $table)) {
            return;
        }
        $this->forge->addColumn($table, [$column => $definition]);
    }

    private function baseFields(): array
    {
        return [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'null' => true],
            'site_id' => ['type' => 'INT', 'null' => true],
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'vat' => ['type' => 'VARCHAR', 'constraint' => 12],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'gl' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
        ];
    }

    private function fivePctFields(): array
    {
        return [
            'vatpctg1' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'vatpctg2' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'vatpctg3' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'vatpctg4' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'vatpctg5' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
        ];
    }

    private function auditFields(): array
    {
        return [
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ];
    }
}
