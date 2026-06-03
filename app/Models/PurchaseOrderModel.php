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
        'company_id', 'site_id', 'po_no', 'po_date', 'supplier_id', 'supplier_name', 'terms_code', 'currency_code',
        'status', 'subtotal_amount', 'tax_amount', 'discount_amount', 'total_amount',
        'source_document_upload_id', 'notes', 'created_by', 'updated_by',
    ];
}
