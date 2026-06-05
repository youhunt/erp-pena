<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">A/P Payments</h4>
                <p class="text-muted mb-0">Posted supplier payments against open A/P payables.</p>
            </div>
            <a href="<?= site_url('ap/purchase-invoices') ?>" class="btn btn-primary"><i class="bx bx-receipt me-1"></i> Open Invoices</a>
        </div>
        <div class="table-responsive">
            <table class="table table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Payment No</th><th>Date</th><th>Invoice</th><th>Supplier</th><th>Method</th><th class="text-end">Amount</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($payment['payment_no'] ?? '-') ?></td>
                        <td><?= esc($payment['payment_date'] ?? '-') ?></td>
                        <td><a href="<?= site_url('ap/purchase-invoices/' . $payment['purchase_invoice_id']) ?>"><?= esc($payment['invoice_no'] ?? '-') ?></a></td>
                        <td><div><?= esc($payment['supplier_name'] ?? '-') ?></div><small class="text-muted"><?= esc($payment['supplier_code'] ?? '-') ?></small></td>
                        <td><?= esc($payment['payment_method'] ?? '-') ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($payment['payment_amount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><a href="<?= site_url('ap/payments/' . $payment['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($payments === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No A/P payment posted yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
