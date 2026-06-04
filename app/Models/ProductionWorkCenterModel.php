<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionWorkCenterModel extends Model
{
    protected $table = 'production_work_centers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'site_code', 'department_code', 'warehouse_code',
        'work_center_code', 'description', 'machine_code', 'notes', 'speed',
        'capacity_percent', 'max_length', 'length_uom', 'max_width', 'width_uom',
        'max_height', 'height_uom', 'max_volume', 'volume_uom', 'qty_labor',
        'working_hour', 'cost_type', 'cost_amount', 'cost_uom',
        'active_date', 'inactive_date', 'is_active', 'created_by', 'updated_by',
    ];
}
