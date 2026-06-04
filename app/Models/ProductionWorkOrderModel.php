<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionWorkOrderModel extends Model
{
    protected $table = 'production_work_orders';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'wo_code', 'wo_no', 'wo_date', 'site_code',
        'department_code', 'warehouse_code', 'work_center_code', 'parent_item_id',
        'parent_item_code', 'parent_item_name', 'bom_id', 'routing_id',
        'batch_qty', 'wo_qty', 'std_qty_finished', 'act_qty_finished',
        'description', 'status', 'created_by', 'updated_by',
    ];
}
