<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPoVatWhtCodes extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('purchase_orders')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('vat_code', 'purchase_orders')) {
            $fields['vat_code'] = [
                'type' => 'VARCHAR',
                'constraint' => 60,
                'null' => true,
                'after' => 'special_charge_amount',
            ];
        }
        if (! $this->db->fieldExists('wht_code', 'purchase_orders')) {
            $fields['wht_code'] = [
                'type' => 'VARCHAR',
                'constraint' => 60,
                'null' => true,
                'after' => 'vat_code',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('purchase_orders', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('purchase_orders')) {
            return;
        }
        if ($this->db->fieldExists('wht_code', 'purchase_orders')) {
            $this->forge->dropColumn('purchase_orders', 'wht_code');
        }
        if ($this->db->fieldExists('vat_code', 'purchase_orders')) {
            $this->forge->dropColumn('purchase_orders', 'vat_code');
        }
    }
}
