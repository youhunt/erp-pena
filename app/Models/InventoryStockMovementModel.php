<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryStockMovementModel extends Model
{
    protected $table = 'inventory_stock_movements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'company_id', 'site_id', 'warehouse_id', 'location_id', 'item_id', 'item_code', 'item_name',
        'uom_code', 'movement_date', 'movement_type', 'direction', 'qty', 'unit_cost', 'stock_value',
        'reference_type', 'reference_id', 'reference_no', 'notes', 'created_by', 'created_at',
    ];
}
