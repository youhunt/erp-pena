<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries')) ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                    <p class="text-muted mb-0">Post <?= esc($type) ?> in/out and optionally create a balanced GL journal.</p>
                </div>
                <a href="<?= site_url('cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries')) ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Entry No</label>
                    <input type="text" name="entry_no" class="form-control" required value="<?= esc(old('entry_no', strtoupper($type) . '-' . date('Ymd-His'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Entry Date</label>
                    <input type="date" name="entry_date" class="form-control" required value="<?= esc(old('entry_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Direction</label>
                    <select name="direction" class="form-select" required>
                        <option value="in"><?= $type === 'cash' ? 'Cash In' : 'Bank In' ?></option>
                        <option value="out"><?= $type === 'cash' ? 'Cash Out' : 'Bank Out' ?></option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency_code" class="form-control" value="<?= esc(old('currency_code', 'IDR')) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cash/Bank Account</label>
                    <select name="cash_bank_code" class="form-select" required>
                        <option value="">Select Account</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= esc($account['cash_bank_code']) ?>"><?= esc($account['cash_bank_code'] . ' - ' . $account['cash_bank_name'] . ' / Balance ' . number_format((float) $account['current_balance'], 2)) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Counter GL Account</label>
                    <select name="counter_account_no" class="form-select">
                        <option value="">No GL Posting</option>
                        <?php foreach ($chartAccounts as $account): ?>
                            <option value="<?= esc($account['account_no']) ?>"><?= esc($account['account_no'] . ' - ' . $account['account_name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text">Isi untuk membuat journal otomatis ke GL.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" name="amount" class="form-control text-end" required value="<?= esc(old('amount', '0')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Reference No</label>
                    <input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" value="<?= esc(old('description')) ?>">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Post this cash/bank entry?')">
                    <i class="bx bx-save me-1"></i> Post Entry
                </button>
                <a href="<?= site_url('cash-bank/accounts') ?>" class="btn btn-light">Cash Bank ID</a>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
