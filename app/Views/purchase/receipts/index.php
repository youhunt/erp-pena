<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $exportUrl = site_url('purchase/receipts/export') . '?' . http_build_query($filters ?? []); ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Purchase Receipts</h4>
                <p class="text-muted mb-0">Posted receiving documents that increased inventory stock.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= esc($exportUrl) ?>" class="btn btn-outline-success"><i class="bx bx-download me-1"></i> Export XLSX</a>
                <a href="<?= site_url('purchase/receipts/import') ?>" class="btn btn-outline-primary"><i class="bx bx-upload me-1"></i> Import</a>
                <a href="<?= site_url('purchase/receipts/import-template') ?>" class="btn btn-outline-secondary"><i class="bx bx-download me-1"></i> Template</a>
                <a href="<?= site_url('purchase/orders') ?>" class="btn btn-primary"><i class="bx bx-cart me-1"></i> Open Purchase Orders</a>
            </div>
        </div>

        <form method="get" action="<?= site_url('purchase/receipts') ?>" class="row g-2 align-items-end mb-4">
            <div class="col-md-5"><label class="form-label">Search</label><input type="text" name="q" value="<?= esc($filters['q'] ?? '') ?>" class="form-control" placeholder="Receipt no, PO no, supplier"></div>
            <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All Status</option><?php foreach ($statusOptions as $option): ?><option value="<?= esc($option) ?>" <?= ($filters['status'] ?? '') === $option ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $option))) ?></option><?php endforeach ?></select></div>
            <div class="col-md-4 d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="bx bx-search me-1"></i> Filter</button><a href="<?= site_url('purchase/receipts') ?>" class="btn btn-light"><i class="bx bx-reset me-1"></i> Reset</a></div>
        </form>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Receipt No</th><th>Date</th><th>PO No</th><th>Supplier</th><th>Status</th><th>Posted At</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach ($receipts as $receipt): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($receipt['receipt_no'] ?? '-') ?></td><td><?= esc($receipt['receipt_date'] ?? '-') ?></td><td><a href="<?= site_url('purchase/orders/' . $receipt['purchase_order_id']) ?>"><?= esc($receipt['po_no'] ?? '-') ?></a></td>
                        <td><div><?= esc($receipt['supplier_name'] ?? '-') ?></div><small class="text-muted"><?= esc($receipt['supplier_code'] ?? '-') ?></small></td><td><span class="badge bg-success"><?= esc($receipt['status'] ?? '-') ?></span></td><td><?= esc($receipt['posted_at'] ?? '-') ?></td>
                        <td class="text-end"><a href="<?= site_url('purchase/receipts/' . $receipt['id']) ?>" class="btn btn-sm btn-outline-primary">View</a> <a href="<?= site_url('purchase/receipts/' . $receipt['id'] . '/export') ?>" class="btn btn-sm btn-outline-success">XLSX</a><?php if (($receipt['status'] ?? '') === 'posted'): ?> <a href="<?= site_url('purchase/receipts/' . $receipt['id'] . '/invoice') ?>" class="btn btn-sm btn-outline-success">Invoice</a><?php endif ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($receipts === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No purchase receipt posted yet.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
