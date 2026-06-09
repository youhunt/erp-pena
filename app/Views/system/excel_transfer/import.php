<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                        <p class="text-muted mb-0">Upload native Microsoft Excel file for <?= esc($config['title']) ?>.</p>
                    </div>
                    <a href="<?= site_url('system/excel-transfer') ?>" class="btn btn-light">Back</a>
                </div>

                <div class="alert alert-warning">
                    Download the Excel template first, fill data in Microsoft Excel, and keep the first-row headers unchanged.
                </div>

                <div class="mb-3">
                    <a href="<?= site_url('system/excel-transfer/' . $resource . '/template') ?>" class="btn btn-outline-secondary">
                        <i class="bx bx-download me-1"></i> Download Excel Template
                    </a>
                </div>

                <form method="post" action="<?= site_url('system/excel-transfer/' . $resource . '/import') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Excel File (.xlsx)</label>
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                        <div class="form-text">Maximum file size: 10 MB.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Required / Supported Headers</label>
                        <div class="border rounded p-3 bg-light">
                            <?php foreach ($headers as $header): ?>
                                <code class="me-2 d-inline-block mb-1"><?= esc($header) ?></code>
                            <?php endforeach ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Import this Excel file?')">
                            <i class="bx bx-upload me-1"></i> Import Excel
                        </button>
                        <a href="<?= site_url('system/excel-transfer') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
