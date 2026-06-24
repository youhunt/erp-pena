<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">Forecast Demand</h4>
                <p class="text-muted mb-0">Input demand forecast as the source for MRP planning.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/mrp') ?>" class="btn btn-outline-primary">MRP</a>
                <a href="<?= site_url('production/forecasts/new') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Forecast</a>
            </div>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <form method="get" class="row g-2 mb-3">
            <div class="col-md-4"><input name="q" class="form-control" placeholder="Search item / forecast no" value="<?= esc($q ?? '') ?>"></div>
            <div class="col-md-2"><button class="btn btn-light w-100">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Forecast No</th>
                        <th>Date</th>
                        <th>Site</th>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th>UoM</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No forecast data yet.</td></tr>
                <?php endif ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= esc($row['forecast_no'] ?? '') ?></td>
                        <td><?= esc($row['forecast_date'] ?? '') ?></td>
                        <td><?= esc($row['site_code'] ?? '') ?></td>
                        <td><strong><?= esc($row['item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($row['item_name'] ?? '') ?></small></td>
                        <td class="text-end"><?= number_format((float) ($row['qty'] ?? 0), 4) ?></td>
                        <td><?= esc($row['uom_code'] ?? '') ?></td>
                        <td><span class="badge bg-success-subtle text-success"><?= esc($row['status'] ?? '') ?></span></td>
                        <td><?= esc($row['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
