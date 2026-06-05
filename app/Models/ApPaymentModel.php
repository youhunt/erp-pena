<?php

namespace App\Models;

use CodeIgniter\Model;

class ApPaymentModel extends Model
{
    protected $table = 'ap_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'payment_no', 'payment_date', 'ap_payable_id',
        'purchase_invoice_id', 'invoice_no', 'supplier_id', 'supplier_code',
        'supplier_name', 'currency_code', 'payment_amount', 'payment_method',
        'cash_bank_code', 'reference_no', 'notes', 'posted_at', 'posted_by',
        'created_by', 'updated_by',
    ];
}
