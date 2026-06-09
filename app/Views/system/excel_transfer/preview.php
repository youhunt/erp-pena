<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1">Preview Import <?= esc($config['title']) ?></h4>
                        <p class="text-muted mb-0">Review validation results before posting data to database.</p>
                    </div>
                    <a href="<?= site_url($returnTo) ?>" class="btn btn-light">Back</a>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="card border mb-0"><div class="card-body py-3"><p class="text-muted mb-1">Total Rows</p><h4 class="mb-0"><?= esc((string) $summary['total']) ?></h4></div></div></div>
                    <div class="col-md-3"><div class="card border mb-0"><div class="card-body py-3"><p class="text-muted mb-1">Valid Rows</p><h4 class="mb-0 text-success"><?= esc((string) $summary['valid']) ?></h4></div></div></div>
                    <div class="col-md-3"><div class="card border mb-0"><div class="card-body py-3"><p class="text-muted mb-1">Error Rows</p><h4 class="mb-0 text-danger"><?= esc((string) $summary['error']) ?></h4></div></div></div>
                    <div class="col-md-3"><div class="card border mb-0"><div class="card-body py-3"><p class="text-muted mb-1">Mode</p><h4 class="mb-0">Preview</h4></div></div></div>
                </div>

                <?php if ($summary['error'] > 0): ?>
                    <div class="alert alert-danger">
                        Ada error pada file Excel. Data belum diposting ke database. Perbaiki baris yang error, lalu upload ulang file Excel.
                    </div>
                    <div class="mb-3">
                        <a href="<?= site_url($downloadErrorUrl) ?>" class="btn btn-outline-danger">
                            <i class="bx bx-download me-1"></i> Download Error Excel
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        Semua baris valid. Klik <strong>Post Valid Rows</strong> untuk menyimpan data ke database.
                    </div>
                    <form method="post" action="<?= site_url($commitUrl) ?>" class="mb-3" onsubmit="return confirm('Post all valid rows to database?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="preview_token" value="<?= esc($previewToken) ?>">
                        <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                        <button type="submit" class="btn btn-success">
                            <i class="bx bx-check-circle me-1"></i> Post Valid Rows
                        </button>
                    </form>
                <?php endif ?>

                <?php if (! empty($errors)): ?>
                    <h5 class="mb-3">Validation Errors</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light"><tr><th style="width: 100px;">Row</th><th>Error</th></tr></thead>
                            <tbody>
                            <?php foreach ($errors as $error): ?>
                                <tr><td><?= esc((string) $error['row']) ?></td><td><?= esc($error['message']) ?></td></tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>

                <?php if (! empty($previewRows)): ?>
                    <h5 class="mb-3">Valid Row Preview</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Excel Row</th>
                                    <?php foreach ($headers as $header): ?><th><?= esc($header) ?></th><?php endforeach ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($previewRows as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?= esc((string) $row['_row_number']) ?></td>
                                    <?php foreach ($headers as $header): ?><td><?= esc((string) ($row[$header] ?? '')) ?></td><?php endforeach ?>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
