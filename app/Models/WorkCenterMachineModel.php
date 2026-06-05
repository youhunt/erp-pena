<?php

namespace App\Models;

use CodeIgniter\Model;

class WorkCenterMachineModel extends Model
{
    protected $table = 'work_center_machine';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $allowedFields = [
        'company_id', 'site_id', 'work_center_id', 'site', 'dept', 'warehouse',
        'work_center', 'no', 'machine', 'notes1', 'speed', 'capacity',
        'length', 'luom', 'width', 'wuom', 'height', 'huom', 'volume', 'vuom',
        'qtylabor', 'workhour', 'created_by', 'updated_by', 'deleted_by', 'active',
    ];
}
