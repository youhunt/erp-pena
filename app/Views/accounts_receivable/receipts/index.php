<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">A/R Receipts</h4>
                <p class="text-muted mb-0">Posted customer receipts against open A/R receivables.</p>
            </div>
            <a href="<?= site_url('ar/sales-invoices') ?>" class="btn btn-primary"><i class="bx bx-receipt me-1"></i> Open Invoices</a>
        </div>
        <div class="table-responsive">
            <table class="table table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Receipt No</th><th>Date</th><th>Invoice</th><th>Customer</th><th>Method</th><th class="text-end">Amount</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($receipts as $receipt): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($receipt['receipt_no'] ?? '-') ?></td>
                        <td><?= esc($receipt['receipt_date'] ?? '-') ?></td>
                        <td><a href="<?= site_url('ar/sales-invoices/' . $receipt['sales_invoice_id']) ?>"><?= esc($receipt['invoice_no'] ?? '-') ?></a></td>
                        <td><div><?= esc($receipt['customer_name'] ?? '-') ?></div><small class="text-muted"><?= esc($receipt['customer_code'] ?? '-') ?></small></td>
                        <td><?= esc($receipt['receipt_method'] ?? '-') ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($receipt['receipt_amount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><a href="<?= site_url('ar/receipts/' . $receipt['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($receipts === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No A/R receipt posted yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
