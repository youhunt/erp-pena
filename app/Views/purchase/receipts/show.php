<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Purchase Receipt</h4>
                        <p class="text-muted mb-0"><?= esc($receipt['receipt_no']) ?></p>
                    </div>
                    <span class="badge bg-success"><?= esc($receipt['status']) ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>Receipt No</th><td><?= esc($receipt['receipt_no']) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($receipt['receipt_date']) ?></td></tr>
                        <tr><th>PO No</th><td><a href="<?= site_url('purchase/orders/' . $receipt['purchase_order_id']) ?>"><?= esc($receipt['po_no']) ?></a></td></tr>
                        <tr><th>Supplier</th><td><?= esc(($receipt['supplier_code'] ?? '-') . ' ' . ($receipt['supplier_name'] ?? '')) ?></td></tr>
                        <tr><th>Receipt GL Entry</th><td><?= ! empty($receipt['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $receipt['gl_entry_id']) . '">#' . esc($receipt['gl_entry_id']) . '</a>' : '-' ?></td></tr>
                        <tr><th>Posted</th><td><?= esc($receipt['posted_at'] ?? '-') ?></td></tr>
                    </tbody>
                </table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= site_url('purchase/orders/' . $receipt['purchase_order_id']) ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back to PO</a>
                    <?php if (($receipt['status'] ?? '') !== 'invoiced'): ?>
                        <a href="<?= site_url('purchase/receipts/' . $receipt['id'] . '/invoice') ?>" class="btn btn-primary"><i class="bx bx-receipt me-1"></i> Create AP Invoice</a>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Received Lines</h4>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Item</th><th>Batch</th><th class="text-end">Qty</th><th>UoM</th><th class="text-end">Unit Cost</th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                <td><?= esc(($line['batch_no'] ?? '') !== '' ? $line['batch_no'] : '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['qty_received'], 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_cost'], 6)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if (! empty($receipt['notes'])): ?>
            <div class="card"><div class="card-body"><h4 class="card-title mb-3">Notes</h4><p class="text-muted mb-0"><?= esc($receipt['notes']) ?></p></div></div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
