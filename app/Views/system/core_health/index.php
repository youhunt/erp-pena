<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Database</p><h5 class="mb-0"><?= esc($selectedDatabase) ?></h5></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Passed</p><h4 class="text-success mb-0"><?= esc((string) $passedCount) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Failed</p><h4 class="<?= $failedCount > 0 ? 'text-danger' : 'text-success' ?> mb-0"><?= esc((string) $failedCount) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Generated</p><h6 class="mb-0"><?= esc($generatedAt) ?></h6></div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="card-title mb-1">ERP Core Health Check</h4>
                <p class="text-muted mb-0">Validasi fondasi ERP sebelum lanjut UAT, MRP, dan modul advanced.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('system/core-health') ?>" class="btn btn-light"><i class="bx bx-refresh me-1"></i> Refresh</a>
                <a href="<?= site_url('system/development-status') ?>" class="btn btn-outline-primary">Development Status</a>
            </div>
        </div>

        <?php if ($failedCount === 0): ?>
            <div class="alert alert-success mb-3"><strong>CORE HEALTH PASS.</strong> ERP core siap untuk step berikutnya.</div>
        <?php else: ?>
            <div class="alert alert-warning mb-3"><strong>CORE HEALTH NEEDS ATTENTION.</strong> Bereskan check yang FAIL sebelum lanjut proses besar.</div>
        <?php endif ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Status</th>
                        <th>Check</th>
                        <th class="text-end">Actual</th>
                        <th class="text-end">Expected</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <?php
                        $badge = $check['pass'] ? 'bg-success-subtle text-success' : ($check['level'] === 'critical' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning');
                    ?>
                    <tr>
                        <td><span class="badge <?= $badge ?>"><?= $check['pass'] ? 'PASS' : 'FAIL' ?></span></td>
                        <td class="fw-semibold"><code><?= esc($check['name']) ?></code></td>
                        <td class="text-end"><?= esc((string) $check['total']) ?></td>
                        <td class="text-end"><?= esc((string) $check['expected']) ?></td>
                        <td><?= esc($check['note']) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($failed !== []): ?>
<div class="card">
    <div class="card-body">
        <h4 class="card-title mb-3">Priority Fix</h4>
        <ol class="mb-0">
            <?php foreach ($failed as $item): ?>
                <li class="mb-2"><code><?= esc($item['name']) ?></code> — <?= esc($item['note']) ?></li>
            <?php endforeach ?>
        </ol>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>
