<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Purchase Order</h4>
                        <p class="text-muted mb-0"><?= esc($order['po_no']) ?></p>
                    </div>
                    <span class="badge bg-secondary"><?= esc($order['status']) ?></span>
                </div>

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>PO No</th><td><?= esc($order['po_no']) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($order['po_date']) ?></td></tr>
                        <tr><th>Supplier</th><td><?= esc($order['supplier_name'] ?? '-') ?></td></tr>
                        <tr><th>Currency</th><td><?= esc($order['currency_code']) ?></td></tr>
                        <tr><th>Company</th><td><?= esc($order['company_id']) ?></td></tr>
                        <tr><th>Site</th><td><?= esc($order['site_id'] ?? '-') ?></td></tr>
                        <tr><th>Source Document</th><td><?= esc($order['source_document_upload_id'] ?? '-') ?></td></tr>
                    </tbody>
                </table>

                <div class="mt-3">
                    <a href="<?= site_url('purchase/orders') ?>" class="btn btn-light">
                        <i class="bx bx-arrow-back me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Line Items</h4>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th class="text-end">Qty</th>
                                <th>UoM</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end">Tax</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line_no']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div>
                                    <small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small>
                                </td>
                                <td class="text-end"><?= esc(number_format((float) $line['qty'], 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_price'], 2)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['discount_amount'], 2)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['tax_amount'], 2)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) $line['line_total'], 2)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr><th colspan="7" class="text-end">Subtotal</th><th class="text-end"><?= esc(number_format((float) $order['subtotal_amount'], 2)) ?></th></tr>
                            <tr><th colspan="7" class="text-end">Discount</th><th class="text-end"><?= esc(number_format((float) $order['discount_amount'], 2)) ?></th></tr>
                            <tr><th colspan="7" class="text-end">Tax</th><th class="text-end"><?= esc(number_format((float) $order['tax_amount'], 2)) ?></th></tr>
                            <tr><th colspan="7" class="text-end">Total</th><th class="text-end"><?= esc(number_format((float) $order['total_amount'], 2)) ?></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php if (! empty($order['notes'])): ?>
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Notes</h4>
                    <p class="text-muted mb-0"><?= esc($order['notes']) ?></p>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
