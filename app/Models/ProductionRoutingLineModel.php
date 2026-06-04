<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionRoutingLineModel extends Model
{
    protected $table = 'production_routing_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'production_routing_id', 'route_no', 'routing_name', 'work_center_code',
        'operation_type', 'hour_qty', 'hour_uom', 'std_speed', 'speed_uom', 'notes',
    ];
}
