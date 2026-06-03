<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryStockBalanceModel extends Model
{
    protected $table = 'inventory_stock_balances';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'warehouse_id', 'location_id', 'item_id', 'item_code', 'uom_code',
        'qty_on_hand', 'qty_reserved', 'qty_available', 'avg_cost', 'stock_value',
    ];
}
