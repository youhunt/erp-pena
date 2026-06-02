<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Sales Orders</h4>
                <p class="text-muted mb-0">Manage sales orders by active company/site.</p>
            </div>
            <a href="<?= site_url('sales/orders/new') ?>" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> New SO
            </a>
        </div>

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
