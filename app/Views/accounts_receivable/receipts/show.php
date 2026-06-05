<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">A/R Receipt</h4>
                        <p class="text-muted mb-0"><?= esc($receipt['receipt_no']) ?></p>
                    </div>
                    <span class="badge bg-success">posted</span>
                </div>
                <table class="table table-sm mb-0">
                    <tr><th>Receipt No</th><td><?= esc($receipt['receipt_no']) ?></td></tr>
                    <tr><th>Date</th><td><?= esc($receipt['receipt_date']) ?></td></tr>
                    <tr><th>Invoice</th><td><a href="<?= site_url('ar/sales-invoices/' . $receipt['sales_invoice_id']) ?>"><?= esc($receipt['invoice_no']) ?></a></td></tr>
                    <tr><th>Customer</th><td><?= esc(($receipt['customer_code'] ?? '-') . ' ' . ($receipt['customer_name'] ?? '')) ?></td></tr>
                    <tr><th>Method</th><td><?= esc($receipt['receipt_method'] ?? '-') ?></td></tr>
                    <tr><th>Cash/Bank</th><td><?= esc($receipt['cash_bank_code'] ?? '-') ?></td></tr>
                    <tr><th>Reference</th><td><?= esc($receipt['reference_no'] ?? '-') ?></td></tr>
                    <tr><th>Amount</th><td class="fw-semibold"><?= esc(number_format((float) $receipt['receipt_amount'], 2)) ?></td></tr>
                    <tr><th>Posted</th><td><?= esc($receipt['posted_at'] ?? '-') ?></td></tr>
                </table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= site_url('ar/receipts') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <a href="<?= site_url('ar/sales-invoices/' . $receipt['sales_invoice_id']) ?>" class="btn btn-outline-primary">Open Invoice</a>
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
