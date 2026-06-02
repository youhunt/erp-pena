<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Audit Summary</h4>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>ID</th><td><?= esc($log['id'] ?? '-') ?></td></tr>
                        <tr><th>Date</th><td><?= esc($log['created_at'] ?? '-') ?></td></tr>
                        <tr><th>Module</th><td><?= esc($log['module'] ?? '-') ?></td></tr>
                        <tr><th>Action</th><td><?= esc($log['action'] ?? '-') ?></td></tr>
                        <tr><th>Table</th><td><?= esc($log['table_name'] ?? '-') ?></td></tr>
                        <tr><th>Record ID</th><td><?= esc($log['record_id'] ?? '-') ?></td></tr>
                        <tr><th>Record Code</th><td><?= esc($log['record_code'] ?? '-') ?></td></tr>
                        <tr><th>User ID</th><td><?= esc($log['user_id'] ?? '-') ?></td></tr>
                        <tr><th>Company</th><td><?= esc($log['company_id'] ?? '-') ?></td></tr>
                        <tr><th>Site</th><td><?= esc($log['site_id'] ?? '-') ?></td></tr>
                        <tr><th>IP</th><td><?= esc($log['ip_address'] ?? '-') ?></td></tr>
                    </tbody>
                </table>

                <div class="mt-3">
                    <a href="<?= site_url('audit-logs') ?>" class="btn btn-light">
                        <i class="bx bx-arrow-back me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Description</h4>
                <p class="text-muted mb-0"><?= esc($log['description'] ?? '-') ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Old Values</h4>
                <?php if ($oldValues === null): ?>
                    <p class="text-muted mb-0">No old values.</p>
                <?php else: ?>
                    <pre class="bg-light border rounded p-3 mb-0" style="white-space: pre-wrap;"><code><?= esc(json_encode($oldValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                <?php endif ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">New Values</h4>
                <?php if ($newValues === null): ?>
                    <p class="text-muted mb-0">No new values.</p>
                <?php else: ?>
                    <pre class="bg-light border rounded p-3 mb-0" style="white-space: pre-wrap;"><code><?= esc(json_encode($newValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                <?php endif ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">User Agent</h4>
                <p class="text-muted mb-0"><?= esc($log['user_agent'] ?? '-') ?></p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
