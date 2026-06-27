<?php

namespace App\Services\Setup;

use Config\Database;
use Throwable;

class SiteBootstrapService
{
    public function bootstrapSite(int $siteId, ?int $userId = null): void
    {
        if ($siteId < 1) {
            return;
        }

        $db = Database::connect();
        if (! $db->tableExists('sites')) {
            return;
        }

        $site = $db->table('sites')->where('id', $siteId)->get(1)->getRowArray();
        if ($site === null || empty($site['company_id'])) {
            return;
        }

        $companyId = (int) $site['company_id'];
        $now = date('Y-m-d H:i:s');

        try {
            (new CompanyBootstrapService())->bootstrapCompany($companyId, $userId);
            $departmentId = $this->ensureDepartment($companyId, $siteId, $now, $userId);
            $warehouseId = $this->ensureWarehouse($companyId, $siteId, $departmentId, $now, $userId);
            $this->ensureLocation($companyId, $siteId, $warehouseId, $now, $userId);
        } catch (Throwable) {
            // Site bootstrap must not block site creation. Core Health will expose missing structure.
        }
    }

    private function ensureDepartment(int $companyId, int $siteId, string $now, ?int $userId): ?int
    {
        $db = Database::connect();
        if (! $db->tableExists('departments')) {
            return null;
        }

        $existing = $db->table('departments')
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->where('code', 'GEN')
            ->get(1)
            ->getRowArray();

        $payload = $this->filterColumns('departments', [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'code' => 'GEN',
            'name' => 'General Department',
            'description' => 'Default department created automatically for initial transactions.',
            'is_active' => 1,
            'updated_by' => $userId,
            'updated_at' => $now,
        ]);

        if ($existing !== null) {
            $db->table('departments')->where('id', (int) $existing['id'])->update($payload);
            return (int) $existing['id'];
        }

        $payload['created_by'] = $userId;
        $payload['created_at'] = $now;
        $db->table('departments')->insert($this->filterColumns('departments', $payload));

        return (int) $db->insertID();
    }

    private function ensureWarehouse(int $companyId, int $siteId, ?int $departmentId, string $now, ?int $userId): ?int
    {
        $db = Database::connect();
        if (! $db->tableExists('warehouses')) {
            return null;
        }

        $builder = $db->table('warehouses')
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->where('code', 'MAIN');
        if ($db->fieldExists('deleted_at', 'warehouses')) {
            $builder->where('deleted_at', null);
        }
        $existing = $builder->get(1)->getRowArray();

        $payload = $this->filterColumns('warehouses', [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'department_id' => $departmentId,
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'description' => 'Default warehouse created automatically for initial inventory transactions.',
            'is_active' => 1,
            'updated_by' => $userId,
            'updated_at' => $now,
        ]);

        if ($existing !== null) {
            $db->table('warehouses')->where('id', (int) $existing['id'])->update($payload);
            return (int) $existing['id'];
        }

        $payload['created_by'] = $userId;
        $payload['created_at'] = $now;
        $db->table('warehouses')->insert($this->filterColumns('warehouses', $payload));

        return (int) $db->insertID();
    }

    private function ensureLocation(int $companyId, int $siteId, ?int $warehouseId, string $now, ?int $userId): ?int
    {
        if ($warehouseId === null || $warehouseId < 1) {
            return null;
        }

        $db = Database::connect();
        if (! $db->tableExists('locations')) {
            return null;
        }

        $builder = $db->table('locations')
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->where('warehouse_id', $warehouseId)
            ->where('code', 'MAIN');
        if ($db->fieldExists('deleted_at', 'locations')) {
            $builder->where('deleted_at', null);
        }
        $existing = $builder->get(1)->getRowArray();

        $payload = $this->filterColumns('locations', [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'warehouse_id' => $warehouseId,
            'code' => 'MAIN',
            'name' => 'Main Location',
            'description' => 'Default location created automatically for initial inventory transactions.',
            'is_active' => 1,
            'updated_by' => $userId,
            'updated_at' => $now,
        ]);

        if ($existing !== null) {
            $db->table('locations')->where('id', (int) $existing['id'])->update($payload);
            return (int) $existing['id'];
        }

        $payload['created_by'] = $userId;
        $payload['created_at'] = $now;
        $db->table('locations')->insert($this->filterColumns('locations', $payload));

        return (int) $db->insertID();
    }

    private function filterColumns(string $table, array $payload): array
    {
        $db = Database::connect();
        foreach (array_keys($payload) as $column) {
            if (! $db->fieldExists($column, $table)) {
                unset($payload[$column]);
            }
        }

        return $payload;
    }
}
