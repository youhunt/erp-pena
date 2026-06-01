<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemModel extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'company_id',
        'site_id',
        'code',
        'name',
        'item_type',
        'brand',
        'stock_uom_id',
        'sales_uom_id',
        'purchase_uom_id',
        'standard_cost',
        'sales_price',
        'shelf_life_days',
        'is_active',
        'created_by',
        'updated_by',
    ];
    protected $useTimestamps = true;
}
