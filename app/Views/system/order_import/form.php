<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                        <p class="text-muted mb-0">Upload .xlsx, .csv, atau .tsv. File akan divalidasi dulu sebelum dibuat sebagai draft <?= esc($typeLabel) ?>.</p>
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
                <?php if (session('message')): ?>
                    <div class="alert alert-success"><?= esc(session('message')) ?></div>
                <?php endif ?>

                <form action="<?= site_url($importUrl) ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Import File</label>
                        <input type="file" name="order_file" class="form-control" accept=".xlsx,.csv,.tsv" required>
                        <div class="form-text">Satu nomor dokumen boleh terdiri dari banyak row. Row dengan nomor dokumen sama akan menjadi line item dalam dokumen yang sama.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-search-alt me-1"></i> Preview Import
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

<?php if ($preview !== null): ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h5 class="card-title mb-1">Import Preview</h5>
                <p class="text-muted mb-0">
                    <?= esc($preview['filename'] ?? '-') ?>,
                    <?= esc($preview['valid_documents'] ?? 0) ?> valid document,
                    <?= esc($preview['valid_lines'] ?? 0) ?> valid line.
                </p>
            </div>
            <?php if (($preview['errors'] ?? []) === [] && ($previewToken ?? '') !== ''): ?>
                <form action="<?= site_url($commitUrl) ?>" method="post" class="mb-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="preview_token" value="<?= esc($previewToken) ?>">
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-check me-1"></i> Post Import
                    </button>
                </form>
            <?php endif ?>
        </div>

        <?php if (($preview['errors'] ?? []) !== []): ?>
            <div class="alert alert-warning">Masih ada error. Perbaiki file lalu upload ulang.</div>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-bordered table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Excel Row</th>
                            <th>Document No</th>
                            <th>Item Code</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($preview['errors'] ?? []) as $error): ?>
                        <tr>
                            <td><?= esc($error['excel_row'] ?? '-') ?></td>
                            <td><?= esc($error['document_no'] ?? '-') ?></td>
                            <td><?= esc($error['item_code'] ?? '-') ?></td>
                            <td class="text-danger"><?= esc($error['message'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Excel Row</th>
                        <th>Document No</th>
                        <th>Line</th>
                        <th>Partner</th>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th>UoM</th>
                        <th class="text-end">Unit Price</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice(($preview['valid_rows'] ?? []), 0, 100) as $row): ?>
                    <tr>
                        <td><?= esc($row['excel_row'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= esc($row['document_no'] ?? '-') ?></td>
                        <td class="text-end"><?= esc($row['line'] ?? '-') ?></td>
                        <td><?= esc(trim(($row['partner_code'] ?? '') . ' ' . ($row['partner_name'] ?? ''))) ?></td>
                        <td><?= esc(trim(($row['item_code'] ?? '') . ' - ' . ($row['item_name'] ?? ''))) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['qty'] ?? 0), 2)) ?></td>
                        <td><?= esc($row['uom_code'] ?? '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['unit_price'] ?? 0), 2)) ?></td>
                    </tr>
                <?php endforeach ?>

                <?php if (($preview['valid_rows'] ?? []) === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No valid rows.</td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
        <?php if (($preview['valid_lines'] ?? 0) > 100): ?>
            <p class="text-muted mt-2 mb-0">Preview table shows first 100 valid rows only.</p>
        <?php endif ?>
    </div>
</div>
<?php endif ?>

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
