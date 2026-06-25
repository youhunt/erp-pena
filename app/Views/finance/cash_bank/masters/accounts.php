<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">Cash Bank ID</h4>
                <p class="text-muted mb-0">Bank master: branch, code, currency, account, PIC, phone, and address.</p>
            </div>
            <a href="<?= site_url('cash-bank/bank-entries/new') ?>" class="btn btn-primary">Bank Entry</a>
        </div>
        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <form method="get" action="<?= site_url('cash-bank/accounts') ?>" class="row g-3 border rounded bg-light p-3 mb-4">
            <input type="hidden" name="action" value="save">
            <div class="col-md-2"><label class="form-label">Bank Branch</label><input type="text" name="bank_branch" maxlength="50" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Bank Code</label><input type="text" name="bank_code" maxlength="50" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Bank Curr</label><select name="currency_code" class="form-select select2" required><?php foreach ($currencies as $c): ?><option value="<?= esc($c['code'], 'attr') ?>"><?= esc($c['code'] . ' - ' . ($c['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3"><label class="form-label">Bank Name</label><input type="text" name="bank_name" maxlength="500" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Bank Account</label><input type="text" name="bank_account" maxlength="50" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">PIC</label><input type="text" name="pic" maxlength="100" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Phone</label><input type="text" name="phone" maxlength="20" class="form-control"></div>
            <div class="col-md-5"><label class="form-label">Address</label><input type="text" name="address" maxlength="100" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Save</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Branch</th><th>Code</th><th>Currency</th><th>Name</th><th>Account</th><th>PIC</th><th>Phone</th><th>Address</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                <tbody>
                <?php if ($accounts === []): ?><tr><td colspan="10" class="text-center text-muted py-4">No cash bank account yet.</td></tr><?php endif ?>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($account['bank_branch'] ?? $account['cash_bank_code'] ?? '-') ?></td>
                        <td><?= esc($account['bank_code'] ?? '-') ?></td>
                        <td><?= esc($account['currency_code'] ?? '-') ?></td>
                        <td><?= esc($account['cash_bank_name'] ?? '-') ?></td>
                        <td><?= esc($account['bank_account'] ?? '-') ?></td>
                        <td><?= esc($account['pic'] ?? '-') ?></td>
                        <td><?= esc($account['phone'] ?? '-') ?></td>
                        <td><?= esc($account['address'] ?? '-') ?></td>
                        <td class="text-end fw-semibold"><?= number_format((float) ($account['current_balance'] ?? 0), 2) ?></td>
                        <td><span class="badge bg-<?= (int) ($account['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($account['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){if(window.jQuery&&jQuery.fn.select2){jQuery('.select2').select2({width:'100%'});}});</script>
<?= $this->endSection() ?>
