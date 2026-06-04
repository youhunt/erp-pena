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
        'company_id', 'site_id', 'company', 'site', 'wo_code', 'wo_no', 'wo_date', 'site_code',
        'department_code', 'warehouse_code', 'work_center_code', 'parent_item_id',
        'parent_item_code', 'parent_item_name', 'bom_id', 'routing_id',
        'batch_qty', 'wo_qty', 'std_qty_finished', 'act_qty_finished',
        'production_type', 'finished_item_id', 'finished_item_code', 'finished_item_name', 'uom_code',
        'qty_plan', 'qty_good', 'qty_reject', 'unit_cost', 'warehouse_id', 'location_id',
        'description', 'status', 'notes', 'posted_at', 'posted_by', 'created_by', 'updated_by',
    ];
}
