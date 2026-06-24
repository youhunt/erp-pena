<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="card-title mb-1">Create Forecast</h4>
                <p class="text-muted mb-0">Forecast will be used as demand source for MRP.</p>
            </div>
            <a href="<?= site_url('production/forecasts') ?>" class="btn btn-light">Back</a>
        </div>

        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <form method="post" action="<?= site_url('production/forecasts') ?>">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Site</label>
                    <select name="site_code" class="form-select" required>
                        <option value="">Select site</option>
                        <?php foreach ($sites as $site): ?><?php $code = (string) ($site['code'] ?? ''); ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= old('site_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($site['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Forecast Date</label>
                    <input type="date" name="forecast_date" class="form-control" required value="<?= esc(old('forecast_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Item</label>
                    <select name="item_code" class="form-select" required>
                        <option value="">Select item</option>
                        <?php foreach ($items as $item): ?><?php $code = (string) ($item['item_code'] ?? $item['code'] ?? ''); ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= old('item_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Qty Forecast</label>
                    <input type="number" step="0.000001" name="qty" class="form-control" required value="<?= esc(old('qty', '0')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">UoM</label>
                    <input name="uom_code" class="form-control" maxlength="20" value="<?= esc(old('uom_code', 'PCS')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Source Type</label>
                    <select name="source_type" class="form-select">
                        <option value="manual">Manual</option>
                        <option value="sales_estimate">Sales Estimate</option>
                        <option value="customer_plan">Customer Plan</option>
                    </select>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <input name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> Save Forecast</button>
                <a href="<?= site_url('production/forecasts') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
