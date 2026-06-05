<?php

namespace App\Models;

use CodeIgniter\Model;

class ApPayableModel extends Model
{
    protected $table = 'ap_payables';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'purchase_invoice_id', 'invoice_no', 'invoice_date',
        'due_date', 'supplier_id', 'supplier_code', 'supplier_name', 'currency_code',
        'invoice_amount', 'paid_amount', 'outstanding_amount', 'status',
    ];
}
