<?php

namespace App\Models;

use CodeIgniter\Model;

class SalesOrderModel extends Model
{
    protected $table = 'sales_orders';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'so_no', 'so_date', 'customer_id', 'customer_name', 'terms_code', 'currency_code',
        'status', 'subtotal_amount', 'tax_amount', 'discount_amount', 'total_amount',
        'source_document_upload_id', 'notes', 'created_by', 'updated_by',
    ];
}
