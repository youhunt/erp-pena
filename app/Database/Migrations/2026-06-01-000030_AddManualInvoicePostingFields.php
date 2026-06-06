<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddManualInvoicePostingFields extends Migration
{
    public function up(): void
    {
        $this->addFields('purchase_invoices');
        $this->addFields('sales_invoices');
    }

    public function down(): void
    {
        $this->dropFields('sales_invoices');
        $this->dropFields('purchase_invoices');
    }

    private function addFields(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('source_type', $table)) {
            $fields['source_type'] = ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'system', 'after' => 'status'];
        }
        if (! $this->db->fieldExists('gl_entry_id', $table)) {
            $fields['gl_entry_id'] = ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'source_type'];
        }

        if ($fields !== []) {
            $this->forge->addColumn($table, $fields);
        }
    }

    private function dropFields(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach (['gl_entry_id', 'source_type'] as $field) {
            if ($this->db->fieldExists($field, $table)) {
                $this->forge->dropColumn($table, $field);
            }
        }
    }
}
