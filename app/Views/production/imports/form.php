<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($title ?? 'Production Import') ?></h4>
                <p class="text-muted mb-0">Upload file, sistem akan melakukan preview dan validasi dulu. Data belum masuk database sampai tombol <strong>Commit Import</strong> ditekan.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/imports/' . esc($resource, 'url') . '/template') ?>" class="btn btn-outline-primary"><i class="bx bx-download me-1"></i> Download Template</a>
                <a href="<?= site_url($config['return_to'] ?? 'production') ?>" class="btn btn-light">Back</a>
            </div>
        </div>

        <form method="post" action="<?= site_url('production/imports/' . esc($resource, 'url')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Excel / CSV File</label>
                    <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv,.tsv,.txt" required>
                    <small class="text-muted">Maksimal 10 MB. Format disarankan .xlsx dari template.</small>
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bx bx-search-alt me-1"></i> Preview & Validate</button>
                </div>
            </div>
        </form>

        <div class="alert alert-info mb-0">
            <div class="fw-semibold mb-2">Kolom template:</div>
            <div class="small text-break"><?= esc(implode(', ', $config['headers'] ?? [])) ?></div>
            <hr>
            <div class="small mb-0">
                Semua import production wajib memakai <strong>site_code</strong>. BOM, Routing, dan Work Order wajib memakai <strong>line_no</strong>. Work Center tidak memakai line_no.
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
