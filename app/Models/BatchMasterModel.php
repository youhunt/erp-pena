<?php

namespace App\Models;

use CodeIgniter\Model;

class BatchMasterModel extends Model
{
    protected $table = 'batch_masters';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'warehouse_id', 'location_id', 'whs', 'loc',
        'item_id', 'item_code', 'batch_no', 'batch_name',
        'production_date', 'expiry_date', 'supplier_lot_no', 'manufacturer_lot_no',
        'stock_qty', 'allocation_qty', 'available_qty',
        'description', 'is_active', 'created_by', 'updated_by',
    ];
}
