<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionRoutingModel extends Model
{
    protected $table = 'production_routings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'site_code', 'department_code', 'warehouse_code',
        'item_id', 'item_code', 'description', 'is_active', 'created_by', 'updated_by',
    ];
}
