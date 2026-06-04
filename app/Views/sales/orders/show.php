<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $status = (string) ($order['document_status'] ?? $order['status'] ?? 'draft'); ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Sales Order</h4>
                        <p class="text-muted mb-0"><?= esc($order['so_no']) ?></p>
                    </div>
                    <span class="badge bg-<?= match ($status) { 'draft' => 'secondary', 'submitted' => 'info', 'approved' => 'success', 'reserved' => 'primary', 'partial_reserved', 'partial_delivered' => 'warning', 'delivered', 'invoiced' => 'success', 'cancelled' => 'danger', default => 'secondary' } ?>"><?= esc($status) ?></span>
                </div>

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>SO No</th><td><?= esc($order['so_no']) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($order['so_date']) ?></td></tr>
                        <tr><th>Customer</th><td><?= esc(($order['customer_code'] ?? $order['customer'] ?? '-') . ' ' . ($order['customer_name'] ?? '')) ?></td></tr>
                        <tr><th>Terms</th><td><?= esc($order['terms_code'] ?? '-') ?></td></tr>
                        <tr><th>Currency</th><td><?= esc($order['currency_code']) ?></td></tr>
                        <tr><th>Company</th><td><?= esc($order['company'] ?? $order['company_id']) ?></td></tr>
                        <tr><th>Site</th><td><?= esc($order['site'] ?? $order['site_id'] ?? '-') ?></td></tr>
                        <tr><th>Submitted</th><td><?= esc($order['submitted_at'] ?? '-') ?></td></tr>
                        <tr><th>Approved</th><td><?= esc($order['approved_at'] ?? '-') ?></td></tr>
                        <tr><th>Reserved</th><td><?= esc($order['reserved_at'] ?? '-') ?></td></tr>
                    </tbody>
                </table>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= site_url('sales/orders') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <?php if ($status === 'draft'): ?>
                        <form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/submit') ?>"><?= csrf_field() ?><button class="btn btn-info" onclick="return confirm('Submit this SO?')">Submit</button></form>
                    <?php endif ?>
                    <?php if ($status === 'submitted'): ?>
                        <form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/approve') ?>"><?= csrf_field() ?><button class="btn btn-success" onclick="return confirm('Approve this SO?')">Approve</button></form>
                    <?php endif ?>
                    <?php if (in_array($status, ['approved','partial_reserved'], true)): ?>
                        <form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/reserve') ?>"><?= csrf_field() ?><button class="btn btn-primary" onclick="return confirm('Reserve stock for this SO?')"><i class="bx bx-lock-alt me-1"></i> Reserve Stock</button></form>
                    <?php endif ?>
                    <?php if (in_array($status, ['approved','reserved','partial_delivered'], true)): ?>
                        <a href="<?= site_url('sales/orders/' . $order['id'] . '/deliver') ?>" class="btn btn-success"><i class="bx bx-send me-1"></i> Create DO</a>
                    <?php endif ?>
                    <?php if (in_array($status, ['draft','submitted'], true)): ?>
                        <form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/cancel') ?>"><?= csrf_field() ?><input type="hidden" name="cancel_reason" value="Cancelled from SO detail"><button class="btn btn-outline-danger" onclick="return confirm('Cancel this SO?')">Cancel</button></form>
                    <?php endif ?>
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
                                <th class="text-end">Ordered</th>
                                <th class="text-end">Reserved</th>
                                <th class="text-end">Delivered</th>
                                <th class="text-end">Outstanding</th>
                                <th>UoM</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_ordered'] ?? $line['qty'] ?? 0), 4)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_reserved'] ?? 0), 4)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_delivered'] ?? 0), 4)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) ($line['qty_outstanding'] ?? $line['qty'] ?? 0), 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_price'], 2)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) $line['line_total'], 2)) ?></td>
                                <td><span class="badge bg-secondary"><?= esc($line['line_status'] ?? 'open') ?></span></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr><th colspan="8" class="text-end">Subtotal</th><th class="text-end"><?= esc(number_format((float) $order['subtotal_amount'], 2)) ?></th><th></th></tr>
                            <tr><th colspan="8" class="text-end">Discount</th><th class="text-end"><?= esc(number_format((float) $order['discount_amount'], 2)) ?></th><th></th></tr>
                            <tr><th colspan="8" class="text-end">Tax</th><th class="text-end"><?= esc(number_format((float) $order['tax_amount'], 2)) ?></th><th></th></tr>
                            <tr><th colspan="8" class="text-end">Total</th><th class="text-end"><?= esc(number_format((float) $order['total_amount'], 2)) ?></th><th></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php if (! empty($order['notes'])): ?>
            <div class="card"><div class="card-body"><h4 class="card-title mb-3">Notes</h4><p class="text-muted mb-0"><?= esc($order['notes']) ?></p></div></div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
