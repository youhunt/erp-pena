<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('period-close') ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1">Close Period</h4>
                    <p class="text-muted mb-0">Closing a period blocks future posting for that module and month.</p>
                </div>
                <a href="<?= site_url('period-close/' . $module) ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Module</label>
                    <select name="module_code" class="form-select" required>
                        <?php foreach ($modules as $code => $label): ?>
                            <option value="<?= esc($code) ?>" <?= old('module_code', $module) === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Period</label>
                    <input type="month" name="period" class="form-control" required value="<?= esc(old('period', date('Y-m'))) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Close this period?')"><i class="bx bx-lock me-1"></i> Close Period</button>
                <a href="<?= site_url('period-close/' . $module) ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
