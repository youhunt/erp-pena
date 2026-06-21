<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rows = $preview['rows'] ?? [];
$headers = $config['headers'] ?? [];
$hasErrors = (bool) ($preview['has_errors'] ?? true);
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($title ?? 'Preview Import') ?></h4>
                <p class="text-muted mb-0">Periksa hasil validasi. Data belum masuk database sampai tombol <strong>Commit Import</strong> ditekan.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/imports/' . esc($resource, 'url')) ?>" class="btn btn-light">Upload Ulang</a>
                <a href="<?= site_url($config['return_to'] ?? 'production') ?>" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3 mb-2"><div class="border rounded p-3"><div class="text-muted small">Total Rows</div><div class="h4 mb-0"><?= esc($preview['total'] ?? 0) ?></div></div></div>
            <div class="col-md-3 mb-2"><div class="border rounded p-3"><div class="text-muted small">Valid</div><div class="h4 mb-0 text-success"><?= esc($preview['valid'] ?? 0) ?></div></div></div>
            <div class="col-md-3 mb-2"><div class="border rounded p-3"><div class="text-muted small">Error</div><div class="h4 mb-0 text-danger"><?= esc($preview['error'] ?? 0) ?></div></div></div>
            <div class="col-md-3 mb-2 d-flex align-items-center">
                <?php if (! $hasErrors && ! empty($token)): ?>
                    <form method="post" action="<?= site_url('production/imports/' . esc($resource, 'url')) ?>" class="w-100">
                        <?= csrf_field() ?>
                        <input type="hidden" name="commit_token" value="<?= esc($token, 'attr') ?>">
                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Commit import <?= esc($config['title'] ?? 'Production') ?> sekarang?')"><i class="bx bx-check me-1"></i> Commit Import</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Commit Disabled</button>
                <?php endif ?>
            </div>
        </div>

        <?php if ($hasErrors): ?>
            <div class="alert alert-danger">Masih ada error. Perbaiki file lalu upload ulang. Data tidak akan di-commit sebelum semua baris valid.</div>
        <?php else: ?>
            <div class="alert alert-success">Semua baris valid. Silakan klik <strong>Commit Import</strong> untuk menyimpan ke database.</div>
        <?php endif ?>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Excel Row</th>
                        <th>Status</th>
                        <th>Message</th>
                        <?php foreach ($headers as $header): ?>
                            <th><?= esc($header) ?></th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr class="<?= ($row['status'] ?? '') === 'error' ? 'table-danger' : '' ?>">
                            <td><?= esc($row['row_number'] ?? '-') ?></td>
                            <td>
                                <?php if (($row['status'] ?? '') === 'valid'): ?>
                                    <span class="badge bg-success">VALID</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">ERROR</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if (! empty($row['errors'])): ?>
                                    <div class="text-danger"><?= esc(implode('; ', $row['errors'])) ?></div>
                                <?php endif ?>
                                <?php if (! empty($row['warnings'])): ?>
                                    <div class="text-warning"><?= esc(implode('; ', $row['warnings'])) ?></div>
                                <?php endif ?>
                                <?php if (empty($row['errors']) && empty($row['warnings'])): ?>
                                    <span class="text-muted">-</span>
                                <?php endif ?>
                            </td>
                            <?php foreach ($headers as $header): ?>
                                <td><?= esc($row['data'][$header] ?? '') ?></td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="<?= count($headers) + 3 ?>" class="text-center text-muted py-4">No preview rows.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
