<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Delivery Order</h4>
                        <p class="text-muted mb-0"><?= esc($delivery['delivery_no']) ?></p>
                    </div>
                    <span class="badge bg-success"><?= esc($delivery['status']) ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>Delivery No</th><td><?= esc($delivery['delivery_no']) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($delivery['delivery_date']) ?></td></tr>
                        <tr><th>SO No</th><td><a href="<?= site_url('sales/orders/' . $delivery['sales_order_id']) ?>"><?= esc($delivery['so_no']) ?></a></td></tr>
                        <tr><th>Customer</th><td><?= esc(($delivery['customer_code'] ?? '-') . ' ' . ($delivery['customer_name'] ?? '')) ?></td></tr>
                        <tr><th>Posted</th><td><?= esc($delivery['posted_at'] ?? '-') ?></td></tr>
                    </tbody>
                </table>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= site_url('sales/orders/' . $delivery['sales_order_id']) ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back to SO</a>
                    <?php if (($delivery['status'] ?? '') !== 'invoiced'): ?>
                        <a href="<?= site_url('sales/deliveries/' . $delivery['id'] . '/invoice') ?>" class="btn btn-primary"><i class="bx bx-receipt me-1"></i> Create Invoice</a>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Delivered Lines</h4>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><th>UoM</th><th class="text-end">Price</th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                <td class="text-end"><?= esc(number_format((float) $line['qty_delivered'], 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_price'], 6)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if (! empty($delivery['notes'])): ?>
            <div class="card"><div class="card-body"><h4 class="card-title mb-3">Notes</h4><p class="text-muted mb-0"><?= esc($delivery['notes']) ?></p></div></div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
