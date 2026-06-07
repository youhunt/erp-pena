<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('gl/posting-profiles') ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1">Posting Profile</h4>
                    <p class="text-muted mb-0">Default GL accounts used by automatic ERP postings.</p>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i> Save</button>
            </div>

            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Module</th>
                            <th>Posting Key</th>
                            <th>Description</th>
                            <th style="min-width: 260px;">Account</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($profiles as $profile): ?>
                        <tr>
                            <td><span class="badge bg-info"><?= esc(strtoupper($profile['module_code'])) ?></span></td>
                            <td><code><?= esc($profile['posting_key']) ?></code></td>
                            <td><?= esc($profile['description'] ?? '-') ?></td>
                            <td>
                                <select name="account_no[<?= esc($profile['id']) ?>]" class="form-select form-select-sm">
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= esc($account['account_no']) ?>" <?= ($profile['account_no'] ?? '') === $account['account_no'] ? 'selected' : '' ?>>
                                            <?= esc($account['account_no'] . ' - ' . $account['account_name']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </td>
                            <td><span class="badge bg-<?= (int) ($profile['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($profile['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($profiles === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No posting profile found. Run GL posting profile seeder first.</td></tr>
                    <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
