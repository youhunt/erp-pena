<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Inventory Transfers</h4>
                <p class="text-muted mb-0">Formal warehouse/location transfer documents with posted stock movements.</p>
            </div>
            <a href="<?= site_url('inventory/transfers/new') ?>" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> New Transfer
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Transfer No</th>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th class="text-end">Lines</th>
                        <th class="text-end">Total Qty</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transfers)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No inventory transfer document yet.</td>
                        </tr>
                    <?php endif ?>

                    <?php foreach ($transfers as $transfer): ?>
                        <?php
                        $status = (string) ($transfer['status'] ?? 'draft');
                        $statusClass = match ($status) {
                            'draft' => 'bg-secondary-subtle text-secondary',
                            'submitted' => 'bg-info-subtle text-info',
                            'posted' => 'bg-success-subtle text-success',
                            'cancelled' => 'bg-danger-subtle text-danger',
                            'reversed' => 'bg-warning-subtle text-warning',
                            default => 'bg-light text-dark',
                        };
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($transfer['transfer_no']) ?></td>
                            <td><?= esc(substr((string) $transfer['transfer_date'], 0, 10)) ?></td>
                            <td>
                                <?= esc($transfer['from_warehouse_code'] ?? 'No Warehouse') ?>
                                <?php if (! empty($transfer['from_location_code'])): ?>
                                    <span class="text-muted">/ <?= esc($transfer['from_location_code']) ?></span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?= esc($transfer['to_warehouse_code'] ?? 'No Warehouse') ?>
                                <?php if (! empty($transfer['to_location_code'])): ?>
                                    <span class="text-muted">/ <?= esc($transfer['to_location_code']) ?></span>
                                <?php endif ?>
                            </td>
                            <td class="text-end"><?= number_format((float) ($transfer['line_count'] ?? 0)) ?></td>
                            <td class="text-end"><?= number_format((float) ($transfer['total_qty'] ?? 0), 4) ?></td>
                            <td><span class="badge <?= esc($statusClass) ?>"><?= esc(ucfirst($status)) ?></span></td>
                            <td class="text-end">
                                <a href="<?= site_url('inventory/transfers/' . (int) $transfer['id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
