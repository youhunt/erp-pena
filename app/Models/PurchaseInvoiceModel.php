<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseInvoiceModel extends Model
{
    protected $table = 'purchase_invoices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'invoice_no', 'invoice_date', 'due_date',
        'purchase_order_id', 'purchase_receipt_id', 'po_no', 'receipt_no',
        'supplier_id', 'supplier_code', 'supplier_name', 'currency_code', 'status',
        'source_type', 'gl_entry_id', 'subtotal_amount', 'discount_amount', 'tax_amount', 'total_amount',
        'paid_amount', 'outstanding_amount', 'notes', 'posted_at', 'posted_by',
        'cancelled_at', 'cancelled_by', 'cancel_reason', 'reversal_gl_entry_id',
        'created_by', 'updated_by',
    ];
}
