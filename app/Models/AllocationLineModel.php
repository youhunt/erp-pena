<?php

namespace App\Models;

use CodeIgniter\Model;

class AllocationLineModel extends Model
{
    protected $table = 'allocationline';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $allowedFields = [
        'company_id', 'site_id', 'allocationorder_id', 'sales_order_id',
        'sales_order_line_id', 'allocate', 'site', 'customer', 'customern',
        'line', 'soprefix', 'salesorder', 'transcode', 'soline', 'itemcode',
        'itemname', 'soqty', 'souom', 'whs', 'loc', 'batchno', 'stockqty',
        'stockuom', 'availableqty', 'availableuom', 'allocateqty',
        'allocateuom', 'delivered_qty', 'delivery_line_id', 'shipto', 'description',
        'created_by', 'updated_by', 'deleted_by', 'active',
    ];
}
