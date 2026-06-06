<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Chart of Account</h4>
                <p class="text-muted mb-0">Postable and summary accounts for active company.</p>
            </div>
            <a href="<?= site_url('gl/entries/new') ?>" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> New GL Entry
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account No</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Normal</th>
                        <th>Parent</th>
                        <th>Postable</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td class="fw-semibold"><code><?= esc($account['account_no'] ?? '-') ?></code></td>
                        <td><?= esc($account['account_name'] ?? '-') ?></td>
                        <td><?= esc($account['account_type'] ?? '-') ?></td>
                        <td><?= esc($account['normal_balance'] ?? '-') ?></td>
                        <td><?= esc($account['parent_account_no'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= (int) ($account['is_postable'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($account['is_postable'] ?? 0) === 1 ? 'Yes' : 'No' ?></span></td>
                        <td><span class="badge bg-<?= (int) ($account['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($account['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($accounts === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No chart of account found. Run GL seeder first.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
