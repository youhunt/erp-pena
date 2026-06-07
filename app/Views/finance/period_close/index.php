<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($module ? ($modules[$module] ?? $module) . ' Period Close' : 'Period Close') ?></h4>
                <p class="text-muted mb-0">Closed periods prevent new postings in the selected module.</p>
            </div>
            <a href="<?= site_url('period-close/new' . ($module ? '/' . $module : '')) ?>" class="btn btn-primary">
                <i class="bx bx-lock me-1"></i> Close Period
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Module</th>
                        <th>Period</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Closed At</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($periods as $row): ?>
                    <tr>
                        <td><?= esc($modules[$row['module_code']] ?? $row['module_code']) ?></td>
                        <td class="fw-semibold"><?= esc($row['period'] ?? '-') ?></td>
                        <td><?= esc($row['period_start'] ?? '-') ?></td>
                        <td><?= esc($row['period_end'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= ($row['status'] ?? '') === 'closed' ? 'danger' : 'success' ?>"><?= esc($row['status'] ?? '-') ?></span></td>
                        <td><?= esc($row['closed_at'] ?? '-') ?></td>
                        <td class="text-end"><a href="<?= site_url('period-close/' . $row['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i></a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($periods === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No period close records yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
