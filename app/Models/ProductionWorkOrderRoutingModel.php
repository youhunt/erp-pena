<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionWorkOrderRoutingModel extends Model
{
    protected $table = 'production_work_order_routings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'production_work_order_id', 'line_no', 'routing_name', 'work_center_code',
        'work_center_name', 'hour_qty', 'uom_code',
    ];
}
