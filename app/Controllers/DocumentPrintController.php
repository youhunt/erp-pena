<?php

namespace App\Controllers;

use App\Services\TenantContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;

class DocumentPrintController extends BaseController
{
    private array $configs = [
        'purchase-order' => [
            'title' => 'Purchase Order',
            'table' => 'purchase_orders',
            'line_table' => 'purchase_order_lines',
            'line_fk' => 'purchase_order_id',
            'number' => 'po_no',
            'date' => 'po_date',
            'date_label' => 'PO Date',
            'partner_label' => 'Supplier',
            'partner_code' => 'supplier_code',
            'partner_name' => 'supplier_name',
            'source_label' => '',
            'source_field' => '',
            'qty' => 'qty_ordered',
            'qty_label' => 'Qty',
            'price' => 'unit_price',
            'price_label' => 'Unit Price',
            'show_amounts' => true,
            'show_line_commercial' => false,
            'show_header_commercial' => true,
            'show_unit_price' => true,
            'signatures' => ['Prepared By', 'Checked By', 'Approved By'],
        ],
        'sales-order' => [
            'title' => 'Sales Order',
            'table' => 'sales_orders',
            'line_table' => 'sales_order_lines',
            'line_fk' => 'sales_order_id',
            'number' => 'so_no',
            'date' => 'so_date',
            'date_label' => 'SO Date',
            'partner_label' => 'Customer',
            'partner_code' => 'customer_code',
            'partner_name' => 'customer_name',
            'source_label' => '',
            'source_field' => '',
            'qty' => 'qty_ordered',
            'qty_label' => 'Qty',
            'price' => 'unit_price',
            'price_label' => 'Unit Price',
            'show_amounts' => true,
            'show_line_commercial' => true,
            'show_header_commercial' => false,
            'show_unit_price' => true,
            'signatures' => ['Prepared By', 'Checked By', 'Approved By'],
        ],
        'purchase-receipt' => [
            'title' => 'Purchase Receipt',
            'table' => 'purchase_receipts',
            'line_table' => 'purchase_receipt_lines',
            'line_fk' => 'purchase_receipt_id',
            'number' => 'receipt_no',
            'date' => 'receipt_date',
            'date_label' => 'Receipt Date',
            'partner_label' => 'Supplier',
            'partner_code' => 'supplier_code',
            'partner_name' => 'supplier_name',
            'source_label' => 'PO No',
            'source_field' => 'po_no',
            'qty' => 'qty_received',
            'qty_label' => 'Received Qty',
            'price' => 'unit_cost',
            'price_label' => 'Unit Cost',
            'show_amounts' => false,
            'show_line_commercial' => false,
            'show_header_commercial' => false,
            'show_unit_price' => false,
            'signatures' => ['Received By', 'Checked By', 'Warehouse'],
        ],
        'sales-delivery' => [
            'title' => 'Delivery Order',
            'table' => 'sales_deliveries',
            'line_table' => 'sales_delivery_lines',
            'line_fk' => 'sales_delivery_id',
            'number' => 'delivery_no',
            'date' => 'delivery_date',
            'date_label' => 'Delivery Date',
            'partner_label' => 'Customer',
            'partner_code' => 'customer_code',
            'partner_name' => 'customer_name',
            'source_label' => 'SO No',
            'source_field' => 'so_no',
            'qty' => 'qty_delivered',
            'qty_label' => 'Delivered Qty',
            'price' => 'unit_price',
            'price_label' => 'Unit Price',
            'show_amounts' => false,
            'show_line_commercial' => false,
            'show_header_commercial' => false,
            'show_unit_price' => false,
            'signatures' => ['Prepared By', 'Delivered By', 'Received By'],
        ],
        'purchase-invoice' => [
            'title' => 'Purchase Invoice',
            'table' => 'purchase_invoices',
            'line_table' => 'purchase_invoice_lines',
            'line_fk' => 'purchase_invoice_id',
            'number' => 'invoice_no',
            'date' => 'invoice_date',
            'date_label' => 'Invoice Date',
            'partner_label' => 'Supplier',
            'partner_code' => 'supplier_code',
            'partner_name' => 'supplier_name',
            'source_label' => 'Receipt No',
            'source_field' => 'receipt_no',
            'qty' => 'qty_invoiced',
            'qty_label' => 'Invoice Qty',
            'price' => 'unit_cost',
            'price_label' => 'Unit Cost',
            'show_amounts' => true,
            'show_line_commercial' => true,
            'show_header_commercial' => false,
            'show_unit_price' => true,
            'signatures' => ['Prepared By', 'Checked By', 'Approved By'],
        ],
        'sales-invoice' => [
            'title' => 'Sales Invoice',
            'table' => 'sales_invoices',
            'line_table' => 'sales_invoice_lines',
            'line_fk' => 'sales_invoice_id',
            'number' => 'invoice_no',
            'date' => 'invoice_date',
            'date_label' => 'Invoice Date',
            'partner_label' => 'Customer',
            'partner_code' => 'customer_code',
            'partner_name' => 'customer_name',
            'source_label' => 'Delivery No',
            'source_field' => 'delivery_no',
            'qty' => 'qty_invoiced',
            'qty_label' => 'Invoice Qty',
            'price' => 'unit_price',
            'price_label' => 'Unit Price',
            'show_amounts' => true,
            'show_line_commercial' => true,
            'show_header_commercial' => false,
            'show_unit_price' => true,
            'signatures' => ['Prepared By', 'Checked By', 'Received By'],
        ],
    ];

    public function purchaseOrder(int $id): string { return $this->renderPrintable('purchase-order', $id); }
    public function salesOrder(int $id): string { return $this->renderPrintable('sales-order', $id); }
    public function purchaseReceipt(int $id): string { return $this->renderPrintable('purchase-receipt', $id); }
    public function salesDelivery(int $id): string { return $this->renderPrintable('sales-delivery', $id); }
    public function purchaseInvoice(int $id): string { return $this->renderPrintable('purchase-invoice', $id); }
    public function salesInvoice(int $id): string { return $this->renderPrintable('sales-invoice', $id); }

    private function renderPrintable(string $type, int $id): string
    {
        $config = $this->configs[$type] ?? null;
        if ($config === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $db = Database::connect();
        $tenant = new TenantContext(session());
        $headerBuilder = $db->table($config['table'])->where('id', $id);
        if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', $config['table'])) {
            $headerBuilder->where('company_id', $tenant->activeCompanyId());
        }
        if ($tenant->activeSiteId() !== null && $db->fieldExists('site_id', $config['table'])) {
            $headerBuilder->where('site_id', $tenant->activeSiteId());
        }
        if ($db->fieldExists('deleted_at', $config['table'])) {
            $headerBuilder->where('deleted_at', null);
        }

        $header = $headerBuilder->get()->getRowArray();
        if ($header === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $lines = $db->table($config['line_table'])
            ->where($config['line_fk'], $id)
            ->orderBy('line_no', 'ASC')
            ->get()
            ->getResultArray();

        $company = [];
        if (! empty($header['company_id']) && $db->tableExists('companies')) {
            $company = $db->table('companies')->where('id', (int) $header['company_id'])->get()->getRowArray() ?? [];
        }

        return view('prints/document', [
            'title' => $config['title'] . ' ' . ($header[$config['number']] ?? ''),
            'type' => $type,
            'config' => $config,
            'header' => $header,
            'lines' => $lines,
            'company' => $company,
        ]);
    }
}
