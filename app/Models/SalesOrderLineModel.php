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
        'sales_order_id', 'line_no', 'item_id', 'item_code', 'item_name', 'qty', 'uom_code',
        'unit_price', 'discount_amount', 'tax_amount', 'line_total',
    ];
}
