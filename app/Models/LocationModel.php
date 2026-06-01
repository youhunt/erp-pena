<?php

namespace App\Models;

use CodeIgniter\Model;

class LocationModel extends Model
{
    protected $table = 'locations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['company_id', 'site_id', 'warehouse_id', 'code', 'name', 'description', 'is_active', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
