<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalesInvoiceCoreTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('sales_invoices')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'invoice_date' => ['type' => 'DATE'],
                'due_date' => ['type' => 'DATE', 'null' => true],
                'sales_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'sales_delivery_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'so_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'delivery_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'customer_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'customer_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'posted'],
                'subtotal_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'total_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'paid_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'outstanding_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
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
            $this->forge->addKey('sales_order_id');
            $this->forge->addKey('sales_delivery_id');
            $this->forge->addKey(['company_id', 'invoice_no'], false, true, 'uq_sales_invoices_company_no');
            $this->forge->createTable('sales_invoices');
        }

        if (! $this->db->tableExists('sales_invoice_lines')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'sales_invoice_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'sales_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'sales_order_line_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'sales_delivery_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'sales_delivery_line_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'line_no' => ['type' => 'INT', 'unsigned' => true],
                'item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'qty_invoiced' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
                'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'line_total' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('sales_invoice_id');
            $this->forge->addKey('sales_order_id');
            $this->forge->addKey('sales_delivery_id');
            $this->forge->createTable('sales_invoice_lines');
        }

        if (! $this->db->tableExists('ar_receivables')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'sales_invoice_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 60],
                'invoice_date' => ['type' => 'DATE'],
                'due_date' => ['type' => 'DATE', 'null' => true],
                'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'customer_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'customer_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
                'invoice_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'paid_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'outstanding_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
                'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'open'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['company_id', 'site_id']);
            $this->forge->addKey('sales_invoice_id');
            $this->forge->addKey(['company_id', 'invoice_no']);
            $this->forge->createTable('ar_receivables');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('ar_receivables', true);
        $this->forge->dropTable('sales_invoice_lines', true);
        $this->forge->dropTable('sales_invoices', true);
    }
}
