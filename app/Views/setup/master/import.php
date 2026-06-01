<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-2"><?= esc($title) ?></h4>
                <p class="text-muted">Upload CSV data for <?= esc($config['title']) ?>. Existing rows with the same code will be updated.</p>

                <div class="alert alert-info">
                    <div class="fw-semibold mb-1">Required CSV headers</div>
                    <code><?= esc(implode(',', $headers)) ?></code>
                </div>

                <form action="<?= site_url("setup/{$resource}/import") ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="csv_file">CSV File</label>
                        <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv,text/plain" required>
                        <div class="form-text">Use UTF-8 CSV. Maximum upload size follows your PHP/server configuration.</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-success waves-effect waves-light" type="submit">
                            <i class="bx bx-upload me-1"></i> Import CSV
                        </button>
                        <a class="btn btn-outline-secondary" href="<?= site_url("setup/{$resource}/template") ?>">
                            <i class="bx bx-file me-1"></i> Download Template
                        </a>
                        <a class="btn btn-light" href="<?= site_url("setup/{$resource}") ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Import Rules</h5>
                <ul class="mb-0 ps-3">
                    <li>CSV header must match the template.</li>
                    <li>Rows with the same <code>code</code> are updated.</li>
                    <li>Blank optional fields are saved as empty/null.</li>
                    <li>Company/site columns are filled automatically from active company/site.</li>
                    <li>Use IDs for relation fields such as <code>warehouse_id</code>, <code>item_id</code>, or <code>uom_id</code>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
