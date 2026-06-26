<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureDefaultWarehouseLocations extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('warehouses') || ! $this->db->tableExists('locations')) {
            return;
        }

        $warehouseBuilder = $this->db->table('warehouses');
        if ($this->db->fieldExists('deleted_at', 'warehouses')) {
            $warehouseBuilder->where('deleted_at', null);
        }
        if ($this->db->fieldExists('is_active', 'warehouses')) {
            $warehouseBuilder->where('is_active', 1);
        }

        $warehouses = $warehouseBuilder->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($warehouses as $warehouse) {
            $warehouseId = (int) ($warehouse['id'] ?? 0);
            if ($warehouseId < 1 || $this->hasLocation($warehouseId)) {
                continue;
            }

            $now = date('Y-m-d H:i:s');
            $locationCode = $this->availableLocationCode($warehouse);
            $row = [
                'warehouse_id' => $warehouseId,
                'code' => $locationCode,
                'name' => $locationCode . ' Location',
                'description' => 'Default location for ' . (string) ($warehouse['code'] ?? ('warehouse #' . $warehouseId)),
            ];

            foreach (['company_id', 'site_id'] as $field) {
                if ($this->db->fieldExists($field, 'locations') && array_key_exists($field, $warehouse)) {
                    $row[$field] = $warehouse[$field];
                }
            }
            if ($this->db->fieldExists('is_active', 'locations')) {
                $row['is_active'] = 1;
            }
            if ($this->db->fieldExists('created_by', 'locations')) {
                $row['created_by'] = 1;
            }
            if ($this->db->fieldExists('updated_by', 'locations')) {
                $row['updated_by'] = 1;
            }
            if ($this->db->fieldExists('created_at', 'locations')) {
                $row['created_at'] = $now;
            }
            if ($this->db->fieldExists('updated_at', 'locations')) {
                $row['updated_at'] = $now;
            }

            $this->db->table('locations')->insert($row);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('locations')) {
            return;
        }

        $builder = $this->db->table('locations')
            ->like('description', 'Default location for', 'after');

        $builder->delete();
    }

    private function hasLocation(int $warehouseId): bool
    {
        $locationBuilder = $this->db->table('locations')->where('warehouse_id', $warehouseId);
        if ($this->db->fieldExists('deleted_at', 'locations')) {
            $locationBuilder->where('deleted_at', null);
        }
        if ($this->db->fieldExists('is_active', 'locations')) {
            $locationBuilder->where('is_active', 1);
        }

        return $locationBuilder->countAllResults() > 0;
    }

    private function availableLocationCode(array $warehouse): string
    {
        $companyId = (int) ($warehouse['company_id'] ?? 0);
        $siteId = (int) ($warehouse['site_id'] ?? 0);
        $warehouseId = (int) ($warehouse['id'] ?? 0);
        $warehouseCode = strtoupper(trim((string) ($warehouse['code'] ?? '')));
        $base = preg_replace('/[^A-Z0-9-]/', '', $warehouseCode) ?: ('WH' . $warehouseId);
        $candidates = array_unique([$base, $base . '-LOC', 'LOC' . $warehouseId]);

        foreach ($candidates as $candidate) {
            if (! $this->locationCodeExists($candidate, $companyId, $siteId)) {
                return substr($candidate, 0, 50);
            }
        }

        return substr('LOC' . $warehouseId . '-' . time(), 0, 50);
    }

    private function locationCodeExists(string $code, int $companyId, int $siteId): bool
    {
        $builder = $this->db->table('locations')->where('code', $code);
        if ($this->db->fieldExists('company_id', 'locations')) {
            $builder->where('company_id', $companyId);
        }
        if ($this->db->fieldExists('site_id', 'locations')) {
            $siteId > 0 ? $builder->where('site_id', $siteId) : $builder->where('site_id', null);
        }
        if ($this->db->fieldExists('deleted_at', 'locations')) {
            $builder->where('deleted_at', null);
        }

        return $builder->countAllResults() > 0;
    }
}
