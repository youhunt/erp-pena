<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseOrderModel extends Model
{
    protected $table = 'purchase_orders';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site',
        'document_no', 'document_date',
        'po_no', 'po_date', 'delivery_date', 'arrive_date',
        'supplier_id', 'supplier', 'supplier_code', 'supplier_name',
        'terms_code', 'currency_code', 'status', 'document_status',
        'subtotal_amount', 'discount_percent', 'discount_amount', 'freight_amount',
        'other_amount', 'special_charge_amount', 'vat_amount', 'wht_amount',
        'tax_amount', 'total_amount',
        'source_document_upload_id', 'notes', 'remarks',
        'submitted_at', 'submitted_by', 'approved_at', 'approved_by',
        'closed_at', 'closed_by', 'cancelled_at', 'cancelled_by', 'cancel_reason',
        'created_by', 'updated_by',
    ];
}
