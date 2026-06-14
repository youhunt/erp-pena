<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($transfer['transfer_no']) ?></h4>
                <p class="text-muted mb-0">Inventory transfer document detail.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('inventory/transfers/new') ?>" class="btn btn-primary">New Transfer</a>
                <a href="<?= site_url('inventory/transfers') ?>" class="btn btn-light">Back</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="text-muted">Date</div>
                <div class="fw-semibold"><?= esc(substr((string) $transfer['transfer_date'], 0, 10)) ?></div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="text-muted">Status</div>
                <span class="badge bg-success-subtle text-success"><?= esc(ucfirst((string) $transfer['status'])) ?></span>
            </div>
            <div class="col-md-3 mb-3">
                <div class="text-muted">Posted At</div>
                <div class="fw-semibold"><?= esc($transfer['posted_at'] ?? '-') ?></div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="text-muted">Posted By</div>
                <div class="fw-semibold"><?= esc($transfer['posted_by'] ?? '-') ?></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted mb-1">Source</div>
                    <div class="fw-semibold"><?= esc($transfer['from_warehouse_code'] ?? 'No Warehouse') ?> - <?= esc($transfer['from_warehouse_name'] ?? '-') ?></div>
                    <div><?= esc($transfer['from_location_code'] ?? 'No Location') ?> <?= ! empty($transfer['from_location_name']) ? '- ' . esc($transfer['from_location_name']) : '' ?></div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted mb-1">Destination</div>
                    <div class="fw-semibold"><?= esc($transfer['to_warehouse_code'] ?? 'No Warehouse') ?> - <?= esc($transfer['to_warehouse_name'] ?? '-') ?></div>
                    <div><?= esc($transfer['to_location_code'] ?? 'No Location') ?> <?= ! empty($transfer['to_location_name']) ? '- ' . esc($transfer['to_location_name']) : '' ?></div>
                </div>
            </div>
        </div>

        <?php if (! empty($transfer['notes'])): ?>
            <div class="alert alert-light border mb-0"><?= nl2br(esc($transfer['notes'])) ?></div>
        <?php endif ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3">Transfer Lines</h5>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-end">#</th>
                        <th>Item</th>
                        <th>UoM</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Out Movement</th>
                        <th class="text-end">In Movement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr>
                            <td class="text-end"><?= (int) $line['line_no'] ?></td>
                            <td>
                                <div class="fw-semibold"><?= esc($line['item_code']) ?></div>
                                <div class="text-muted small"><?= esc($line['item_name'] ?? '') ?></div>
                            </td>
                            <td><?= esc($line['uom_code']) ?></td>
                            <td class="text-end"><?= number_format((float) $line['qty'], 4) ?></td>
                            <td class="text-end"><?= number_format((float) $line['unit_cost'], 4) ?></td>
                            <td class="text-end"><?= esc($line['transfer_out_movement_id'] ?? '-') ?></td>
                            <td class="text-end"><?= esc($line['transfer_in_movement_id'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
