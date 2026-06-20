<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$status = (string) ($invoice['status'] ?? 'open');
$statusClass = match ($status) {
    'open' => 'bg-success',
    'partial' => 'bg-info',
    'paid' => 'bg-primary',
    'cancelled' => 'bg-danger',
    default => 'bg-secondary',
};
$outstandingAmount = (float) ($payable['outstanding_amount'] ?? $invoice['outstanding_amount'] ?? 0);
$paidAmount = (float) ($payable['paid_amount'] ?? $invoice['paid_amount'] ?? 0);
?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Purchase Invoice</h4>
                        <p class="text-muted mb-0"><?= esc($invoice['invoice_no']) ?></p>
                    </div>
                    <span class="badge <?= esc($statusClass) ?>"><?= esc($status) ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>Invoice No</th><td><?= esc($invoice['invoice_no']) ?></td></tr>
                        <tr><th>Date</th><td><?= esc($invoice['invoice_date']) ?></td></tr>
                        <tr><th>Due Date</th><td><?= esc($invoice['due_date'] ?? '-') ?></td></tr>
                        <tr><th>Source</th><td><?= esc($invoice['source_type'] ?? (! empty($invoice['purchase_receipt_id']) ? 'receipt' : 'system')) ?></td></tr>
                        <tr><th>PO No</th><td><?= ! empty($invoice['purchase_order_id']) ? '<a href="' . site_url('purchase/orders/' . $invoice['purchase_order_id']) . '">' . esc($invoice['po_no'] ?? '-') . '</a>' : '-' ?></td></tr>
                        <tr><th>Receipt No</th><td><?= ! empty($invoice['purchase_receipt_id']) ? '<a href="' . site_url('purchase/receipts/' . $invoice['purchase_receipt_id']) . '">' . esc($invoice['receipt_no'] ?? '-') . '</a>' : '-' ?></td></tr>
                        <tr><th>GL Entry</th><td><?= ! empty($invoice['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $invoice['gl_entry_id']) . '">#' . esc($invoice['gl_entry_id']) . '</a>' : '-' ?></td></tr>
                        <tr><th>Reversal GL</th><td><?= ! empty($invoice['reversal_gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $invoice['reversal_gl_entry_id']) . '">#' . esc($invoice['reversal_gl_entry_id']) . '</a>' : '-' ?></td></tr>
                        <tr><th>Supplier</th><td><?= esc(($invoice['supplier_code'] ?? '-') . ' ' . ($invoice['supplier_name'] ?? '')) ?></td></tr>
                        <tr><th>Outstanding</th><td class="fw-semibold"><?= esc(number_format($outstandingAmount, 2)) ?></td></tr>
                        <?php if ($status === 'cancelled'): ?>
                            <tr><th>Cancelled</th><td><?= esc($invoice['cancelled_at'] ?? '-') ?></td></tr>
                            <tr><th>Reason</th><td><?= esc($invoice['cancel_reason'] ?? '-') ?></td></tr>
                        <?php endif ?>
                    </tbody>
                </table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= site_url('ap/purchase-invoices') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <a href="<?= site_url('print/purchase-invoices/' . (int) $invoice['id']) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bx bx-printer me-1"></i> Print</a>
                    <?php if (in_array($status, ['open', 'partial'], true) && $outstandingAmount > 0): ?>
                        <a href="<?= site_url('ap/purchase-invoices/' . $invoice['id'] . '/payment') ?>" class="btn btn-primary"><i class="bx bx-money me-1"></i> Post Payment</a>
                    <?php endif ?>
                    <?php if ($status === 'open' && $paidAmount <= 0): ?>
                        <form method="post" action="<?= site_url('ap/purchase-invoices/' . (int) $invoice['id'] . '/cancel') ?>" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="text" name="cancel_reason" class="form-control form-control-sm" placeholder="Cancel reason" style="max-width: 170px;">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Cancel this purchase invoice and post reversal GL?')">Cancel</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Invoice Lines</h4>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><th>UoM</th><th class="text-end">Cost</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line_no']) ?></td>
                                <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                                <td class="text-end"><?= esc(number_format((float) $line['qty_invoiced'], 4)) ?></td>
                                <td><?= esc($line['uom_code'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) $line['unit_cost'], 2)) ?></td>
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
