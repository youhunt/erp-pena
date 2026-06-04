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
        'parent_item_id', 'parent_item_code', 'parent_item_name', 'bom_type',
        'qty_batch', 'uom_code', 'ratio_percent', 'description',
        'active_date', 'inactive_date', 'is_active', 'created_by', 'updated_by',
    ];
}
