<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">A/P Payment</h4>
                        <p class="text-muted mb-0"><?= esc($payment['payment_no']) ?></p>
                    </div>
                    <span class="badge bg-success">posted</span>
                </div>
                <table class="table table-sm mb-0">
                    <tr><th>Payment No</th><td><?= esc($payment['payment_no']) ?></td></tr>
                    <tr><th>Date</th><td><?= esc($payment['payment_date']) ?></td></tr>
                    <tr><th>Invoice</th><td><a href="<?= site_url('ap/purchase-invoices/' . $payment['purchase_invoice_id']) ?>"><?= esc($payment['invoice_no']) ?></a></td></tr>
                    <tr><th>Supplier</th><td><?= esc(($payment['supplier_code'] ?? '-') . ' ' . ($payment['supplier_name'] ?? '')) ?></td></tr>
                    <tr><th>Method</th><td><?= esc($payment['payment_method'] ?? '-') ?></td></tr>
                    <tr><th>Cash/Bank</th><td><?= esc($payment['cash_bank_code'] ?? '-') ?></td></tr>
                    <tr><th>Reference</th><td><?= esc($payment['reference_no'] ?? '-') ?></td></tr>
                    <tr><th>Amount</th><td class="fw-semibold"><?= esc(number_format((float) $payment['payment_amount'], 2)) ?></td></tr>
                    <tr><th>Posted</th><td><?= esc($payment['posted_at'] ?? '-') ?></td></tr>
                </table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= site_url('ap/payments') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <a href="<?= site_url('ap/purchase-invoices/' . $payment['purchase_invoice_id']) ?>" class="btn btn-outline-primary">Open Invoice</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Notes</h4>
                <p class="text-muted mb-0"><?= esc($payment['notes'] ?: '-') ?></p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
