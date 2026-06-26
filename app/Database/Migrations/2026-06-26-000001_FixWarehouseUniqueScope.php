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

        $this->dropOldCompanyCodeUniqueIndexes();
        $this->createDepartmentScopedUniqueIndex();
    }

    public function down(): void
    {
        // Non-destructive migration. Keep the safer department-scoped uniqueness.
    }

    private function dropOldCompanyCodeUniqueIndexes(): void
    {
        $indexes = $this->db->query("SHOW INDEX FROM warehouses WHERE Non_unique = 0 AND Key_name <> 'PRIMARY'")->getResultArray();
        $grouped = [];
        foreach ($indexes as $index) {
            $name = (string) ($index['Key_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $grouped[$name][] = (string) ($index['Column_name'] ?? '');
        }

        foreach ($grouped as $name => $columns) {
            $hasCompany = in_array('company_id', $columns, true);
            $hasCode = in_array('code', $columns, true);
            $hasDepartment = in_array('department_id', $columns, true);
            if ($hasCompany && $hasCode && ! $hasDepartment) {
                $this->db->query('ALTER TABLE warehouses DROP INDEX `' . str_replace('`', '``', $name) . '`');
            }
        }
    }

    private function createDepartmentScopedUniqueIndex(): void
    {
        foreach (['company_id', 'site_id', 'department_id', 'code'] as $column) {
            if (! $this->db->fieldExists($column, 'warehouses')) {
                return;
            }
        }

        $existing = $this->db->query("SHOW INDEX FROM warehouses WHERE Key_name = 'uq_warehouses_company_site_dept_code'")->getResultArray();
        if ($existing !== []) {
            return;
        }

        $this->db->query('ALTER TABLE warehouses ADD UNIQUE KEY uq_warehouses_company_site_dept_code (company_id, site_id, department_id, code)');
    }
}
