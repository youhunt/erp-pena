<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Sales Orders</h4>
                <p class="text-muted mb-0">Manage sales orders by active company/site.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= site_url('sales/orders/import') ?>" class="btn btn-outline-primary">
                    <i class="bx bx-upload me-1"></i> Import
                </a>
                <a href="<?= site_url('sales/orders/import-template') ?>" class="btn btn-outline-secondary">
                    <i class="bx bx-download me-1"></i> Template
                </a>
                <a href="<?= site_url('sales/orders/new') ?>" class="btn btn-primary">
                    <i class="bx bx-plus me-1"></i> New SO
                </a>
            </div>
        </div>

        <form method="get" action="<?= site_url('sales/orders') ?>" class="row g-2 align-items-end mb-4">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="<?= esc($filters['q'] ?? '') ?>" class="form-control" placeholder="SO no, customer code, customer name">
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
                <a href="<?= site_url('sales/orders') ?>" class="btn btn-light"><i class="bx bx-reset me-1"></i> Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>SO No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($order['so_no']) ?></td>
                        <td><?= esc($order['so_date']) ?></td>
                        <td><?= esc($order['customer_name'] ?? '-') ?></td>
                        <td><span class="badge bg-secondary"><?= esc($order['status']) ?></span></td>
                        <td class="text-end"><?= esc(number_format((float) $order['total_amount'], 2)) ?></td>
                        <td class="text-end">
                            <a href="<?= site_url('sales/orders/' . $order['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bx bx-show"></i>
                            </a>
                            <?php if (in_array(($order['document_status'] ?? $order['status'] ?? ''), ['approved', 'reserved', 'partial_delivered'], true)): ?>
                                <a href="<?= site_url('sales/orders/' . $order['id'] . '/deliver') ?>" class="btn btn-sm btn-outline-success">Deliver</a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if ($orders === []): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No sales orders yet.</td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
