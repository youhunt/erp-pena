<?php

namespace App\Models;

use CodeIgniter\Model;

class SalesOrderLineModel extends Model
{
    protected $table = 'sales_order_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'sales_order_id', 'line_no', 'so_line', 'item_id', 'item_code', 'item_name', 'description',
        'qty', 'qty_ordered', 'qty_reserved', 'qty_delivered', 'qty_outstanding',
        'uom_code', 'unit_price', 'discount_percent', 'discount_amount', 'freight_amount',
        'special_charge_amount', 'other_amount', 'tax_amount', 'line_total', 'line_status',
    ];
}
