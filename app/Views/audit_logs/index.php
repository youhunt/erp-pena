<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h4 class="card-title mb-1">Audit Logs</h4>
                <p class="text-muted mb-0">Recent system activity across master data and ERP workflows.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>Record</th>
                        <th>Description</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= esc($log['created_at'] ?? '-') ?></td>
                        <td><span class="badge bg-light text-dark"><?= esc($log['module'] ?? '-') ?></span></td>
                        <td><span class="badge bg-info"><?= esc($log['action'] ?? '-') ?></span></td>
                        <td><?= esc($log['table_name'] ?? '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= esc($log['record_code'] ?? $log['record_id'] ?? '-') ?></div>
                            <small class="text-muted">ID: <?= esc($log['record_id'] ?? '-') ?></small>
                        </td>
                        <td><?= esc($log['description'] ?? '-') ?></td>
                        <td><?= esc($log['user_id'] ?? '-') ?></td>
                    </tr>
                <?php endforeach ?>

                <?php if ($logs === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No audit logs yet.</td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
