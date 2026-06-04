<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Work Order In</h4>
                <p class="text-muted mb-0">Finished good receipts from production into inventory.</p>
            </div>
            <a href="<?= site_url('production/work-order-in/new') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> Post Work Order In</a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>WO No</th>
                        <th>Date</th>
                        <th>Finished Good</th>
                        <th class="text-end">Good Qty</th>
                        <th class="text-end">Reject Qty</th>
                        <th>UoM</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($order['wo_no'] ?? '-') ?></td>
                        <td><?= esc($order['wo_date'] ?? '-') ?></td>
                        <td><div><?= esc($order['finished_item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($order['finished_item_name'] ?? '-') ?></small></td>
                        <td class="text-end"><?= esc(number_format((float) ($order['qty_good'] ?? 0), 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($order['qty_reject'] ?? 0), 4)) ?></td>
                        <td><?= esc($order['uom_code'] ?? '-') ?></td>
                        <td><span class="badge bg-success"><?= esc($order['status'] ?? '-') ?></span></td>
                        <td class="text-end"><a href="<?= site_url('production/work-order-in/' . $order['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                <?php endforeach ?>

                <?php if ($orders === []): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No Work Order In posted yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
