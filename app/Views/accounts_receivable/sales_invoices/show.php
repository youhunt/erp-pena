<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Sales Invoice</h4>
                        <p class="text-muted mb-0"><?= esc($invoice['invoice_no']) ?></p>
                    </div>
                    <span class="badge bg-warning"><?= esc($invoice['status']) ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>Invoice No</th><td><?= esc($invoice['invoice_no']) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($invoice['invoice_date']) ?></td></tr>
                        <tr><th>Due Date</th><td><?= esc($invoice['due_date'] ?? '-') ?></td></tr>
                        <tr><th>SO No</th><td><a href="<?= site_url('sales/orders/' . $invoice['sales_order_id']) ?>"><?= esc($invoice['so_no'] ?? '-') ?></a></td></tr>
                        <tr><th>DO No</th><td><a href="<?= site_url('sales/deliveries/' . $invoice['sales_delivery_id']) ?>"><?= esc($invoice['delivery_no'] ?? '-') ?></a></td></tr>
                        <tr><th>Customer</th><td><?= esc(($invoice['customer_code'] ?? '-') . ' ' . ($invoice['customer_name'] ?? '')) ?></td></tr>
                        <tr><th>Outstanding</th><td class="fw-semibold"><?= esc(number_format((float) ($receivable['outstanding_amount'] ?? $invoice['outstanding_amount'] ?? 0), 2)) ?></td></tr>
                    </tbody>
                </table>
                <div class="mt-3"><a href="<?= site_url('ar/sales-invoices') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a></div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Invoice Lines</h4>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><th>UoM</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                <td class="text-end"><?= esc(number_format((float) $line['qty_invoiced'], 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_price'], 2)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) $line['line_total'], 2)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr><th colspan="5" class="text-end">Subtotal</th><th class="text-end"><?= esc(number_format((float) $invoice['subtotal_amount'], 2)) ?></th></tr>
                            <tr><th colspan="5" class="text-end">Discount</th><th class="text-end"><?= esc(number_format((float) $invoice['discount_amount'], 2)) ?></th></tr>
                            <tr><th colspan="5" class="text-end">Tax</th><th class="text-end"><?= esc(number_format((float) $invoice['tax_amount'], 2)) ?></th></tr>
                            <tr><th colspan="5" class="text-end">Total</th><th class="text-end"><?= esc(number_format((float) $invoice['total_amount'], 2)) ?></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php if (! empty($invoice['notes'])): ?>
            <div class="card"><div class="card-body"><h4 class="card-title mb-3">Notes</h4><p class="text-muted mb-0"><?= esc($invoice['notes']) ?></p></div></div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
