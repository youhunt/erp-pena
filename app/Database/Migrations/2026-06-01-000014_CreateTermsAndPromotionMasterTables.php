<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTermsAndPromotionMasterTables extends Migration
{
    public function up(): void
    {
        $this->createTermsTable('customer_terms');
        $this->createTermsTable('supplier_terms');
        $this->createPromotionTable('customer_promotions', 'customer');
        $this->createPromotionTable('supplier_promotions', 'supplier');
    }

    public function down(): void
    {
        foreach (['supplier_promotions', 'customer_promotions', 'supplier_terms', 'customer_terms'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function createTermsTable(string $table): void
    {
        if ($this->db->tableExists($table)) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'terms_code' => ['type' => 'VARCHAR', 'constraint' => 12],
            'terms_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'terms_days' => ['type' => 'INT', 'constraint' => 6, 'default' => 0],
            'promo_code' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->auditFields());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'site_id', 'terms_code']);
        $this->forge->addKey(['company_id', 'site_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable($table);
    }

    private function createPromotionTable(string $table, string $partnerPrefix): void
    {
        if ($this->db->tableExists($table)) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'promo_code' => ['type' => 'VARCHAR', 'constraint' => 12],
            'promo_description' => ['type' => 'VARCHAR', 'constraint' => 1000],
            $partnerPrefix => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            $partnerPrefix . '_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'item_parent' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'item_parent_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'line_no' => ['type' => 'INT', 'constraint' => 4, 'default' => 10],
            'promo_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'percent'],
            'from_qty' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
            'to_qty' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
            'uom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
            'promo_price' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'default' => 0],
            'pct' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0],
            'disc_amount' => ['type' => 'DECIMAL', 'constraint' => '20,12', 'default' => 0],
            'free_item' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'free_item_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'free_qty' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
            'active_date' => ['type' => 'DATE', 'null' => true],
            'active_hour' => ['type' => 'TIME', 'null' => true],
            'inactive_date' => ['type' => 'DATE', 'null' => true],
            'inactive_hour' => ['type' => 'TIME', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->auditFields());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['company_id', 'site_id', 'promo_code', 'line_no']);
        $this->forge->addKey(['company_id', 'site_id', 'promo_code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable($table);
    }

    private function auditFields(): array
    {
        return [
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'updated_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'deleted_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ];
    }
}
