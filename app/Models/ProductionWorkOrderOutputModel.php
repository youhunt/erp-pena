<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionWorkOrderOutputModel extends Model
{
    protected $table = 'production_work_order_outputs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'production_work_order_id', 'line_no', 'item_id', 'item_code', 'item_name',
        'qty_good', 'qty_reject', 'uom_code', 'unit_cost', 'warehouse_id', 'location_id',
        'inventory_movement_id',
    ];
}
