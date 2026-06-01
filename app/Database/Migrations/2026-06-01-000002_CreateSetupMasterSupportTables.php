<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSetupMasterSupportTables extends Migration
{
    public function up(): void
    {
        $this->createPostalCodesTable();
        $this->createAddressesTable();
        $this->createItemVatRatesTable();
    }

    public function down(): void
    {
        foreach (['item_vat_rates', 'addresses', 'postal_codes'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function auditFields(): array
    {
        return [
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ];
    }

    private function createPostalCodesTable(): void
    {
        if ($this->db->tableExists('postal_codes')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'country_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'province_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'city_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'district' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'village' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->auditFields());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['code', 'city_id']);
        $this->forge->addKey(['country_id', 'province_id', 'city_id']);
        $this->forge->addForeignKey('country_id', 'countries', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('province_id', 'provinces', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('city_id', 'cities', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('postal_codes');
    }

    private function createAddressesTable(): void
    {
        if ($this->db->tableExists('addresses')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'address_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'general'],
            'owner_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'owner_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'country_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'province_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'city_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'postal_code_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'address_line1' => ['type' => 'TEXT', 'null' => true],
            'address_line2' => ['type' => 'TEXT', 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->auditFields());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'site_id', 'code']);
        $this->forge->addKey(['company_id', 'site_id', 'owner_type', 'owner_code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('country_id', 'countries', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('province_id', 'provinces', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('city_id', 'cities', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('postal_code_id', 'postal_codes', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('addresses');
    }

    private function createItemVatRatesTable(): void
    {
        if ($this->db->tableExists('item_vat_rates')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'item_id' => ['type' => 'INT', 'unsigned' => true],
            'vat_rate_id' => ['type' => 'INT', 'unsigned' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->auditFields());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'item_id', 'vat_rate_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('vat_rate_id', 'vat_rates', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('item_vat_rates');
    }
}
