<?php

namespace App\Models;

use CodeIgniter\Model;
use RuntimeException;

class WarehouseModel extends Model
{
    protected $table = 'warehouses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $beforeInsert = ['normalizeMaster'];
    protected $beforeUpdate = ['normalizeMaster'];
    protected $allowedFields = ['company_id', 'site_id', 'department_id', 'code', 'name', 'description', 'is_active', 'created_by', 'updated_by'];

    protected function normalizeMaster(array $payload): array
    {
        $data = $payload['data'] ?? [];
        $db = db_connect();

        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $data[$field] = trim($value);
            }
        }

        foreach (array_keys($data) as $field) {
            if (! $db->fieldExists($field, $this->table)) {
                unset($data[$field]);
            }
        }

        if (! empty($data['code'])) {
            $data['code'] = strtoupper((string) $data['code']);
        }

        foreach (['company_id', 'site_id', 'department_id'] as $nullableIntField) {
            if (array_key_exists($nullableIntField, $data) && ($data[$nullableIntField] === '' || $data[$nullableIntField] === 0 || $data[$nullableIntField] === '0')) {
                $data[$nullableIntField] = null;
            }
        }

        if (! array_key_exists('is_active', $data) && $db->fieldExists('is_active', $this->table)) {
            $data['is_active'] = 1;
        }

        $this->validateDepartmentScope($data);

        $payload['data'] = $data;
        return $this->checkDuplicateCode($payload);
    }

    private function validateDepartmentScope(array $data): void
    {
        if (empty($data['department_id'])) {
            return;
        }

        $db = db_connect();
        if (! $db->tableExists('departments')) {
            throw new RuntimeException('Department master table does not exist.');
        }

        $department = $db->table('departments')
            ->where('id', (int) $data['department_id'])
            ->get()
            ->getRowArray();

        if ($department === null) {
            throw new RuntimeException('Selected department is not valid.');
        }

        if ($db->fieldExists('deleted_at', 'departments') && ! empty($department['deleted_at'])) {
            throw new RuntimeException('Selected department is inactive or deleted.');
        }

        if ($db->fieldExists('company_id', 'departments')) {
            $warehouseCompany = (int) ($data['company_id'] ?? 0);
            $departmentCompany = (int) ($department['company_id'] ?? 0);
            if ($warehouseCompany > 0 && $departmentCompany > 0 && $warehouseCompany !== $departmentCompany) {
                throw new RuntimeException('Selected department does not belong to selected company.');
            }
        }

        if ($db->fieldExists('site_id', 'departments')) {
            $warehouseSite = (int) ($data['site_id'] ?? 0);
            $departmentSite = (int) ($department['site_id'] ?? 0);
            if ($warehouseSite > 0 && $departmentSite > 0 && $warehouseSite !== $departmentSite) {
                throw new RuntimeException('Selected department does not belong to selected site.');
            }
        }
    }

    private function checkDuplicateCode(array $payload): array
    {
        $data = $payload['data'] ?? [];
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '' || empty($data['company_id'])) {
            return $payload;
        }

        $db = db_connect();
        $builder = $db->table($this->table)->where('company_id', (int) $data['company_id'])->where('code', $code);
        if ($db->fieldExists('deleted_at', $this->table)) {
            $builder->where('deleted_at', null);
        }
        if (array_key_exists('site_id', $data) && $db->fieldExists('site_id', $this->table)) {
            empty($data['site_id']) ? $builder->where('site_id', null) : $builder->where('site_id', (int) $data['site_id']);
        }
        $id = $payload['id'] ?? null;
        if (is_array($id)) {
            $id = reset($id);
        }
        if ((int) $id > 0) {
            $builder->where('id !=', (int) $id);
        }
        if ($builder->countAllResults() > 0) {
            throw new RuntimeException('Duplicate warehouse code: ' . $code);
        }
        return $payload;
    }
}
