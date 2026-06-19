<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Import Bank Statement</h4>
                <p class="text-muted mb-0">Upload rekening koran Excel as bank-side evidence. This does not post Cash/Bank Entry or GL.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('cash-bank/statements/template') ?>" class="btn btn-outline-secondary">Download Template</a>
                <a href="<?= site_url('cash-bank/statements') ?>" class="btn btn-light">Back</a>
            </div>
        </div>

        <div class="alert alert-info">
            Recommended headers: <code>statement_date</code>, <code>value_date</code>, <code>reference_no</code>, <code>description</code>, <code>debit</code>, <code>credit</code>, <code>balance</code>, <code>currency</code>.
        </div>

        <form method="post" action="<?= site_url('cash-bank/statements/import') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Bank Account</label>
                    <select name="cash_bank_code" class="form-select" required>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= esc($account['cash_bank_code']) ?>" <?= old('cash_bank_code') === $account['cash_bank_code'] ? 'selected' : '' ?>>
                                <?= esc($account['cash_bank_code'] . ' - ' . $account['cash_bank_name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statement Date</label>
                    <input type="date" name="statement_date" class="form-control" required value="<?= esc(old('statement_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statement Ref</label>
                    <input type="text" name="statement_ref" class="form-control" value="<?= esc(old('statement_ref')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Closing Balance</label>
                    <input type="number" name="closing_balance" class="form-control text-end" step="0.01" value="<?= esc(old('closing_balance', '0.00')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Opening Balance</label>
                    <input type="number" name="opening_balance" class="form-control text-end" step="0.01" value="<?= esc(old('opening_balance', '0.00')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Excel File (.xlsx)</label>
                    <input type="file" name="statement_file" class="form-control" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="bx bx-upload me-1"></i> Import Statement</button>
                <a href="<?= site_url('cash-bank/statements') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
