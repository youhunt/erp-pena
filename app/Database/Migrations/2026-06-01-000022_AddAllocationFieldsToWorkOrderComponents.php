<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAllocationFieldsToWorkOrderComponents extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('production_work_order_components')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('allocated_qty', 'production_work_order_components')) {
            $fields['allocated_qty'] = ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0, 'after' => 'booking_qty'];
        }
        if (! $this->db->fieldExists('issued_qty', 'production_work_order_components')) {
            $fields['issued_qty'] = ['type' => 'DECIMAL', 'constraint' => '24,12', 'default' => 0, 'after' => 'allocated_qty'];
        }
        if (! $this->db->fieldExists('line_status', 'production_work_order_components')) {
            $fields['line_status'] = ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open', 'after' => 'issued_qty'];
        }

        if ($fields !== []) {
            $this->forge->addColumn('production_work_order_components', $fields);
        }
    }

    public function down(): void
    {
    }
}
