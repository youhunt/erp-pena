<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $exportUrl = site_url('sales/deliveries/export') . '?' . http_build_query($filters ?? []); ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Delivery Orders</h4>
                <p class="text-muted mb-0">Posted delivery documents that decreased inventory stock.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= esc($exportUrl) ?>" class="btn btn-outline-success"><i class="bx bx-download me-1"></i> Export XLSX</a>
                <a href="<?= site_url('sales/deliveries/import') ?>" class="btn btn-outline-primary"><i class="bx bx-upload me-1"></i> Import</a>
                <a href="<?= site_url('sales/deliveries/import-template') ?>" class="btn btn-outline-secondary"><i class="bx bx-download me-1"></i> Template</a>
                <a href="<?= site_url('sales/orders') ?>" class="btn btn-primary"><i class="bx bx-cart me-1"></i> Open Sales Orders</a>
            </div>
        </div>

        <form method="get" action="<?= site_url('sales/deliveries') ?>" class="row g-2 align-items-end mb-4">
            <div class="col-md-5"><label class="form-label">Search</label><input type="text" name="q" value="<?= esc($filters['q'] ?? '') ?>" class="form-control" placeholder="Delivery no, SO no, customer"></div>
            <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All Status</option><?php foreach ($statusOptions as $option): ?><option value="<?= esc($option) ?>" <?= ($filters['status'] ?? '') === $option ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $option))) ?></option><?php endforeach ?></select></div>
            <div class="col-md-4 d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="bx bx-search me-1"></i> Filter</button><a href="<?= site_url('sales/deliveries') ?>" class="btn btn-light"><i class="bx bx-reset me-1"></i> Reset</a></div>
        </form>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Delivery No</th><th>Date</th><th>SO No</th><th>Customer</th><th>Status</th><th>Posted At</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach ($deliveries as $delivery): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($delivery['delivery_no'] ?? '-') ?></td><td><?= esc($delivery['delivery_date'] ?? '-') ?></td><td><a href="<?= site_url('sales/orders/' . $delivery['sales_order_id']) ?>"><?= esc($delivery['so_no'] ?? '-') ?></a></td>
                        <td><div><?= esc($delivery['customer_name'] ?? '-') ?></div><small class="text-muted"><?= esc($delivery['customer_code'] ?? '-') ?></small></td><td><span class="badge bg-success"><?= esc($delivery['status'] ?? '-') ?></span></td><td><?= esc($delivery['posted_at'] ?? '-') ?></td>
                        <td class="text-end"><a href="<?= site_url('sales/deliveries/' . $delivery['id']) ?>" class="btn btn-sm btn-outline-primary">View</a> <a href="<?= site_url('sales/deliveries/' . $delivery['id'] . '/export') ?>" class="btn btn-sm btn-outline-success">XLSX</a><?php if (($delivery['status'] ?? '') === 'posted'): ?> <a href="<?= site_url('sales/deliveries/' . $delivery['id'] . '/invoice') ?>" class="btn btn-sm btn-outline-success">Invoice</a><?php endif ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($deliveries === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No delivery order posted yet.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
