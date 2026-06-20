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
        'purchase_receipt_id', 'purchase_order_id', 'purchase_order_line_id',
        'stock_movement_id', 'reversal_movement_id', 'line_no',
        'item_id', 'item_code', 'batch_no', 'item_name',
        'qty_received', 'reversed_qty', 'uom_code', 'unit_cost',
        'warehouse_id', 'location_id',
        'reversed_at', 'reversed_by', 'reversal_reason',
    ];
}
