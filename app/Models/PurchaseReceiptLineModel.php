<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseReceiptLineModel extends Model
{
    protected $table = 'purchase_receipt_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'purchase_receipt_id', 'purchase_order_id', 'purchase_order_line_id', 'line_no',
        'item_id', 'item_code', 'item_name', 'qty_received', 'uom_code', 'unit_cost',
        'warehouse_id', 'location_id',
    ];
}
