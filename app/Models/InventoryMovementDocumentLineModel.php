<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryMovementDocumentLineModel extends Model
{
    protected $table = 'inventory_movement_document_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'document_id', 'stock_movement_id', 'reversal_movement_id', 'line_no', 'item_id', 'item_code', 'item_name', 'batch_no',
        'uom_code', 'system_qty', 'counted_qty', 'qty', 'unit_cost', 'stock_value', 'notes',
    ];
}
