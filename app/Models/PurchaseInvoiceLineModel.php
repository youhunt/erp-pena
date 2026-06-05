<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseInvoiceLineModel extends Model
{
    protected $table = 'purchase_invoice_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'purchase_invoice_id', 'purchase_order_id', 'purchase_order_line_id',
        'purchase_receipt_id', 'purchase_receipt_line_id', 'line_no',
        'item_id', 'item_code', 'item_name', 'qty_invoiced', 'uom_code',
        'unit_cost', 'discount_amount', 'tax_amount', 'line_total',
    ];
}
