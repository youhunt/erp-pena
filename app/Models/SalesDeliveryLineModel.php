<?php

namespace App\Models;

use CodeIgniter\Model;

class SalesDeliveryLineModel extends Model
{
    protected $table = 'sales_delivery_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'sales_delivery_id',
        'sales_order_id',
        'sales_order_line_id',
        'stock_movement_id',
        'reversal_movement_id',
        'line_no',
        'item_id',
        'item_code',
        'batch_no',
        'item_name',
        'qty_delivered',
        'reversed_qty',
        'uom_code',
        'unit_price',
        'warehouse_id',
        'location_id',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];
}
