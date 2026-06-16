<?php

namespace App\Models;

use CodeIgniter\Model;

class ArReceiptModel extends Model
{
    protected $table = 'ar_receipts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'receipt_no', 'receipt_date', 'ar_receivable_id',
        'sales_invoice_id', 'invoice_no', 'customer_id', 'customer_code',
        'customer_name', 'currency_code', 'receipt_amount', 'receipt_method',
        'cash_bank_code', 'cash_bank_entry_id', 'gl_entry_id', 'reference_no',
        'notes', 'status', 'posted_at', 'posted_by', 'cancelled_at', 'cancelled_by',
        'cancel_reason', 'reversal_cash_bank_entry_id', 'reversal_gl_entry_id',
        'created_by', 'updated_by',
    ];
}
