<?php

namespace App\Models;

use CodeIgniter\Model;

class WorkCenterCostModel extends Model
{
    protected $table = 'work_center_cost';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $allowedFields = [
        'company_id', 'site_id', 'work_center_id', 'work_center', 'costtype',
        'costamount', 'costuom', 'notes2', 'created_by', 'updated_by',
        'deleted_by', 'active',
    ];
}
