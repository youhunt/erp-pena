<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="statement_line_id" value="<?= esc(old('statement_line_id', $defaults['statement_line_id'] ?? '')) ?>">
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                    <p class="text-muted mb-0">Post <?= esc($type) ?> in/out and optionally create a balanced GL journal.</p>
                </div>
                <a href="<?= site_url('cash-bank/' . ($type === 'cash' ? 'cash-entries' : 'bank-entries')) ?>" class="btn btn-light">Back</a>
            </div>

            <?php if (! empty($defaults['statement_line_id'])): ?>
                <div class="alert alert-info">
                    This bank entry is prepared from bank statement line #<?= esc($defaults['statement_line_id']) ?>. Bank, date, direction, and amount must stay aligned with the statement line.
                </div>
            <?php endif ?>

            <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">Entry No</label><input type="text" name="entry_no" class="form-control" required value="<?= esc(old('entry_no', $defaults['entry_no'] ?? (strtoupper($type) . '-' . date('Ymd-His')))) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Entry Date</label><input type="date" name="entry_date" class="form-control" required value="<?= esc(old('entry_date', $defaults['entry_date'] ?? date('Y-m-d'))) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Direction</label><select name="direction" class="form-select" required><?php $direction = old('direction', $defaults['direction'] ?? 'in'); ?><option value="in" <?= $direction === 'in' ? 'selected' : '' ?>><?= $type === 'cash' ? 'Cash In' : 'Bank In' ?></option><option value="out" <?= $direction === 'out' ? 'selected' : '' ?>><?= $type === 'cash' ? 'Cash Out' : 'Bank Out' ?></option></select></div>
                <div class="col-md-3 mb-3"><label class="form-label">Currency</label><input type="text" name="currency_code" id="entryCurrency" class="form-control" readonly value="<?= esc(old('currency_code', $defaults['currency_code'] ?? 'IDR')) ?>"><small class="text-muted">Auto from selected Cash/Bank Account.</small></div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cash/Bank Account</label>
                    <select name="cash_bank_code" id="cashBankCode" class="form-select select2" required>
                        <option value="">Select Account</option>
                        <?php $cashBankCode = old('cash_bank_code', $defaults['cash_bank_code'] ?? ''); ?>
                        <?php foreach ($accounts as $account): ?>
                            <?php $code = (string) ($account['cash_bank_code'] ?? ''); $currency = (string) ($account['currency_code'] ?? 'IDR'); $balance = (float) ($account['current_balance'] ?? 0); ?>
                            <option value="<?= esc($code, 'attr') ?>" data-currency="<?= esc($currency, 'attr') ?>" data-balance="<?= esc((string) $balance, 'attr') ?>" <?= $cashBankCode === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($account['cash_bank_name'] ?? '-') . ' / ' . $currency . ' / Balance ' . number_format($balance, 2)) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text" id="cashBankBalanceHint">Select account to see currency and balance.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Counter GL Account</label>
                    <select name="counter_account_no" class="form-select select2" required>
                        <option value="">Select GL Account</option>
                        <?php $counterAccountNo = old('counter_account_no', $defaults['counter_account_no'] ?? ''); ?>
                        <?php foreach ($chartAccounts as $account): ?><?php $accountNo = (string) ($account['account_no'] ?? ''); ?><option value="<?= esc($accountNo, 'attr') ?>" <?= $counterAccountNo === $accountNo ? 'selected' : '' ?>><?= esc($accountNo . ' - ' . ($account['account_name'] ?? '')) ?></option><?php endforeach ?>
                    </select>
                    <div class="form-text">Required untuk membuat journal otomatis ke GL.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" id="entryAmount" class="form-control text-end" required value="<?= esc(old('amount', $defaults['amount'] ?? '0')) ?>"></div>
                <div class="col-md-2 mb-3"><label class="form-label">Rate Type</label><input type="text" name="rate_type" maxlength="12" class="form-control" value="<?= esc(old('rate_type', $defaults['rate_type'] ?? 'BI')) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Exchange Rate</label><input type="number" step="0.000000000001" name="exchange_rate" id="exchangeRate" class="form-control text-end" value="<?= esc(old('exchange_rate', $defaults['exchange_rate'] ?? '')) ?>"><small class="text-muted">Kosongkan untuk ambil dari Rate Master.</small></div>
                <div class="col-md-4 mb-3"><label class="form-label">Base Amount Preview</label><input type="text" id="baseAmountPreview" class="form-control text-end" readonly value="0.00"><small class="text-muted">Preview saja, nilai final dihitung server.</small></div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Reference No</label><input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no', $defaults['reference_no'] ?? '')) ?>"></div>
                <div class="col-md-8 mb-3"><label class="form-label">Description</label><input type="text" name="description" class="form-control" value="<?= esc(old('description', $defaults['description'] ?? '')) ?>"></div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Post this cash/bank entry?')"><i class="bx bx-save me-1"></i> Post Entry</button>
                <a href="<?= site_url('cash-bank/accounts') ?>" class="btn btn-light">Cash Bank ID</a>
                <a href="<?= site_url('cash-bank/rates') ?>" class="btn btn-light">Rate Master</a>
            </div>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const accountSelect = document.getElementById('cashBankCode');
    const currencyInput = document.getElementById('entryCurrency');
    const balanceHint = document.getElementById('cashBankBalanceHint');
    const amountInput = document.getElementById('entryAmount');
    const rateInput = document.getElementById('exchangeRate');
    const basePreview = document.getElementById('baseAmountPreview');

    function formatNumber(value) {
        const parsed = parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00';
    }
    function syncBasePreview() {
        const amount = parseFloat(amountInput ? amountInput.value : '0') || 0;
        const rate = parseFloat(rateInput ? rateInput.value : '0') || 1;
        if (basePreview) basePreview.value = formatNumber(amount * rate);
    }
    function syncAccountCurrency() {
        if (!accountSelect) return;
        const option = accountSelect.options[accountSelect.selectedIndex];
        const currency = option ? (option.getAttribute('data-currency') || 'IDR') : 'IDR';
        const balance = option ? (option.getAttribute('data-balance') || '0') : '0';
        if (currencyInput) currencyInput.value = currency;
        if (balanceHint) balanceHint.textContent = option && option.value ? 'Currency: ' + currency + ' | Current Balance: ' + formatNumber(balance) : 'Select account to see currency and balance.';
        syncBasePreview();
    }
    if (accountSelect) accountSelect.addEventListener('change', syncAccountCurrency);
    if (amountInput) amountInput.addEventListener('input', syncBasePreview);
    if (rateInput) rateInput.addEventListener('input', syncBasePreview);
    if (window.jQuery && jQuery.fn.select2) { jQuery('.select2').select2({ width: '100%' }); jQuery('#cashBankCode').on('select2:select change', syncAccountCurrency); }
    syncAccountCurrency();
});
</script>
<?= $this->endSection() ?>
