<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBatchNoToReceiptDeliveryLines extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('purchase_receipt_lines') && ! $this->db->fieldExists('batch_no', 'purchase_receipt_lines')) {
            $this->forge->addColumn('purchase_receipt_lines', [
                'batch_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => '', 'after' => 'item_code'],
            ]);
        }

        if ($this->db->tableExists('sales_delivery_lines') && ! $this->db->fieldExists('batch_no', 'sales_delivery_lines')) {
            $this->forge->addColumn('sales_delivery_lines', [
                'batch_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => '', 'after' => 'item_code'],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('sales_delivery_lines') && $this->db->fieldExists('batch_no', 'sales_delivery_lines')) {
            $this->forge->dropColumn('sales_delivery_lines', 'batch_no');
        }

        if ($this->db->tableExists('purchase_receipt_lines') && $this->db->fieldExists('batch_no', 'purchase_receipt_lines')) {
            $this->forge->dropColumn('purchase_receipt_lines', 'batch_no');
        }
    }
}
