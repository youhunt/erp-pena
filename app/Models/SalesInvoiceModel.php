<?php

namespace App\Models;

use CodeIgniter\Model;

class SalesInvoiceModel extends Model
{
    protected $table = 'sales_invoices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'invoice_no', 'invoice_date', 'due_date',
        'sales_order_id', 'sales_delivery_id', 'so_no', 'delivery_no',
        'customer_id', 'customer_code', 'customer_name', 'currency_code', 'status',
        'source_type', 'gl_entry_id', 'subtotal_amount', 'discount_amount', 'tax_amount', 'total_amount',
        'paid_amount', 'outstanding_amount', 'notes', 'posted_at', 'posted_by',
        'created_by', 'updated_by',
    ];
}
