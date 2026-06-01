<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePenaErpFoundation extends Migration
{
    public function up(): void
    {
        $this->createSetupTables();
        $this->createAccessTables();
        $this->createBusinessPartnerTables();
        $this->createInventoryTables();
        $this->createSalesPurchaseFinanceTables();
        $this->createWorkflowAndAiTables();
    }

    public function down(): void
    {
        foreach ([
            'document_transaction_links',
            'document_processing_logs',
            'document_field_mappings',
            'document_extractions',
            'document_uploads',
            'approval_steps',
            'approval_workflows',
            'audit_trails',
            'invoice_lines',
            'invoices',
            'inventory_movement_lines',
            'inventory_movements',
            'sales_order_lines',
            'sales_orders',
            'purchase_order_lines',
            'purchase_orders',
            'items',
            'suppliers',
            'customers',
            'menu_items',
            'user_site_access',
            'user_company_access',
            'prefix_codes',
            'transaction_codes',
            'uom_conversions',
            'uoms',
            'wht_rates',
            'vat_rates',
            'currencies',
            'cities',
            'provinces',
            'countries',
            'locations',
            'warehouses',
            'departments',
            'sites',
            'companies',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function addAuditFields(bool $softDelete = true): void
    {
        $this->forge->addField([
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        if ($softDelete) {
            $this->forge->addField([
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
        }
    }

    private function addTenantIndexes(bool $site = true): void
    {
        $this->forge->addKey('company_id');

        if ($site) {
            $this->forge->addKey('site_id');
        }
    }

    private function createSetupTables(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 12],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'legal_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'tax_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'base_currency' => ['type' => 'VARCHAR', 'constraint' => 6, 'default' => 'IDR'],
            'address' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->addAuditFields();
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('companies');

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 12],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'address' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->addAuditFields();
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('sites');

        foreach ([
            'departments' => ['company_id', 'site_id', 'code', 'name', 'description'],
            'warehouses' => ['company_id', 'site_id', 'code', 'name', 'description'],
            'locations' => ['company_id', 'site_id', 'warehouse_id', 'code', 'name', 'description'],
        ] as $table => $columns) {
            $fields = [
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'unsigned' => true],
                'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'name' => ['type' => 'VARCHAR', 'constraint' => 255],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ];

            if (in_array('warehouse_id', $columns, true)) {
                $fields['warehouse_id'] = ['type' => 'INT', 'unsigned' => true, 'null' => true];
            }

            $this->forge->addField($fields);
            $this->addAuditFields();
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['company_id', 'site_id', 'code']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->addForeignKey('site_id', 'sites', 'id', 'SET NULL', 'RESTRICT');
            if ($table === 'locations') {
                $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'SET NULL', 'RESTRICT');
            }
            $this->forge->createTable($table);
        }

        foreach ([
            'countries' => 3,
            'provinces' => 12,
            'cities' => 12,
            'currencies' => 6,
        ] as $table => $codeLength) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => $codeLength],
                'name' => ['type' => 'VARCHAR', 'constraint' => 255],
                'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'rounding' => ['type' => 'INT', 'constraint' => 4, 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->addAuditFields();
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('code');
            $this->forge->createTable($table);
        }

        foreach ([
            'vat_rates' => 'VAT',
            'wht_rates' => 'WHT/PPH',
            'uoms' => 'Unit of Measure',
            'transaction_codes' => 'Transaction Code',
            'prefix_codes' => 'Prefix Code',
        ] as $table => $label) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 20],
                'name' => ['type' => 'VARCHAR', 'constraint' => 255],
                'rate' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->addAuditFields();
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['company_id', 'code']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->createTable($table);
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'from_uom_id' => ['type' => 'INT', 'unsigned' => true],
            'to_uom_id' => ['type' => 'INT', 'unsigned' => true],
            'multiplier' => ['type' => 'DECIMAL', 'constraint' => '18,8', 'default' => 1],
            'divider' => ['type' => 'DECIMAL', 'constraint' => '18,8', 'default' => 1],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->addAuditFields();
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'from_uom_id', 'to_uom_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('from_uom_id', 'uoms', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('to_uom_id', 'uoms', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('uom_conversions');
    }

    private function createAccessTables(): void
    {
        foreach (['user_company_access', 'user_site_access'] as $table) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id' => ['type' => 'INT', 'unsigned' => true],
                'company_id' => ['type' => 'INT', 'unsigned' => true],
                'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey($table === 'user_company_access' ? ['user_id', 'company_id'] : ['user_id', 'company_id', 'site_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('site_id', 'sites', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable($table);
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'label' => ['type' => 'VARCHAR', 'constraint' => 100],
            'route' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'permission' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->addAuditFields(false);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['parent_id', 'sort_order']);
        $this->forge->createTable('menu_items');
    }

    private function createBusinessPartnerTables(): void
    {
        foreach ([
            'customers' => 'customer',
            'suppliers' => 'supplier',
        ] as $table => $type) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'unsigned' => true],
                'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'name' => ['type' => 'VARCHAR', 'constraint' => 255],
                'terms_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => true],
                'tax_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'address' => ['type' => 'TEXT', 'null' => true],
                'phone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'email' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->addAuditFields();
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['company_id', 'site_id', 'code']);
            $this->addTenantIndexes();
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->addForeignKey('site_id', 'sites', 'id', 'SET NULL', 'RESTRICT');
            $this->forge->createTable($table);
        }
    }

    private function createInventoryTables(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'item_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'stock'],
            'brand' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'stock_uom_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sales_uom_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'purchase_uom_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'standard_cost' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'sales_price' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'shelf_life_days' => ['type' => 'INT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->addAuditFields();
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'site_id', 'code']);
        $this->addTenantIndexes();
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('stock_uom_id', 'uoms', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('sales_uom_id', 'uoms', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('purchase_uom_id', 'uoms', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('items');
    }

    private function createSalesPurchaseFinanceTables(): void
    {
        $this->createDocumentTablePair('purchase_orders', 'purchase_order_lines', 'supplier_id', 'suppliers');
        $this->createDocumentTablePair('sales_orders', 'sales_order_lines', 'customer_id', 'customers');
        $this->createDocumentTablePair('invoices', 'invoice_lines', 'customer_id', 'customers');
        $this->createInventoryMovementTables();
    }

    private function createDocumentTablePair(string $headerTable, string $lineTable, string $partnerColumn, string $partnerTable): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'unsigned' => true],
            $partnerColumn => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'document_no' => ['type' => 'VARCHAR', 'constraint' => 30],
            'document_date' => ['type' => 'DATE'],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 6, 'default' => 'IDR'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'subtotal_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'remarks' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->addAuditFields();
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'site_id', 'document_no']);
        $this->addTenantIndexes();
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey($partnerColumn, $partnerTable, 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable($headerTable);

        $headerColumn = rtrim($headerTable, 's') . '_id';
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            $headerColumn => ['type' => 'INT', 'unsigned' => true],
            'item_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'qty' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'uom_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey($headerColumn);
        $this->forge->addForeignKey($headerColumn, $headerTable, 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('uom_id', 'uoms', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable($lineTable);
    }

    private function createInventoryMovementTables(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'unsigned' => true],
            'movement_no' => ['type' => 'VARCHAR', 'constraint' => 30],
            'movement_date' => ['type' => 'DATE'],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'remarks' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->addAuditFields();
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'site_id', 'movement_no']);
        $this->addTenantIndexes();
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('inventory_movements');

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'inventory_movement_id' => ['type' => 'INT', 'unsigned' => true],
            'item_id' => ['type' => 'INT', 'unsigned' => true],
            'warehouse_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'location_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'batch_no' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'qty' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'uom_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('inventory_movement_id');
        $this->forge->addForeignKey('inventory_movement_id', 'inventory_movements', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('location_id', 'locations', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->addForeignKey('uom_id', 'uoms', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('inventory_movement_lines');
    }

    private function createWorkflowAndAiTables(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity_id' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'before_data' => ['type' => 'JSON', 'null' => true],
            'after_data' => ['type' => 'JSON', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['company_id', 'site_id', 'entity_type', 'entity_id']);
        $this->forge->createTable('audit_trails');

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'module' => ['type' => 'VARCHAR', 'constraint' => 50],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'min_amount' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->addAuditFields();
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['company_id', 'module', 'document_type']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('approval_workflows');

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'approval_workflow_id' => ['type' => 'INT', 'unsigned' => true],
            'step_order' => ['type' => 'INT', 'default' => 1],
            'role' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'permission' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('approval_workflow_id');
        $this->forge->addForeignKey('approval_workflow_id', 'approval_workflows', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('approval_steps');

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'uploaded_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_path' => ['type' => 'VARCHAR', 'constraint' => 500],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 120],
            'file_size' => ['type' => 'INT', 'unsigned' => true],
            'sha256_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'uploaded'],
            'duplicate_of_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'sha256_hash']);
        $this->addTenantIndexes();
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('document_uploads');

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'document_upload_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'ocr_provider' => ['type' => 'VARCHAR', 'constraint' => 50],
            'ai_provider' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'raw_text' => ['type' => 'LONGTEXT', 'null' => true],
            'structured_json' => ['type' => 'JSON', 'null' => true],
            'confidence_score' => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 0],
            'review_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending_review'],
            'reviewed_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('document_upload_id');
        $this->forge->addForeignKey('document_upload_id', 'document_uploads', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('document_extractions');

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'document_extraction_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'source_field' => ['type' => 'VARCHAR', 'constraint' => 120],
            'target_table' => ['type' => 'VARCHAR', 'constraint' => 120],
            'target_field' => ['type' => 'VARCHAR', 'constraint' => 120],
            'extracted_value' => ['type' => 'TEXT', 'null' => true],
            'corrected_value' => ['type' => 'TEXT', 'null' => true],
            'confidence_score' => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('document_extraction_id');
        $this->forge->addForeignKey('document_extraction_id', 'document_extractions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('document_field_mappings');

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'document_upload_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'level' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'info'],
            'message' => ['type' => 'VARCHAR', 'constraint' => 500],
            'context_json' => ['type' => 'JSON', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('document_upload_id');
        $this->forge->addForeignKey('document_upload_id', 'document_uploads', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('document_processing_logs');

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'document_upload_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'transaction_table' => ['type' => 'VARCHAR', 'constraint' => 120],
            'transaction_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'converted_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'converted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['transaction_table', 'transaction_id']);
        $this->forge->addForeignKey('document_upload_id', 'document_uploads', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('document_transaction_links');
    }
}
