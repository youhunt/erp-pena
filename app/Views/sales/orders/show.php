<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$status = (string) ($order['document_status'] ?? $order['status'] ?? 'draft');
$hasProcessedLine = false;
$totalReserved = 0.0;
$totalDelivered = 0.0;
foreach ($lines as $line) {
    $reserved = (float) ($line['qty_reserved'] ?? 0);
    $delivered = (float) ($line['qty_delivered'] ?? 0);
    $totalReserved += $reserved;
    $totalDelivered += $delivered;
    if ($reserved > 0 || $delivered > 0) {
        $hasProcessedLine = true;
    }
}
$canEditSo = $status === 'draft' && ! $hasProcessedLine;
$canBackToDraft = in_array($status, ['submitted', 'approved', 'reserved', 'partial_reserved', 'cancelled'], true) && $totalDelivered <= 0;
$hasDownstreamPosted = in_array($status, ['partial_delivered', 'delivered', 'invoiced'], true) || $totalDelivered > 0;
?>
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

                <?php if ($hasDownstreamPosted): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        SO ini sudah ada proses downstream / delivered/invoiced, jadi tidak bisa langsung dikembalikan ke draft. Urutannya: cancel A/R receipt, cancel invoice, reverse delivery, baru SO bisa dibuka ulang.
                    </div>
                <?php endif ?>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= site_url('sales/orders') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back to List</a>
                    <a href="<?= site_url('print/sales-orders/' . (int) $order['id']) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bx bx-printer me-1"></i> Print</a>
                    <?php if ($canEditSo): ?>
                        <a href="<?= site_url('sales/orders/' . $order['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bx bx-edit me-1"></i> Edit</a>
                    <?php endif ?>
                    <?php if ($status === 'draft'): ?>
                        <form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/submit') ?>"><?= csrf_field() ?><button class="btn btn-info" onclick="return confirm('Submit this SO?')">Submit</button></form>
                    <?php endif ?>
                    <?php if ($status === 'submitted'): ?>
                        <form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/approve') ?>"><?= csrf_field() ?><button class="btn btn-success" onclick="return confirm('Approve this SO?')">Approve</button></form>
                    <?php endif ?>
                    <?php if (in_array($status, ['approved','partial_reserved'], true)): ?>
                        <a href="<?= site_url('sales/orders/' . $order['id'] . '/allocate') ?>" class="btn btn-primary"><i class="bx bx-lock-alt me-1"></i> Create Allocation</a>
                    <?php endif ?>
                    <?php if (in_array($status, ['approved','reserved','partial_delivered'], true)): ?>
                        <a href="<?= site_url('sales/orders/' . $order['id'] . '/deliver') ?>" class="btn btn-success"><i class="bx bx-send me-1"></i> Create DO</a>
                    <?php endif ?>
                    <?php if ($canBackToDraft): ?>
                        <form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/cancel') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="back_to_draft">
                            <button class="btn btn-warning" onclick="return confirm('Kembalikan SO ini ke draft? Allocation/reservation yang masih open akan dilepas.')"><i class="bx bx-rotate-left me-1"></i> Back to Draft</button>
                        </form>
                    <?php endif ?>
                    <?php if (in_array($status, ['draft','submitted'], true)): ?>
                        <form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/cancel') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="cancel_reason" value="Cancelled from SO detail">
                            <button class="btn btn-outline-danger" onclick="return confirm('Cancel this SO? This is not Back.')"><i class="bx bx-x-circle me-1"></i> Cancel SO</button>
                        </form>
                    <?php endif ?>
                    <?php if ($status === 'cancelled'): ?>
                        <form method="post" action="<?= site_url('sales/orders/' . $order['id'] . '/cancel') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reopen">
                            <button class="btn btn-warning" onclick="return confirm('Aktifkan kembali SO ini sebagai draft?')"><i class="bx bx-rotate-left me-1"></i> Reopen as Draft</button>
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
                        <tr><th>Subtotal</th><td class="text-end"><?= esc(number_format((float) ($order['subtotal_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Discount Amt</th><td class="text-end"><?= esc(number_format((float) ($order['discount_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Freight</th><td class="text-end"><?= esc(number_format((float) ($order['freight_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Other Amount</th><td class="text-end"><?= esc(number_format((float) ($order['other_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Tax</th><td class="text-end"><?= esc(number_format((float) ($order['tax_amount'] ?? 0), 2)) ?></td></tr>
                        <tr class="table-light"><th>Total</th><td class="text-end fw-semibold"><?= esc(number_format((float) $order['total_amount'], 2)) ?></td></tr>
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
                                <th>#</th><th>Item</th><th>Description</th><th class="text-end">Ordered</th><th class="text-end">Reserved</th><th class="text-end">Delivered</th><th class="text-end">Outstanding</th><th>UoM</th><th class="text-end">Price</th><th class="text-end">Disc</th><th class="text-end">Charges</th><th class="text-end">Total</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <?php $charges = (float) ($line['freight_amount'] ?? 0) + (float) ($line['special_charge_amount'] ?? 0) + (float) ($line['other_amount'] ?? 0); ?>
                            <tr>
                                <td><?= esc($line['so_line'] ?? $line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                <td><?= esc($line['description'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_ordered'] ?? $line['qty'] ?? 0), 4)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_reserved'] ?? 0), 4)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['qty_delivered'] ?? 0), 4)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) ($line['qty_outstanding'] ?? $line['qty'] ?? 0), 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_price'], 2)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['discount_amount'] ?? 0), 2)) ?></td>
                                <td class="text-end"><?= esc(number_format($charges, 2)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) $line['line_total'], 2)) ?></td>
                                <td><span class="badge bg-secondary"><?= esc($line['line_status'] ?? 'open') ?></span></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr><th colspan="11" class="text-end">Total</th><th class="text-end"><?= esc(number_format((float) $order['total_amount'], 2)) ?></th><th></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Related GL Entries</h4>
                        <p class="text-muted mb-0">SO does not post GL directly. Journals below come from delivery/COGS, invoice, receipt, or reversal documents linked to this SO.</p>
                    </div>
                    <a href="<?= site_url('gl/entries') ?>" class="btn btn-sm btn-outline-secondary">Open GL</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Source</th>
                                <th>Document</th>
                                <th>Date</th>
                                <th>Role</th>
                                <th>Journal</th>
                                <th>Journal Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($relatedGlEntries ?? []) as $entry): ?>
                            <tr>
                                <td><?= esc($entry['module'] ?? '-') ?></td>
                                <td><a href="<?= esc($entry['document_url']) ?>"><?= esc($entry['document_no'] ?? '-') ?></a></td>
                                <td><?= esc($entry['document_date'] ?? '-') ?></td>
                                <td><span class="badge bg-<?= ($entry['role'] ?? '') === 'reversal' ? 'warning' : 'success' ?>"><?= esc($entry['role'] ?? '-') ?></span></td>
                                <td><a href="<?= esc($entry['gl_url']) ?>"><?= esc($entry['journal_no'] ?? ('#' . ($entry['gl_entry_id'] ?? ''))) ?></a></td>
                                <td><?= esc($entry['journal_date'] ?? '-') ?></td>
                                <td><?= esc($entry['status'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if (($relatedGlEntries ?? []) === []): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No related GL entry yet. GL will appear after delivery, invoice, receipt, or reversal posting.</td></tr>
                        <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (! empty($order['notes']) || ! empty($order['remarks'])): ?>
            <div class="card"><div class="card-body">
                <?php if (! empty($order['notes'])): ?><h4 class="card-title mb-3">Notes</h4><p class="text-muted"><?= esc($order['notes']) ?></p><?php endif ?>
                <?php if (! empty($order['remarks'])): ?><h4 class="card-title mb-3">Remarks</h4><p class="text-muted mb-0"><?= esc($order['remarks']) ?></p><?php endif ?>
            </div></div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
