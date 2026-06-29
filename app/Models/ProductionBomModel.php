<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionBomModel extends Model
{
    protected $table = 'production_boms';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'site_code', 'department_code', 'warehouse_code',
        'parent_item_id', 'parent_item_code', 'parent_item_name', 'bom_type', 'routing_id',
        'qty_batch', 'uom_code', 'ratio_percent', 'description',
        'active_date', 'inactive_date', 'is_active', 'created_by', 'updated_by',
    ];
    protected $beforeInsert = ['syncRoutingFromRequest'];
    protected $beforeUpdate = ['syncRoutingFromRequest'];

    protected function syncRoutingFromRequest(array $data): array
    {
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        $value = service('request')->getPost('routing_id');
        if ($value !== null) {
            $data['data']['routing_id'] = trim((string) $value) !== '' ? (int) $value : null;
        }

        return $data;
    }
}
