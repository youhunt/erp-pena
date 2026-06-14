<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemLocationModel extends Model
{
    protected $table = 'item_locations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'warehouse_id', 'location_id', 'item_id', 'item_code',
        'min_qty', 'max_qty', 'reorder_qty', 'is_default', 'is_active',
        'created_by', 'updated_by',
    ];
}
