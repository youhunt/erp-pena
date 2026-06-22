<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($modules[$period['module_code']] ?? $period['module_code']) ?> Period <?= esc($period['period']) ?></h4>
                <p class="text-muted mb-0">Status: <?= esc($period['status']) ?></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= site_url('period-close/' . $period['id'] . '/export') ?>" class="btn btn-outline-success"><i class="bx bx-download me-1"></i> Export XLSX</a>
                <a href="<?= site_url('period-close/' . $period['module_code']) ?>" class="btn btn-light">Back</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><div class="text-muted">Module</div><div class="fw-semibold"><?= esc($modules[$period['module_code']] ?? $period['module_code']) ?></div></div>
            <div class="col-md-3 mb-3"><div class="text-muted">Period</div><div class="fw-semibold"><?= esc($period['period']) ?></div></div>
            <div class="col-md-3 mb-3"><div class="text-muted">Start</div><div class="fw-semibold"><?= esc($period['period_start']) ?></div></div>
            <div class="col-md-3 mb-3"><div class="text-muted">End</div><div class="fw-semibold"><?= esc($period['period_end']) ?></div></div>
        </div>

        <table class="table table-bordered mb-4">
            <tbody>
                <tr><th style="width:220px;">Status</th><td><?= esc($period['status']) ?></td></tr>
                <tr><th>Closed At</th><td><?= esc($period['closed_at'] ?? '-') ?></td></tr>
                <tr><th>Reopened At</th><td><?= esc($period['reopened_at'] ?? '-') ?></td></tr>
                <tr><th>Notes</th><td><?= esc($period['notes'] ?: '-') ?></td></tr>
            </tbody>
        </table>

        <div class="d-flex flex-wrap gap-2">
            <?php if (($period['status'] ?? '') === 'closed'): ?>
                <form method="post" action="<?= site_url('period-close/' . $period['id'] . '/reopen') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Reopen this period?')"><i class="bx bx-lock-open me-1"></i> Reopen Period</button>
                </form>
            <?php endif ?>
            <a href="<?= site_url('period-close/' . $period['id'] . '/export') ?>" class="btn btn-outline-success"><i class="bx bx-download me-1"></i> Export XLSX</a>
            <a href="<?= site_url('period-close/' . $period['module_code']) ?>" class="btn btn-light">Back</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
