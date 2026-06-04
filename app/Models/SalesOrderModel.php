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
        'company_id', 'site_id', 'company', 'site',
        'so_no', 'so_date', 'customer_id', 'customer', 'customer_code', 'customer_name',
        'terms_code', 'currency_code', 'status', 'document_status',
        'subtotal_amount', 'tax_amount', 'discount_amount', 'total_amount',
        'source_document_upload_id', 'notes',
        'submitted_at', 'submitted_by', 'approved_at', 'approved_by',
        'reserved_at', 'reserved_by', 'cancelled_at', 'cancelled_by', 'cancel_reason',
        'created_by', 'updated_by',
    ];
}
