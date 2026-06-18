<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$status = (string) ($order['document_status'] ?? $order['status'] ?? 'draft');
$hasReceivedLine = false;
foreach ($lines as $line) {
    if ((float) ($line['qty_received'] ?? 0) > 0) {
        $hasReceivedLine = true;
        break;
    }
}
$canEditPo = in_array($status, ['draft', 'submitted', 'approved'], true) && ! $hasReceivedLine;
$subtotal = (float) ($order['subtotal_amount'] ?? 0);
$discountPercent = (float) ($order['discount_percent'] ?? 0);
$discountPercentAmount = round($subtotal * $discountPercent / 100, 2);
$manualDiscountAmount = (float) ($order['discount_amount'] ?? 0);
$totalDiscountAmount = round($discountPercentAmount + $manualDiscountAmount, 2);
?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Purchase Order</h4>
                        <p class="text-muted mb-0"><?= esc($order['po_no']) ?></p>
                    </div>
                    <span class="badge bg-<?= match ($status) { 'draft' => 'secondary', 'submitted' => 'info', 'approved' => 'success', 'partial_received' => 'warning', 'received' => 'primary', 'closed' => 'dark', 'cancelled' => 'danger', default => 'secondary' } ?>"><?= esc($status) ?></span>
                </div>

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>PO No</th><td><?= esc($order['po_no']) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($order['po_date']) ?></td></tr>
                        <tr><th>Delivery</th><td><?= esc($order['delivery_date'] ?? '-') ?></td></tr>
                        <tr><th>Arrive</th><td><?= esc($order['arrive_date'] ?? '-') ?></td></tr>
                        <tr><th>Supplier</th><td><?= esc(($order['supplier_code'] ?? $order['supplier'] ?? '-') . ' ' . ($order['supplier_name'] ?? '')) ?></td></tr>
                        <tr><th>Terms</th><td><?= esc($order['terms_code'] ?? '-') ?></td></tr>
                        <tr><th>Currency</th><td><?= esc($order['currency_code']) ?></td></tr>
                        <tr><th>Company</th><td><?= esc($order['company'] ?? $order['company_id']) ?></td></tr>
                        <tr><th>Site</th><td><?= esc($order['site'] ?? $order['site_id'] ?? '-') ?></td></tr>
                        <tr><th>Submitted</th><td><?= esc($order['submitted_at'] ?? '-') ?></td></tr>
                        <tr><th>Approved</th><td><?= esc($order['approved_at'] ?? '-') ?></td></tr>
                    </tbody>
                </table>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= site_url('purchase/orders') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <a href="<?= site_url('print/purchase-orders/' . (int) $order['id']) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bx bx-printer me-1"></i> Print</a>
                    <?php if ($canEditPo): ?>
                        <a href="<?= site_url('purchase/orders/' . $order['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bx bx-edit me-1"></i> Edit</a>
                    <?php endif ?>
                    <?php if ($status === 'draft'): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/submit') ?>"><?= csrf_field() ?><button class="btn btn-info" onclick="return confirm('Submit this PO?')">Submit</button></form>
                    <?php endif ?>
                    <?php if ($status === 'submitted'): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/approve') ?>"><?= csrf_field() ?><button class="btn btn-success" onclick="return confirm('Approve this PO?')">Approve</button></form>
                    <?php endif ?>
                    <?php if (in_array($status, ['approved','partial_received'], true)): ?>
                        <a href="<?= site_url('purchase/orders/' . $order['id'] . '/receive') ?>" class="btn btn-primary"><i class="bx bx-package me-1"></i> Receive</a>
                    <?php endif ?>
                    <?php if ($status === 'received'): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/close') ?>"><?= csrf_field() ?><button class="btn btn-dark" onclick="return confirm('Close this PO?')">Close</button></form>
                    <?php endif ?>
                    <?php if (in_array($status, ['draft','submitted'], true)): ?>
                        <form method="post" action="<?= site_url('purchase/orders/' . $order['id'] . '/cancel') ?>" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="cancel_reason" value="Cancelled from PO detail">
                            <button class="btn btn-outline-danger" onclick="return confirm('Cancel this PO?')">Cancel</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Header Amount</h4>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>Subtotal</th><td class="text-end"><?= esc(number_format($subtotal, 2)) ?></td></tr>
                        <tr class="table-light">
                            <th>Discount</th>
                            <td class="text-end">
                                <div class="fw-semibold"><?= esc(number_format($totalDiscountAmount, 2)) ?></div>
                                <small class="text-muted"><?= esc(number_format($discountPercent, 4)) ?>% = <?= esc(number_format($discountPercentAmount, 2)) ?> + amount <?= esc(number_format($manualDiscountAmount, 2)) ?></small>
                            </td>
                        </tr>
                        <tr><th>Freight</th><td class="text-end"><?= esc(number_format((float) ($order['freight_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Other Amount</th><td class="text-end"><?= esc(number_format((float) ($order['other_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Special Charge</th><td class="text-end"><?= esc(number_format((float) ($order['special_charge_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>VAT</th><td class="text-end"><?= esc(number_format((float) ($order['vat_amount'] ?? $order['tax_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>WHT</th><td class="text-end"><?= esc(number_format((float) ($order['wht_amount'] ?? 0), 2)) ?></td></tr>
                        <tr class="table-light"><th>Total PO</th><td class="text-end fw-semibold"><?= esc(number_format((float) $order['total_amount'], 2)) ?></td></tr>
                    </tbody>
                </table>
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
                                <th>#</th><th>Item</th><th>Description</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Outstanding</th><th>UoM</th><th class="text-end">Price</th><th class="text-end">Line Total</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['po_line'] ?? $line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                <td><?= esc($line['description'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_ordered'] ?? $line['qty'] ?? 0), 4)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_received'] ?? 0), 4)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) ($line['qty_outstanding'] ?? $line['qty'] ?? 0), 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_price'], 2)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) $line['line_total'], 2)) ?></td>
                                <td><span class="badge bg-secondary"><?= esc($line['line_status'] ?? 'open') ?></span></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (! empty($order['notes']) || ! empty($order['remarks'])): ?>
            <div class="card">
                <div class="card-body">
                    <?php if (! empty($order['notes'])): ?><h4 class="card-title mb-2">Notes</h4><p class="text-muted"><?= esc($order['notes']) ?></p><?php endif ?>
                    <?php if (! empty($order['remarks'])): ?><h4 class="card-title mb-2">Remarks</h4><p class="text-muted mb-0"><?= esc($order['remarks']) ?></p><?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
