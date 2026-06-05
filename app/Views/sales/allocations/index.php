<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">Allocation Order</h4>
                <p class="text-muted mb-0">Posted allocation documents that reserve stock for Sales Orders.</p>
            </div>
            <a href="<?= site_url('sales/orders') ?>" class="btn btn-primary"><i class="bx bx-list-ul me-1"></i> Open Sales Orders</a>
        </div>
        <div class="table-responsive">
            <table class="table table-nowrap align-middle mb-0">
                <thead class="table-light"><tr><th>Allocation No</th><th>Date</th><th>Customer</th><th>Site</th><th>Warehouse</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($allocations as $allocation): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($allocation['allocnumb'] ?? '-') ?></td>
                        <td><?= esc($allocation['allocdate'] ?? '-') ?></td>
                        <td><div><?= esc($allocation['customern'] ?? '-') ?></div><small class="text-muted"><?= esc($allocation['customer'] ?? '-') ?></small></td>
                        <td><?= esc($allocation['site'] ?? '-') ?></td>
                        <td><?= esc($allocation['whs'] ?? '-') ?></td>
                        <td><span class="badge bg-success"><?= esc($allocation['status'] ?? 'posted') ?></span></td>
                        <td class="text-end"><a href="<?= site_url('sales/allocations/' . $allocation['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($allocations === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No allocation order posted yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
