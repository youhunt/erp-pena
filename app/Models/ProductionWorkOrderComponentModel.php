<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionWorkOrderComponentModel extends Model
{
    protected $table = 'production_work_order_components';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'production_work_order_id', 'line_no', 'component_item_id',
        'component_item_code', 'component_item_name', 'qty_used', 'uom_code',
        'warehouse_code', 'location_code', 'batch_no', 'booking_qty',
        'allocated_qty', 'issued_qty', 'line_status',
    ];
}
