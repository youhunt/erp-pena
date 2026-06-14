<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDepartmentWarehouseItemLocationHierarchy extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('warehouses') && ! $this->db->fieldExists('department_id', 'warehouses')) {
            $this->forge->addColumn('warehouses', [
                'department_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'site_id'],
            ]);
        }

        if (! $this->db->tableExists('item_locations')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'unsigned' => true],
                'site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'warehouse_id' => ['type' => 'INT', 'unsigned' => true],
                'location_id' => ['type' => 'INT', 'unsigned' => true],
                'item_id' => ['type' => 'INT', 'unsigned' => true],
                'item_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'min_qty' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'max_qty' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'reorder_qty' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
                'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'updated_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['company_id', 'site_id', 'location_id', 'item_id'], 'uq_item_locations_scope_location_item');
            $this->forge->addKey(['company_id', 'site_id', 'warehouse_id', 'location_id']);
            $this->forge->addKey(['company_id', 'item_code']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->addForeignKey('site_id', 'sites', 'id', 'SET NULL', 'RESTRICT');
            $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->addForeignKey('location_id', 'locations', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->addForeignKey('item_id', 'items', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->createTable('item_locations');
        }

        $this->backfillWarehouseDepartments();
        $this->ensureWarehouseDepartmentConstraint();
        $this->backfillItemLocations();
    }

    public function down(): void
    {
        if ($this->db->tableExists('item_locations')) {
            $this->forge->dropTable('item_locations', true);
        }

        if ($this->db->tableExists('warehouses') && $this->db->fieldExists('department_id', 'warehouses')) {
            if ($this->foreignKeyExists('warehouses', 'fk_warehouses_department_id')) {
                $this->db->query('ALTER TABLE warehouses DROP FOREIGN KEY fk_warehouses_department_id');
            }
            if ($this->indexExists('warehouses', 'idx_warehouses_department_id')) {
                $this->db->query('ALTER TABLE warehouses DROP INDEX idx_warehouses_department_id');
            }
            $this->forge->dropColumn('warehouses', 'department_id');
        }
    }

    private function backfillWarehouseDepartments(): void
    {
        if (! $this->db->fieldExists('department_id', 'warehouses')) {
            return;
        }

        $warehouses = $this->db->table('warehouses')
            ->select('id, company_id, site_id')
            ->where('department_id IS NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($warehouses as $warehouse) {
            $department = $this->firstDepartment((int) $warehouse['company_id'], $warehouse['site_id'] === null ? null : (int) $warehouse['site_id']);

            if ($department !== null) {
                $this->db->table('warehouses')->where('id', $warehouse['id'])->update(['department_id' => $department['id']]);
            }
        }
    }

    private function firstDepartment(int $companyId, ?int $siteId): ?array
    {
        $department = $this->db->table('departments')
            ->select('id')
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        if ($department !== null) {
            return $department;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('departments')->insert([
            'company_id' => $companyId,
            'site_id' => $siteId,
            'code' => 'GEN',
            'name' => 'General',
            'description' => 'Default department for warehouse hierarchy',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $this->db->insertID()];
    }

    private function ensureWarehouseDepartmentConstraint(): void
    {
        if (! $this->db->fieldExists('department_id', 'warehouses')) {
            return;
        }

        if (! $this->indexExists('warehouses', 'idx_warehouses_department_id')) {
            $this->db->query('ALTER TABLE warehouses ADD INDEX idx_warehouses_department_id (department_id)');
        }

        if (! $this->foreignKeyExists('warehouses', 'fk_warehouses_department_id')) {
            $this->db->query('ALTER TABLE warehouses ADD CONSTRAINT fk_warehouses_department_id FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL ON UPDATE RESTRICT');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return $this->db->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->countAllResults() > 0;
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return $this->db->table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->countAllResults() > 0;
    }

    private function backfillItemLocations(): void
    {
        if (! $this->db->tableExists('item_locations')) {
            return;
        }

        $items = $this->db->table('items')
            ->select('id, company_id, site_id, item_code')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($items as $item) {
            $location = $this->db->table('locations')
                ->select('locations.id AS location_id, locations.warehouse_id')
                ->join('warehouses', 'warehouses.id = locations.warehouse_id', 'inner')
                ->where('locations.company_id', $item['company_id'])
                ->where('locations.site_id', $item['site_id'])
                ->where('locations.deleted_at', null)
                ->orderBy('warehouses.id', 'ASC')
                ->orderBy('locations.id', 'ASC')
                ->get()
                ->getRowArray();

            if ($location === null) {
                continue;
            }

            $exists = $this->db->table('item_locations')
                ->where('company_id', $item['company_id'])
                ->where('site_id', $item['site_id'])
                ->where('location_id', $location['location_id'])
                ->where('item_id', $item['id'])
                ->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('item_locations')->insert([
                'company_id' => $item['company_id'],
                'site_id' => $item['site_id'],
                'warehouse_id' => $location['warehouse_id'],
                'location_id' => $location['location_id'],
                'item_id' => $item['id'],
                'item_code' => $item['item_code'],
                'is_default' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
