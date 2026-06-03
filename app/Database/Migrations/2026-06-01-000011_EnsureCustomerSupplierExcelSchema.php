<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureCustomerSupplierExcelSchema extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('customers')) {
            $this->alignCustomers();
        }

        if ($this->db->tableExists('suppliers')) {
            $this->alignSuppliers();
        }
    }

    public function down(): void
    {
        // No-op: schema repair migration for Excel data dictionary alignment.
    }

    private function alignCustomers(): void
    {
        $fields = [];
        foreach ($this->customerFields() as $field => $definition) {
            $this->addIfMissing($fields, 'customers', $field, $definition);
        }

        if ($fields !== []) {
            $this->forge->addColumn('customers', $fields);
        }
    }

    private function alignSuppliers(): void
    {
        $fields = [];
        foreach ($this->supplierFields() as $field => $definition) {
            $this->addIfMissing($fields, 'suppliers', $field, $definition);
        }

        if ($fields !== []) {
            $this->forge->addColumn('suppliers', $fields);
        }
    }

    private function addIfMissing(array &$fields, string $table, string $field, array $definition): void
    {
        if (! $this->db->fieldExists($field, $table)) {
            $fields[$field] = $definition;
        }
    }

    private function customerFields(): array
    {
        $v12 = ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true];
        $v10 = ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true];
        $v15 = ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true];
        $v50 = ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true];

        return [
            'company' => $v12,
            'site' => $v12,
            'customer' => $v12,
            'customern' => $v50,
            'customerr' => $v50,
            'contactnar' => $v50,
            'description' => $v50,
            'shipwhs' => $v12,
            'officeaddre' => $v10,
            'officecity' => $v50,
            'officeprovir' => $v50,
            'officecount' => $v50,
            'officeposta' => $v10,
            'officeconta' => $v10,
            'officephon' => $v15,
            'officehp' => $v15,
            'taxcode' => $v12,
            'taxnumber' => $v50,
            'vat' => $v12,
            'limitamound' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true],
            'limitqty' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true],
            'terms' => $v12,
            'limitdays' => ['type' => 'INT', 'constraint' => 4, 'default' => 0, 'null' => true],
            'salescode' => $v10,
            'salesname' => $v50,
            'bank1' => $v15,
            'bankaccou' => $v50,
            'bank2' => $v15,
            'bankaccou2' => $v50,
            'billingcust' => $v12,
            'billingtoc' => $v12,
            'billingaddre' => $v10,
            'billingcity' => $v50,
            'billingprovi' => $v50,
            'billingcoun' => $v50,
            'billingposta' => $v10,
            'billingconta' => $v10,
            'billingphon' => $v15,
            'billinghp' => $v15,
            'mailcustom' => $v12,
            'mailcode' => $v12,
            'mailaddres' => $v10,
            'mailcity' => $v50,
            'mailprovin' => $v50,
            'mailcountr' => $v50,
            'mailpostal' => $v10,
            'mailcontac' => $v10,
            'mailphone' => $v15,
            'mailhp' => $v15,
            'shiptocust' => $v12,
            'shiptocode' => $v12,
            'shiptoaddr' => $v10,
            'shiptocity' => $v50,
            'shiptoprovi' => $v50,
            'shiptocour' => $v50,
            'shiptopost' => $v10,
            'shiptocont' => $v10,
            'shiptophon' => $v15,
            'shiptohp' => $v15,
            'deleted_by' => $v50,
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ];
    }

    private function supplierFields(): array
    {
        $v12 = ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true];
        $v10 = ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true];
        $v15 = ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true];
        $v50 = ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true];

        return [
            'company' => $v12,
            'site' => $v12,
            'supplier' => $v12,
            'supplierna' => $v50,
            'supplierref' => $v50,
            'contactnar' => $v50,
            'description' => $v50,
            'officeaddre' => $v10,
            'officecity' => $v50,
            'officeprovir' => $v50,
            'officecoun' => $v50,
            'officeposta' => $v10,
            'officeconta' => $v10,
            'officephon' => $v15,
            'officehp' => $v15,
            'mailaddres' => $v10,
            'mailcity' => $v50,
            'mailprovin' => $v50,
            'mailcountr' => $v50,
            'mailpostal' => $v10,
            'mailcontac' => $v10,
            'mailphone' => $v15,
            'mailhp' => $v15,
            'billingadre' => $v10,
            'billingcity' => $v50,
            'billingprovi' => $v50,
            'billingcoun' => $v50,
            'billingposta' => $v10,
            'billingconta' => $v10,
            'billingphon' => $v15,
            'billinghp' => $v15,
            'taxcode' => $v12,
            'taxnumber' => $v50,
            'vat' => $v12,
            'limitamound' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true],
            'limitqty' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true],
            'terms' => $v12,
            'limitdays' => ['type' => 'INT', 'constraint' => 4, 'default' => 0, 'null' => true],
            'employee' => $v15,
            'purchasing' => $v10,
            'bank1' => $v15,
            'bankaccou' => $v50,
            'bank2' => $v15,
            'bankaccou2' => $v50,
            'shiptoaddr' => $v10,
            'shiptocity' => $v50,
            'shiptoprovi' => $v50,
            'shiptocoun' => $v50,
            'shiptopost' => $v10,
            'shiptocont' => $v10,
            'shiptophon' => $v15,
            'shiptohp' => $v15,
            'deleted_by' => $v50,
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ];
    }
}
