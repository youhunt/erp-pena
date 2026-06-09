<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                        <p class="text-muted mb-0">Upload XLSX spreadsheet file for <?= esc($module) ?>.</p>
                    </div>
                    <a href="<?= site_url('system/data-import') ?>" class="btn btn-light">Back</a>
                </div>

                <div class="alert alert-warning">
                    Please download the XLSX template first and keep the header columns unchanged.
                </div>

                <div class="mb-3">
                    <a href="<?= esc($templateUrl) ?>" class="btn btn-outline-secondary">
                        <i class="bx bx-download me-1"></i> Download XLSX Template
                    </a>
                </div>

                <form method="post" action="<?= esc($actionUrl) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Spreadsheet File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".xlsx,.xls,.csv,.txt" required>
                        <div class="form-text">Recommended format: XLSX. CSV/TXT is still accepted for legacy imports.</div>
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
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Import this spreadsheet file?')">
                            <i class="bx bx-upload me-1"></i> Import Spreadsheet
                        </button>
                        <a href="<?= site_url('system/data-import') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
