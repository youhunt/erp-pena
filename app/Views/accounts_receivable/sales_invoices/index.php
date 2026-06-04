<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Sales Invoices</h4>
                <p class="text-muted mb-0">Posted invoices and open A/R balances from delivery orders.</p>
            </div>
            <a href="<?= site_url('sales/deliveries') ?>" class="btn btn-primary">
                <i class="bx bx-send me-1"></i> Open Delivery Orders
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Due Date</th>
                        <th>Customer</th>
                        <th>DO No</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Outstanding</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($invoice['invoice_no'] ?? '-') ?></td>
                        <td><?= esc($invoice['invoice_date'] ?? '-') ?></td>
                        <td><?= esc($invoice['due_date'] ?? '-') ?></td>
                        <td>
                            <div><?= esc($invoice['customer_name'] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($invoice['customer_code'] ?? '-') ?></small>
                        </td>
                        <td><a href="<?= site_url('sales/deliveries/' . $invoice['sales_delivery_id']) ?>"><?= esc($invoice['delivery_no'] ?? '-') ?></a></td>
                        <td class="text-end"><?= esc(number_format((float) ($invoice['total_amount'] ?? 0), 2)) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($invoice['outstanding_amount'] ?? 0), 2)) ?></td>
                        <td><span class="badge bg-<?= ($invoice['status'] ?? '') === 'open' ? 'warning' : 'success' ?>"><?= esc($invoice['status'] ?? '-') ?></span></td>
                        <td class="text-end">
                            <a href="<?= site_url('ar/sales-invoices/' . $invoice['id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if ($invoices === []): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No sales invoice posted yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
