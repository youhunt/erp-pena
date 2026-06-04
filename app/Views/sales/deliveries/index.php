<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Delivery Orders</h4>
                <p class="text-muted mb-0">Posted delivery documents that decreased inventory stock.</p>
            </div>
            <a href="<?= site_url('sales/orders') ?>" class="btn btn-primary">
                <i class="bx bx-cart me-1"></i> Open Sales Orders
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Delivery No</th>
                        <th>Date</th>
                        <th>SO No</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Posted At</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($deliveries as $delivery): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($delivery['delivery_no'] ?? '-') ?></td>
                        <td><?= esc($delivery['delivery_date'] ?? '-') ?></td>
                        <td><a href="<?= site_url('sales/orders/' . $delivery['sales_order_id']) ?>"><?= esc($delivery['so_no'] ?? '-') ?></a></td>
                        <td>
                            <div><?= esc($delivery['customer_name'] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($delivery['customer_code'] ?? '-') ?></small>
                        </td>
                        <td><span class="badge bg-success"><?= esc($delivery['status'] ?? '-') ?></span></td>
                        <td><?= esc($delivery['posted_at'] ?? '-') ?></td>
                        <td class="text-end">
                            <a href="<?= site_url('sales/deliveries/' . $delivery['id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if ($deliveries === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No delivery order posted yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
