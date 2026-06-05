<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Allocation Order</h4>
                        <p class="text-muted mb-0"><?= esc($allocation['allocnumb']) ?></p>
                    </div>
                    <span class="badge bg-success"><?= esc($allocation['status'] ?? 'posted') ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tr><th>Allocation No</th><td><?= esc($allocation['allocnumb']) ?></td></tr>
                    <tr><th>Date</th><td><?= esc($allocation['allocdate']) ?></td></tr>
                    <tr><th>Customer</th><td><?= esc(($allocation['customer'] ?? '-') . ' ' . ($allocation['customern'] ?? '')) ?></td></tr>
                    <tr><th>Site</th><td><?= esc($allocation['site'] ?? '-') ?></td></tr>
                    <tr><th>Warehouse</th><td><?= esc($allocation['whs'] ?? '-') ?></td></tr>
                    <tr><th>Ship To</th><td><?= esc($allocation['shipto'] ?? '-') ?></td></tr>
                    <tr><th>Posted</th><td><?= esc($allocation['posted_at'] ?? '-') ?></td></tr>
                </table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= site_url('sales/allocations') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                    <?php if (! empty($allocation['sales_order_id'])): ?>
                        <a href="<?= site_url('sales/orders/' . $allocation['sales_order_id']) ?>" class="btn btn-outline-primary">Open SO</a>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Allocation Lines</h4>
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>Line</th><th>Item</th><th>SO</th><th class="text-end">SO Qty</th><th class="text-end">Available</th><th class="text-end">Allocated</th><th>UoM</th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['line'] ?? '-') ?></td>
                                <td><div class="fw-semibold"><?= esc($line['itemcode'] ?? '-') ?></div><small class="text-muted"><?= esc($line['itemname'] ?? '-') ?></small></td>
                                <td><?= esc($line['salesorder'] ?? '-') ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['soqty'] ?? 0), 4)) ?></td>
                                <td class="text-end"><?= esc(number_format((float) ($line['availableqty'] ?? 0), 4)) ?></td>
                                <td class="text-end fw-semibold"><?= esc(number_format((float) ($line['allocateqty'] ?? 0), 4)) ?></td>
                                <td><?= esc($line['allocateuom'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($lines === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No allocation line.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if (! empty($allocation['remarks'])): ?>
            <div class="card"><div class="card-body"><h4 class="card-title mb-3">Remarks</h4><p class="text-muted mb-0"><?= esc($allocation['remarks']) ?></p></div></div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
