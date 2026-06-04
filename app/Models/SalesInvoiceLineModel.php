<?php

namespace App\Models;

use CodeIgniter\Model;

class SalesInvoiceLineModel extends Model
{
    protected $table = 'sales_invoice_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'sales_invoice_id', 'sales_order_id', 'sales_order_line_id',
        'sales_delivery_id', 'sales_delivery_line_id', 'line_no',
        'item_id', 'item_code', 'item_name', 'qty_invoiced', 'uom_code',
        'unit_price', 'discount_amount', 'tax_amount', 'line_total',
    ];
}
