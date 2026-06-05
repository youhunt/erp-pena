<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSettlementCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('ap_payments')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'payment_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'payment_date' => ['type' => 'DATE'],
                'ap_payable_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'purchase_invoice_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'supplier_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'supplier_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'supplier_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'payment_amount' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'payment_method' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'bank'],
                'cash_bank_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'reference_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'posted_at' => ['type' => 'DATETIME', 'null' => true],
                'posted_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('ap_payable_id');
            $this->forge->addKey('purchase_invoice_id');
            $this->forge->addKey(['company_id', 'payment_no'], false, true, 'uq_ap_payments_company_no');
            $this->forge->createTable('ap_payments');
        }

        if (! $this->db->tableExists('ar_receipts')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'receipt_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'receipt_date' => ['type' => 'DATE'],
                'ar_receivable_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'sales_invoice_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'customer_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'customer_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'receipt_amount' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'receipt_method' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'bank'],
                'cash_bank_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'reference_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'posted_at' => ['type' => 'DATETIME', 'null' => true],
                'posted_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('ar_receivable_id');
            $this->forge->addKey('sales_invoice_id');
            $this->forge->addKey(['company_id', 'receipt_no'], false, true, 'uq_ar_receipts_company_no');
            $this->forge->createTable('ar_receipts');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('ar_receipts', true);
        $this->forge->dropTable('ap_payments', true);
    }
}
