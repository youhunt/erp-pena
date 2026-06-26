<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixWarehouseUniqueScope extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('warehouses')) {
            return;
        }

        // Do not drop old unique indexes here. In some databases, the old
        // company/site/code index is reused by a foreign key, so dropping it
        // breaks migration. The department-aware duplicate rule is enforced
        // in WarehouseModel. This migration only adds a helper index for lookup.
        $this->createDepartmentScopedLookupIndex();
    }

    public function down(): void
    {
        // Non-destructive migration.
    }

    private function createDepartmentScopedLookupIndex(): void
    {
        foreach (['company_id', 'site_id', 'department_id', 'code'] as $column) {
            if (! $this->db->fieldExists($column, 'warehouses')) {
                return;
            }
        }

        $existing = $this->db->query("SHOW INDEX FROM warehouses WHERE Key_name = 'idx_warehouses_company_site_dept_code'")->getResultArray();
        if ($existing !== []) {
            return;
        }

        $this->db->query('ALTER TABLE warehouses ADD INDEX idx_warehouses_company_site_dept_code (company_id, site_id, department_id, code)');
    }
}
