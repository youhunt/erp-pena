<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateErpTransactionBackboneMvpTables extends Migration
{
    public function up(): void
    {
        $this->createInventoryMovements();
        $this->createInvoices();
        $this->createPayments();
        $this->createJournalEntries();
        $this->createApprovalRequests();
        $this->createPrintJobs();
        $this->createCoaMappings();
        $this->createMatchingLogs();
    }

    public function down(): void
    {
        foreach ([
            'matching_logs',
            'coa_mappings',
            'print_jobs',
            'approval_actions',
            'approval_requests',
            'journal_entry_lines',
            'journal_entries',
            'payment_allocations',
            'payments',
            'invoice_lines',
            'invoices',
            'inventory_movement_lines',
            'inventory_movements',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function tenantColumns(): array
    {
        return [
            'company_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'site_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
        ];
    }

    private function auditColumns(): array
    {
        return [
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ];
    }

    private function createInventoryMovements(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
        ] + $this->tenantColumns() + [
            'movement_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'movement_date' => ['type' => 'DATE'],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'source_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'warehouse_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'notes' => ['type' => 'TEXT', 'null' => true],
        ] + $this->auditColumns());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'movement_no']);
        $this->forge->addKey(['company_id', 'site_id', 'movement_date']);
        $this->forge->createTable('inventory_movements', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'inventory_movement_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'line_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'PCS'],
            'qty_in' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'qty_out' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'source_line_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('inventory_movement_id');
        $this->forge->addKey(['item_id', 'item_code']);
        $this->forge->createTable('inventory_movement_lines', true);
    }

    private function createInvoices(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
        ] + $this->tenantColumns() + [
            'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'invoice_date' => ['type' => 'DATE'],
            'due_date' => ['type' => 'DATE', 'null' => true],
            'invoice_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'partner_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'partner_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'partner_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
            'subtotal_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'paid_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'balance_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'source_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'notes' => ['type' => 'TEXT', 'null' => true],
        ] + $this->auditColumns());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'invoice_no']);
        $this->forge->addKey(['company_id', 'site_id', 'invoice_type', 'invoice_date']);
        $this->forge->createTable('invoices', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'invoice_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'line_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
            'qty' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 1],
            'uom_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'PCS'],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'line_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'source_line_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('invoice_id');
        $this->forge->createTable('invoice_lines', true);
    }

    private function createPayments(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
        ] + $this->tenantColumns() + [
            'payment_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'payment_date' => ['type' => 'DATE'],
            'payment_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'partner_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'partner_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'partner_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'IDR'],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'allocated_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'unallocated_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'reference_no' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'notes' => ['type' => 'TEXT', 'null' => true],
        ] + $this->auditColumns());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'payment_no']);
        $this->forge->addKey(['company_id', 'site_id', 'payment_type', 'payment_date']);
        $this->forge->createTable('payments', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'payment_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'invoice_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'allocated_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['payment_id', 'invoice_id']);
        $this->forge->createTable('payment_allocations', true);
    }

    private function createJournalEntries(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
        ] + $this->tenantColumns() + [
            'journal_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'journal_date' => ['type' => 'DATE'],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'source_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'total_debit' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'total_credit' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
        ] + $this->auditColumns());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'journal_no']);
        $this->forge->addKey(['company_id', 'site_id', 'journal_date']);
        $this->forge->createTable('journal_entries', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'journal_entry_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'line_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'account_code' => ['type' => 'VARCHAR', 'constraint' => 60],
            'account_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'debit' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'credit' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('journal_entry_id');
        $this->forge->createTable('journal_entry_lines', true);
    }

    private function createApprovalRequests(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
        ] + $this->tenantColumns() + [
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 60],
            'document_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'document_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'requested_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'current_step' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'notes' => ['type' => 'TEXT', 'null' => true],
        ] + $this->auditColumns());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'document_type', 'document_id']);
        $this->forge->createTable('approval_requests', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'approval_request_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'step_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'action' => ['type' => 'VARCHAR', 'constraint' => 30],
            'acted_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'acted_at' => ['type' => 'DATETIME', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('approval_request_id');
        $this->forge->createTable('approval_actions', true);
    }

    private function createPrintJobs(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
        ] + $this->tenantColumns() + [
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 60],
            'document_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'document_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'template_code' => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'default'],
            'printed_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'printed_at' => ['type' => 'DATETIME', 'null' => true],
            'output_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'queued'],
        ] + $this->auditColumns());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'document_type', 'document_id']);
        $this->forge->createTable('print_jobs', true);
    }

    private function createCoaMappings(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
        ] + $this->tenantColumns() + [
            'mapping_type' => ['type' => 'VARCHAR', 'constraint' => 60],
            'mapping_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'account_code' => ['type' => 'VARCHAR', 'constraint' => 60],
            'account_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->auditColumns());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'mapping_type', 'mapping_key']);
        $this->forge->createTable('coa_mappings', true);
    }

    private function createMatchingLogs(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
        ] + $this->tenantColumns() + [
            'match_type' => ['type' => 'VARCHAR', 'constraint' => 60],
            'left_type' => ['type' => 'VARCHAR', 'constraint' => 60],
            'left_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'right_type' => ['type' => 'VARCHAR', 'constraint' => 60],
            'right_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'matched_amount' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'matched'],
            'notes' => ['type' => 'TEXT', 'null' => true],
        ] + $this->auditColumns());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'match_type', 'left_type', 'left_id']);
        $this->forge->addKey(['company_id', 'match_type', 'right_type', 'right_id']);
        $this->forge->createTable('matching_logs', true);
    }
}
