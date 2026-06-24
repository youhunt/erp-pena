<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="card-title mb-1">Master Production Schedule</h4>
                <p class="text-muted mb-0">MPS mengambil Forecast Demand dan menyiapkan jadwal produksi sebelum MRP.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/forecasts') ?>" class="btn btn-outline-primary">Forecast</a>
                <a href="<?= site_url('production/mrp') ?>" class="btn btn-success">MRP</a>
            </div>
        </div>

        <form method="get" action="<?= site_url('production/mps') ?>" class="row g-3 mb-4 border rounded p-3 bg-light">
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= esc($fromDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= esc($toDate) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Refresh</button>
            </div>
        </form>

        <div class="row mb-4">
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">MPS Items</div><h4><?= number_format((float) ($summary['items'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Total Qty</div><h4><?= number_format((float) ($summary['qty'] ?? 0), 4) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Ready BOM</div><h4 class="text-success"><?= number_format((float) ($summary['with_bom'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Need BOM</div><h4 class="text-warning"><?= number_format((float) ($summary['without_bom'] ?? 0), 0) ?></h4></div></div>
        </div>

        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                MPS ini adalah jadwal produksi awal dari forecast. Item dengan status <strong>Ready for MRP</strong> bisa diproses di MRP. Item dengan status <strong>Create BOM</strong> perlu BOM aktif dulu.
            </div>
            <?php if (($summary['items'] ?? 0) > 0): ?>
                <form method="post" action="<?= site_url('production/mrp/run') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="from_date" value="<?= esc($fromDate, 'attr') ?>">
                    <input type="hidden" name="to_date" value="<?= esc($toDate, 'attr') ?>">
                    <input type="hidden" name="item_code" value="">
                    <button type="submit" class="btn btn-success btn-sm">
                        Generate MRP from MPS Period
                    </button>
                </form>
            <?php endif ?>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Period</th>
                        <th>UoM</th>
                        <th class="text-end">Forecast Qty</th>
                        <th class="text-end">MPS Qty</th>
                        <th>BOM</th>
                        <th>Suggested Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No forecast demand found for this period.</td></tr><?php endif ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= esc($row['item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($row['item_name'] ?? '') ?></small></td>
                        <td><?= esc(($row['first_date'] ?? '') . ' - ' . ($row['last_date'] ?? '')) ?></td>
                        <td><?= esc($row['uom_code'] ?? '') ?></td>
                        <td class="text-end"><?= number_format((float) ($row['forecast_qty'] ?? 0), 6) ?></td>
                        <td class="text-end fw-semibold"><?= number_format((float) ($row['mps_qty'] ?? 0), 6) ?></td>
                        <td>
                            <?php if (! empty($row['has_bom'])): ?>
                                <span class="badge bg-success-subtle text-success">Ready</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning">Need BOM</span>
                            <?php endif ?>
                        </td>
                        <td><code><?= esc($row['suggested_action'] ?? '') ?></code></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
