<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CoreFinanceCashBankMigration extends Migration
{
    public function up(): void
    {
        $this->ensureCashBankAccountColumns();
        $this->ensureCurrenciesTable();
        $this->ensureEmployeesTable();
        $this->ensureCurrencyRatesTable();
        $this->ensureCashBankEntryRateColumns();
        $this->ensureItemImportMappingsTable();
    }

    public function down(): void
    {
        // Core ERP migrations are intentionally non-destructive.
        // Data-bearing master and transaction tables should not be dropped automatically.
    }

    private function ensureCashBankAccountColumns(): void
    {
        if (! $this->db->tableExists('cash_bank_accounts')) {
            return;
        }

        $this->addColumnIfMissing('cash_bank_accounts', 'bank_branch', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'site_id']);
        $this->addColumnIfMissing('cash_bank_accounts', 'bank_code', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'bank_branch']);
        $this->addColumnIfMissing('cash_bank_accounts', 'bank_account', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'cash_bank_name']);
        $this->addColumnIfMissing('cash_bank_accounts', 'pic', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'bank_account']);
        $this->addColumnIfMissing('cash_bank_accounts', 'phone', ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'pic']);
        $this->addColumnIfMissing('cash_bank_accounts', 'address', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'phone']);
    }

    private function ensureCurrenciesTable(): void
    {
        if (! $this->db->tableExists('currencies')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'null' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 6],
                'name' => ['type' => 'VARCHAR', 'constraint' => 500],
                'rounding' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by' => ['type' => 'INT', 'null' => true],
                'updated_by' => ['type' => 'INT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'code'], false, true, 'uq_currencies_company_code');
            $this->forge->createTable('currencies', true);
            return;
        }

        $this->addColumnIfMissing('currencies', 'company_id', ['type' => 'INT', 'null' => true, 'after' => 'id']);
        $this->addColumnIfMissing('currencies', 'rounding', ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0, 'after' => 'name']);
        $this->addColumnIfMissing('currencies', 'is_active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'rounding']);
        $this->addColumnIfMissing('currencies', 'created_by', ['type' => 'INT', 'null' => true, 'after' => 'is_active']);
        $this->addColumnIfMissing('currencies', 'updated_by', ['type' => 'INT', 'null' => true, 'after' => 'created_by']);
        $this->addColumnIfMissing('currencies', 'created_at', ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_by']);
        $this->addColumnIfMissing('currencies', 'updated_at', ['type' => 'DATETIME', 'null' => true, 'after' => 'created_at']);
        $this->addColumnIfMissing('currencies', 'deleted_at', ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at']);
    }

    private function ensureEmployeesTable(): void
    {
        if ($this->db->tableExists('employees')) {
            $this->addColumnIfMissing('employees', 'company_id', ['type' => 'INT', 'null' => true, 'after' => 'id']);
            $this->addColumnIfMissing('employees', 'site_id', ['type' => 'INT', 'null' => true, 'after' => 'company_id']);
            $this->addColumnIfMissing('employees', 'employee_code', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false, 'after' => 'site_id']);
            $this->addColumnIfMissing('employees', 'site_code', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true, 'after' => 'employee_code']);
            $this->addColumnIfMissing('employees', 'department_code', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true, 'after' => 'site_code']);
            $this->addColumnIfMissing('employees', 'description', ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'name']);
            $this->addColumnIfMissing('employees', 'is_active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'description']);
            $this->addAuditColumns('employees');
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'null' => true],
            'site_id' => ['type' => 'INT', 'null' => true],
            'employee_code' => ['type' => 'VARCHAR', 'constraint' => 12],
            'site_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'department_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 500],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('employees', true);
    }

    private function ensureCurrencyRatesTable(): void
    {
        if ($this->db->tableExists('currency_rates')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'null' => true],
            'rate_type' => ['type' => 'VARCHAR', 'constraint' => 12],
            'from_currency' => ['type' => 'VARCHAR', 'constraint' => 6],
            'to_currency' => ['type' => 'VARCHAR', 'constraint' => 6],
            'rate_date' => ['type' => 'DATE'],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('currency_rates', true);
    }

    private function ensureCashBankEntryRateColumns(): void
    {
        if (! $this->db->tableExists('cash_bank_entries')) {
            return;
        }

        $this->addColumnIfMissing('cash_bank_entries', 'rate_type', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true, 'after' => 'currency_code']);
        $this->addColumnIfMissing('cash_bank_entries', 'exchange_rate', ['type' => 'DECIMAL', 'constraint' => '20,12', 'default' => 1, 'after' => 'rate_type']);
        $this->addColumnIfMissing('cash_bank_entries', 'base_currency', ['type' => 'VARCHAR', 'constraint' => 6, 'default' => 'IDR', 'after' => 'exchange_rate']);
        $this->addColumnIfMissing('cash_bank_entries', 'base_amount', ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0, 'after' => 'base_currency']);
    }

    private function ensureItemImportMappingsTable(): void
    {
        if ($this->db->tableExists('item_import_mappings')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'null' => true],
            'site_id' => ['type' => 'INT', 'null' => true],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'purchase_order_import'],
            'imported_item_name' => ['type' => 'VARCHAR', 'constraint' => 300],
            'normalized_imported_name' => ['type' => 'VARCHAR', 'constraint' => 300],
            'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'uom_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('item_import_mappings', true);
    }

    private function addAuditColumns(string $table): void
    {
        $this->addColumnIfMissing($table, 'created_by', ['type' => 'INT', 'null' => true]);
        $this->addColumnIfMissing($table, 'updated_by', ['type' => 'INT', 'null' => true]);
        $this->addColumnIfMissing($table, 'created_at', ['type' => 'DATETIME', 'null' => true]);
        $this->addColumnIfMissing($table, 'updated_at', ['type' => 'DATETIME', 'null' => true]);
        $this->addColumnIfMissing($table, 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function addColumnIfMissing(string $table, string $column, array $definition): void
    {
        if (! $this->db->tableExists($table) || $this->db->fieldExists($column, $table)) {
            return;
        }

        $this->forge->addColumn($table, [$column => $definition]);
    }
}
