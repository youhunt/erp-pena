<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$status = (string) ($receipt['status'] ?? 'posted');
$statusClass = match ($status) {
    'posted' => 'bg-success',
    'cancelled' => 'bg-danger',
    default => 'bg-secondary',
};
$cashBankRoute = ($receipt['receipt_method'] ?? '') === 'cash' ? 'cash-bank/cash-entries/' : 'cash-bank/bank-entries/';
?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">A/R Receipt</h4>
                        <p class="text-muted mb-0"><?= esc($receipt['receipt_no']) ?></p>
                    </div>
                    <span class="badge <?= esc($statusClass) ?>"><?= esc($status) ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tr><th>Receipt No</th><td><?= esc($receipt['receipt_no']) ?></td></tr>
                    <tr><th>Date</th><td><?= esc($receipt['receipt_date']) ?></td></tr>
                    <tr><th>Invoice</th><td><a href="<?= site_url('ar/sales-invoices/' . $receipt['sales_invoice_id']) ?>"><?= esc($receipt['invoice_no']) ?></a></td></tr>
                    <tr><th>Customer</th><td><?= esc(($receipt['customer_code'] ?? '-') . ' ' . ($receipt['customer_name'] ?? '')) ?></td></tr>
                    <tr><th>Method</th><td><?= esc($receipt['receipt_method'] ?? '-') ?></td></tr>
                    <tr><th>Cash/Bank</th><td><?= esc($receipt['cash_bank_code'] ?? '-') ?></td></tr>
                    <tr><th>Cash/Bank Entry</th><td><?= ! empty($receipt['cash_bank_entry_id']) ? '<a href="' . site_url($cashBankRoute . $receipt['cash_bank_entry_id']) . '">#' . esc($receipt['cash_bank_entry_id']) . '</a>' : '-' ?></td></tr>
                    <tr><th>GL Entry</th><td><?= ! empty($receipt['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $receipt['gl_entry_id']) . '">#' . esc($receipt['gl_entry_id']) . '</a>' : '-' ?></td></tr>
                    <tr><th>Reversal Cash/Bank</th><td><?= ! empty($receipt['reversal_cash_bank_entry_id']) ? '<a href="' . site_url($cashBankRoute . $receipt['reversal_cash_bank_entry_id']) . '">#' . esc($receipt['reversal_cash_bank_entry_id']) . '</a>' : '-' ?></td></tr>
                    <tr><th>Reversal GL</th><td><?= ! empty($receipt['reversal_gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $receipt['reversal_gl_entry_id']) . '">#' . esc($receipt['reversal_gl_entry_id']) . '</a>' : '-' ?></td></tr>
                    <tr><th>Reference</th><td><?= esc($receipt['reference_no'] ?? '-') ?></td></tr>
                    <tr><th>Amount</th><td class="fw-semibold"><?= esc(number_format((float) $receipt['receipt_amount'], 2)) ?></td></tr>
                    <tr><th>Posted</th><td><?= esc($receipt['posted_at'] ?? '-') ?></td></tr>
                    <?php if ($status === 'cancelled'): ?>
                        <tr><th>Cancelled</th><td><?= esc($receipt['cancelled_at'] ?? '-') ?></td></tr>
                        <tr><th>Reason</th><td><?= esc($receipt['cancel_reason'] ?? '-') ?></td></tr>
                    <?php endif ?>
                </table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= site_url('ar/receipts') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <a href="<?= site_url('ar/sales-invoices/' . $receipt['sales_invoice_id']) ?>" class="btn btn-outline-primary">Open Invoice</a>
                    <?php if ($status === 'posted'): ?>
                        <form method="post" action="<?= site_url('ar/receipts/' . (int) $receipt['id'] . '/cancel') ?>" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="text" name="cancel_reason" class="form-control form-control-sm" placeholder="Cancel reason" style="max-width: 170px;">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Cancel this A/R receipt and post cash/bank reversal?')">Cancel</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Notes</h4>
                <p class="text-muted mb-0"><?= esc($receipt['notes'] ?: '-') ?></p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
