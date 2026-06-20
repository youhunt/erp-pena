<?php

namespace App\Models;

use CodeIgniter\Model;

class LocationModel extends Model
{
    protected $table = 'locations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $beforeInsert = ['normalizeMaster'];
    protected $beforeUpdate = ['normalizeMaster'];
    protected $allowedFields = ['company_id', 'site_id', 'warehouse_id', 'code', 'name', 'description', 'is_active', 'created_by', 'updated_by'];

    protected function normalizeMaster(array $payload): array
    {
        $data = $payload['data'] ?? [];

        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $data[$field] = trim($value);
            }
        }

        if (! empty($data['code'])) {
            $data['code'] = strtoupper((string) $data['code']);
        }
        if (! empty($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }
        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = 1;
        }

        $payload['data'] = $data;
        return $payload;
    }
}
