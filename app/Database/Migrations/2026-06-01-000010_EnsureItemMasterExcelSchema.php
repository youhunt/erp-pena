<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureItemMasterExcelSchema extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('items')) {
            $this->createItemsTable();
            return;
        }

        $fields = [];
        $this->addIfMissing($fields, 'company', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true]);
        $this->addIfMissing($fields, 'site', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true]);
        $this->addIfMissing($fields, 'item_code', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->addIfMissing($fields, 'item_name', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->addIfMissing($fields, 'item_coded', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->addIfMissing($fields, 'item_named', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->addIfMissing($fields, 'shelf_life', ['type' => 'DECIMAL', 'constraint' => '8,0', 'default' => 0, 'null' => true]);
        $this->addIfMissing($fields, 'stockuom', ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true]);
        $this->addIfMissing($fields, 'purchaseuom', ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true]);
        $this->addIfMissing($fields, 'sellinguom', ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true]);
        $this->addIfMissing($fields, 'stockwhs', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true]);
        $this->addIfMissing($fields, 'item_price', ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true]);
        $this->addIfMissing($fields, 'purchasep', ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true]);
        $this->addIfMissing($fields, 'sellingprice', ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true]);
        $this->addIfMissing($fields, 'vat', ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true]);
        foreach (['item_length', 'item_width', 'item_heigh', 'item_diam', 'out_length', 'out_width', 'out_height', 'out_diame'] as $field) {
            $this->addIfMissing($fields, $field, ['type' => 'DECIMAL', 'constraint' => '16,4', 'default' => 0, 'null' => true]);
        }
        foreach (['item_lengt', 'item_widthh', 'item_heigh_uom', 'item_diam_uom', 'out_lengt', 'out_widthh', 'out_height_uom', 'out_diame_uom'] as $field) {
            $this->addIfMissing($fields, $field, ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true]);
        }
        foreach (['item_group', 'item_subg', 'item_class', 'item_subc', 'item_type', 'item_subty', 'item_atribu'] as $field) {
            $this->addIfMissing($fields, $field, ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true]);
        }
        $this->addIfMissing($fields, 'deleted_by', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->addIfMissing($fields, 'active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);

        if ($fields !== []) {
            $this->forge->addColumn('items', $fields);
        }
    }

    public function down(): void
    {
        // No-op: this migration safely aligns older local schemas with the Excel data dictionary.
    }

    private function addIfMissing(array &$fields, string $field, array $definition): void
    {
        if (! $this->db->fieldExists($field, 'items')) {
            $fields[$field] = $definition;
        }
    }

    private function createItemsTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'site_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'company' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'site' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'item_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 50],
            'item_coded' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'item_named' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'shelf_life' => ['type' => 'DECIMAL', 'constraint' => '8,0', 'default' => 0, 'null' => true],
            'stockuom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
            'purchaseuom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
            'sellinguom' => ['type' => 'VARCHAR', 'constraint' => 4, 'null' => true],
            'stockwhs' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'item_price' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true],
            'purchasep' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true],
            'sellingprice' => ['type' => 'DECIMAL', 'constraint' => '20,6', 'default' => 0, 'null' => true],
            'vat' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'item_length' => ['type' => 'DECIMAL', 'constraint' => '16,4', 'default' => 0, 'null' => true],
            'item_width' => ['type' => 'DECIMAL', 'constraint' => '16,4', 'default' => 0, 'null' => true],
            'item_heigh' => ['type' => 'DECIMAL', 'constraint' => '16,4', 'default' => 0, 'null' => true],
            'item_diam' => ['type' => 'DECIMAL', 'constraint' => '16,4', 'default' => 0, 'null' => true],
            'item_lengt' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'item_widthh' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'item_heigh_uom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'item_diam_uom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'out_length' => ['type' => 'DECIMAL', 'constraint' => '16,4', 'default' => 0, 'null' => true],
            'out_width' => ['type' => 'DECIMAL', 'constraint' => '16,4', 'default' => 0, 'null' => true],
            'out_height' => ['type' => 'DECIMAL', 'constraint' => '16,4', 'default' => 0, 'null' => true],
            'out_diame' => ['type' => 'DECIMAL', 'constraint' => '16,4', 'default' => 0, 'null' => true],
            'out_lengt' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'out_widthh' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'out_height_uom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'out_diame_uom' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'item_group' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'item_subg' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'item_class' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'item_subc' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'item_type' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'item_subty' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'item_atribu' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'system'],
            'updated_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'deleted_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['company_id', 'site_id']);
        $this->forge->addKey(['company_id', 'site_id', 'item_code'], false, true, 'uq_items_company_site_item_code');
        $this->forge->createTable('items');
    }
}
