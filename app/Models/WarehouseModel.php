<?php

namespace App\Models;

use CodeIgniter\Model;

class WarehouseModel extends Model
{
    protected $table = 'warehouses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['company_id', 'site_id', 'code', 'name', 'description', 'is_active', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
