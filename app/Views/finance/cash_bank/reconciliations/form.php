<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="get" action="<?= site_url('cash-bank/reconciliations/new') ?>" class="card">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Bank Account</label>
                <select name="cash_bank_code" class="form-select" required>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= esc($account['cash_bank_code']) ?>" <?= $selectedCode === $account['cash_bank_code'] ? 'selected' : '' ?>>
                            <?= esc($account['cash_bank_code'] . ' - ' . $account['cash_bank_name'] . ' (' . number_format((float) $account['current_balance'], 2) . ')') ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-light w-100">Load Entries</button>
            </div>
        </div>
    </div>
</form>

<form method="post" action="<?= site_url('cash-bank/reconciliations') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="cash_bank_code" value="<?= esc($selectedCode) ?>">

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1">Create Bank Reconcile</h4>
                    <p class="text-muted mb-0">Match posted bank entries with the bank statement.</p>
                </div>
                <a href="<?= site_url('cash-bank/reconciliations') ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Reconcile No</label>
                    <input type="text" name="reconcile_no" class="form-control" required value="<?= esc(old('reconcile_no', 'BR-' . date('Ymd-His'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statement Date</label>
                    <input type="date" name="statement_date" class="form-control" required value="<?= esc(old('statement_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statement Balance</label>
                    <input type="number" name="statement_balance" class="form-control text-end" required step="0.01" value="<?= esc(old('statement_balance', '0.00')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statement Ref</label>
                    <input type="text" name="statement_ref" class="form-control" value="<?= esc(old('statement_ref')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-3">Unreconciled Bank Entries</h4>
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0" id="entryTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 48px;"><input type="checkbox" class="form-check-input" id="checkAll"></th>
                            <th>Date</th>
                            <th>Entry No</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Signed</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <?php $signed = str_ends_with((string) ($entry['entry_type'] ?? ''), '_in') ? (float) $entry['amount'] : -(float) $entry['amount']; ?>
                        <tr>
                            <td><input type="checkbox" class="form-check-input entry-check" name="entry_ids[]" value="<?= esc($entry['id']) ?>" data-signed="<?= esc($signed) ?>"></td>
                            <td><?= esc($entry['entry_date'] ?? '-') ?></td>
                            <td class="fw-semibold"><?= esc($entry['entry_no'] ?? '-') ?></td>
                            <td><span class="badge bg-<?= $signed >= 0 ? 'success' : 'danger' ?>"><?= esc($entry['entry_type'] ?? '-') ?></span></td>
                            <td><?= esc($entry['reference_no'] ?? '-') ?></td>
                            <td><?= esc($entry['description'] ?? '-') ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($entry['amount'] ?? 0), 2)) ?></td>
                            <td class="text-end fw-semibold"><?= esc(number_format($signed, 2)) ?></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($entries === []): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No unreconciled bank entry found for this account.</td></tr>
                    <?php endif ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><th colspan="7" class="text-end">Selected Signed Amount</th><th class="text-end" id="selectedAmount">0.00</th></tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Post this bank reconciliation?')"><i class="bx bx-check me-1"></i> Post Reconcile</button>
                <a href="<?= site_url('cash-bank/reconciliations') ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>

<script>
(() => {
    const checks = [...document.querySelectorAll('.entry-check')];
    const selectedAmount = document.getElementById('selectedAmount');
    const checkAll = document.getElementById('checkAll');

    function recalc() {
        const total = checks.filter((check) => check.checked).reduce((sum, check) => sum + parseFloat(check.dataset.signed || '0'), 0);
        selectedAmount.textContent = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    checks.forEach((check) => check.addEventListener('change', recalc));
    checkAll?.addEventListener('change', () => {
        checks.forEach((check) => check.checked = checkAll.checked);
        recalc();
    });
})();
</script>
<?= $this->endSection() ?>
