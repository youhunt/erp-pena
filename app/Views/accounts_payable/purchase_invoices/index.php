<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Purchase Invoices</h4>
                <p class="text-muted mb-0">Posted supplier invoices and open A/P balances from purchase receipts.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= site_url('ap/manual-invoices/new') ?>" class="btn btn-primary">
                    <i class="bx bx-plus me-1"></i> Manual A/P Invoice
                </a>
                <a href="<?= site_url('purchase/receipts') ?>" class="btn btn-light">
                    <i class="bx bx-receipt me-1"></i> Open Receipts
                </a>
            </div>
        </div>

        <form method="get" action="<?= site_url('ap/purchase-invoices') ?>" class="row g-2 align-items-end mb-4">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="<?= esc($filters['q'] ?? '') ?>" class="form-control" placeholder="Invoice no, receipt no, supplier">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <?php foreach ($statusOptions as $option): ?>
                        <option value="<?= esc($option) ?>" <?= ($filters['status'] ?? '') === $option ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $option))) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bx bx-search me-1"></i> Filter</button>
                <a href="<?= site_url('ap/purchase-invoices') ?>" class="btn btn-light"><i class="bx bx-reset me-1"></i> Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Due Date</th>
                        <th>Supplier</th>
                        <th>Receipt No</th>
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
                            <div><?= esc($invoice['supplier_name'] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($invoice['supplier_code'] ?? '-') ?></small>
                        </td>
                        <td><?= ! empty($invoice['purchase_receipt_id']) ? '<a href="' . site_url('purchase/receipts/' . $invoice['purchase_receipt_id']) . '">' . esc($invoice['receipt_no'] ?? '-') . '</a>' : '<span class="text-muted">manual</span>' ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($invoice['total_amount'] ?? 0), 2)) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($invoice['outstanding_amount'] ?? 0), 2)) ?></td>
                        <td><span class="badge bg-<?= ($invoice['status'] ?? '') === 'open' ? 'warning' : 'success' ?>"><?= esc($invoice['status'] ?? '-') ?></span></td>
                        <td class="text-end">
                            <a href="<?= site_url('ap/purchase-invoices/' . $invoice['id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <?php if ((float) ($invoice['outstanding_amount'] ?? 0) > 0): ?>
                                <a href="<?= site_url('ap/purchase-invoices/' . $invoice['id'] . '/payment') ?>" class="btn btn-sm btn-outline-success">Pay</a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if ($invoices === []): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No purchase invoice posted yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
