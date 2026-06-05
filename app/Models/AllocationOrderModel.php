<?php

namespace App\Models;

use CodeIgniter\Model;

class AllocationOrderModel extends Model
{
    protected $table = 'allocationorder';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $allowedFields = [
        'company_id', 'site_id', 'sales_order_id', 'allocnumb', 'allocdate',
        'site', 'customer', 'customern', 'shipdate', 'shipto', 'dept', 'whs',
        'remarks', 'status', 'posted_at', 'posted_by', 'created_by',
        'updated_by', 'deleted_by', 'active',
    ];
}
