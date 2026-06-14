<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseReceiptModel extends Model
{
    protected $table = 'purchase_receipts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'site_id', 'company', 'site', 'receipt_no', 'receipt_date',
        'purchase_order_id', 'po_no', 'supplier_id', 'supplier_code', 'supplier_name',
        'warehouse_id', 'location_id', 'status', 'gl_entry_id', 'notes', 'posted_at', 'posted_by',
        'created_by', 'updated_by',
    ];
}
