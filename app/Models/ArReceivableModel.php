<?php

namespace App\Models;

use CodeIgniter\Model;

class ArReceivableModel extends Model
{
    protected $table = 'ar_receivables';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'sales_invoice_id', 'invoice_no', 'invoice_date',
        'due_date', 'customer_id', 'customer_code', 'customer_name', 'currency_code',
        'invoice_amount', 'paid_amount', 'outstanding_amount', 'status',
    ];
}
