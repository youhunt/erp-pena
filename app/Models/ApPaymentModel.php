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
        'cash_bank_code', 'cash_bank_entry_id', 'gl_entry_id', 'reference_no',
        'notes', 'status', 'posted_at', 'posted_by', 'cancelled_at', 'cancelled_by',
        'cancel_reason', 'reversal_cash_bank_entry_id', 'reversal_gl_entry_id',
        'created_by', 'updated_by',
    ];
}
