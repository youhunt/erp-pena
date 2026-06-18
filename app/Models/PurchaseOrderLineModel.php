<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseOrderLineModel extends Model
{
    protected $table = 'purchase_order_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'purchase_order_id', 'line_no', 'po_line',
        'item_id', 'item_code', 'item_name', 'description',
        'qty', 'qty_ordered', 'qty_received', 'qty_outstanding',
        'uom_code', 'unit_price', 'line_total', 'line_status',
    ];
}
