<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Cash Bank ID</h4>
                <p class="text-muted mb-0">Cash and bank account master with running balance.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('cash-bank/cash-entries/new') ?>" class="btn btn-outline-primary">Cash Entry</a>
                <a href="<?= site_url('cash-bank/bank-entries/new') ?>" class="btn btn-primary">Bank Entry</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Currency</th>
                        <th>GL Account</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($account['cash_bank_code'] ?? '-') ?></td>
                        <td><?= esc($account['cash_bank_name'] ?? '-') ?></td>
                        <td><?= esc($account['account_type'] ?? '-') ?></td>
                        <td><?= esc($account['currency_code'] ?? '-') ?></td>
                        <td><code><?= esc($account['gl_account_no'] ?? '-') ?></code></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($account['current_balance'] ?? 0), 2)) ?></td>
                        <td><span class="badge bg-<?= (int) ($account['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($account['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($accounts === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No cash/bank account found. Run CashBankSeeder first.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
