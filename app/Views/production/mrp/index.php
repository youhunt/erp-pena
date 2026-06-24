<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">MRP Planning</h4>
                <p class="text-muted mb-0">Generate material requirement from forecast demand and BOM.</p>
            </div>
            <a href="<?= site_url('production/forecasts') ?>" class="btn btn-outline-primary">Forecast</a>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <form method="post" action="<?= site_url('production/mrp/run') ?>" class="row g-3 mb-4 border rounded p-3 bg-light">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" required value="<?= esc(old('from_date', $defaultFrom)) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" required value="<?= esc(old('to_date', $defaultTo)) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Item Filter</label>
                <select name="item_code" class="form-select">
                    <option value="">All forecast items</option>
                    <?php foreach ($items as $item): ?><?php $code = (string) ($item['item_code'] ?? $item['code'] ?? ''); ?>
                        <option value="<?= esc($code, 'attr') ?>"><?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '')) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit"><i class="bx bx-cog me-1"></i> Generate</button>
            </div>
        </form>

        <h5 class="mb-3">MRP Runs</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Run No</th>
                        <th>Period</th>
                        <th class="text-end">Demand</th>
                        <th class="text-end">Lines</th>
                        <th class="text-end">Gross Qty</th>
                        <th class="text-end">Net Qty</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($runs === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No MRP run yet.</td></tr><?php endif ?>
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td><strong><?= esc($run['run_no'] ?? '') ?></strong></td>
                        <td><?= esc(($run['from_date'] ?? '') . ' - ' . ($run['to_date'] ?? '')) ?></td>
                        <td class="text-end"><?= number_format((float) ($run['demand_count'] ?? 0), 0) ?></td>
                        <td class="text-end"><?= number_format((float) ($run['line_count'] ?? 0), 0) ?></td>
                        <td class="text-end"><?= number_format((float) ($run['gross_qty'] ?? 0), 4) ?></td>
                        <td class="text-end"><?= number_format((float) ($run['net_qty'] ?? 0), 4) ?></td>
                        <td><span class="badge bg-primary-subtle text-primary"><?= esc($run['status'] ?? '') ?></span></td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="<?= site_url('production/mrp/runs/' . (int) $run['id']) ?>">Open</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
