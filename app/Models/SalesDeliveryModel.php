<?php

namespace App\Models;

use CodeIgniter\Model;

class SalesDeliveryModel extends Model
{
    protected $table = 'sales_deliveries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'delivery_no', 'delivery_date',
        'sales_order_id', 'so_no', 'customer_id', 'customer_code', 'customer_name',
        'warehouse_id', 'location_id', 'status', 'gl_entry_id', 'reversal_gl_entry_id', 'notes', 'posted_at', 'posted_by',
        'reversed_at', 'reversed_by', 'reversal_reason',
        'created_by', 'updated_by',
    ];
}
