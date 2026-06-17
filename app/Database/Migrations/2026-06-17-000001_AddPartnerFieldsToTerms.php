<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPartnerFieldsToTerms extends Migration
{
    public function up(): void
    {
        $this->addCustomerFields();
        $this->addSupplierFields();
    }

    public function down(): void
    {
        if ($this->db->tableExists('customer_terms')) {
            foreach (['customer_name', 'customer'] as $field) {
                if ($this->db->fieldExists($field, 'customer_terms')) {
                    $this->forge->dropColumn('customer_terms', $field);
                }
            }
        }

        if ($this->db->tableExists('supplier_terms')) {
            foreach (['supplier_name', 'supplier'] as $field) {
                if ($this->db->fieldExists($field, 'supplier_terms')) {
                    $this->forge->dropColumn('supplier_terms', $field);
                }
            }
        }
    }

    private function addCustomerFields(): void
    {
        if (! $this->db->tableExists('customer_terms')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('customer', 'customer_terms')) {
            $fields['customer'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'site'];
        }
        if (! $this->db->fieldExists('customer_name', 'customer_terms')) {
            $fields['customer_name'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'customer'];
        }

        if ($fields !== []) {
            $this->forge->addColumn('customer_terms', $fields);
        }
    }

    private function addSupplierFields(): void
    {
        if (! $this->db->tableExists('supplier_terms')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('supplier', 'supplier_terms')) {
            $fields['supplier'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'site'];
        }
        if (! $this->db->fieldExists('supplier_name', 'supplier_terms')) {
            $fields['supplier_name'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'supplier'];
        }

        if ($fields !== []) {
            $this->forge->addColumn('supplier_terms', $fields);
        }
    }
}
