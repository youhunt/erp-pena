<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                        <p class="text-muted mb-0">Upload .xlsx, .csv, atau .tsv. Data akan dibuat sebagai draft <?= esc($typeLabel) ?> pada company/site aktif.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= site_url($templateUrl) ?>" class="btn btn-outline-primary">
                            <i class="bx bx-download me-1"></i> Template
                        </a>
                        <a href="<?= site_url($backUrl) ?>" class="btn btn-light">Back</a>
                    </div>
                </div>

                <?php if (session('error')): ?>
                    <div class="alert alert-danger"><?= esc(session('error')) ?></div>
                <?php endif ?>

                <form action="<?= site_url($importUrl) ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Import File</label>
                        <input type="file" name="order_file" class="form-control" accept=".xlsx,.csv,.tsv" required>
                        <div class="form-text">Satu nomor dokumen boleh terdiri dari banyak row. Row dengan nomor dokumen sama akan menjadi line item dalam dokumen yang sama.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-upload me-1"></i> Import Draft
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Format Kolom</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-nowrap mb-0">
                        <tbody>
                        <?php foreach ($headers as $header): ?>
                            <tr>
                                <td><code><?= esc($header) ?></code></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3">Contoh Isi</h5>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <th><?= esc($header) ?></th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sampleRows as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?= esc($value) ?></td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
