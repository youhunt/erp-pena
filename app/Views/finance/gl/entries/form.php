<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('gl/entries') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1">Create GL Entry</h4>
                    <p class="text-muted mb-0">Manual posted journal. Debit and credit must balance.</p>
                </div>
                <a href="<?= site_url('gl/entries') ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Journal No</label>
                    <input type="text" name="journal_no" class="form-control" required value="<?= esc(old('journal_no', 'JE-' . date('Ymd-His'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Journal Date</label>
                    <input type="date" name="journal_date" class="form-control" required value="<?= esc(old('journal_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">GL Book</label>
                    <select name="gl_book_id" class="form-select">
                        <option value="">Default</option>
                        <?php foreach ($books as $book): ?>
                            <option value="<?= (int) $book['id'] ?>"><?= esc(($book['book_code'] ?? '-') . ' - ' . ($book['book_name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency_code" class="form-control" value="<?= esc(old('currency_code', 'IDR')) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" value="<?= esc(old('description')) ?>">
            </div>

            <div class="table-responsive border rounded">
                <table class="table table-sm align-middle mb-0" id="glLinesTable">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:240px;">Account</th>
                            <th>Description</th>
                            <th class="text-end" style="width:160px;">Debit</th>
                            <th class="text-end" style="width:160px;">Credit</th>
                            <th style="width:48px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <tr>
                            <td>
                                <select name="account_no[]" class="form-select form-select-sm">
                                    <option value="">Select Account</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= esc($account['account_no']) ?>"><?= esc($account['account_no'] . ' - ' . $account['account_name']) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </td>
                            <td><input type="text" name="line_description[]" class="form-control form-control-sm"></td>
                            <td><input type="number" step="0.01" name="debit[]" class="form-control form-control-sm text-end gl-debit" value="<?= $i === 0 ? '1000' : '0' ?>"></td>
                            <td><input type="number" step="0.01" name="credit[]" class="form-control form-control-sm text-end gl-credit" value="<?= $i === 1 ? '1000' : '0' ?>"></td>
                            <td><button type="button" class="btn btn-sm btn-light gl-remove-line"><i class="bx bx-trash"></i></button></td>
                        </tr>
                    <?php endfor ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-end">Total</th>
                            <th class="text-end" id="totalDebit">0.00</th>
                            <th class="text-end" id="totalCredit">0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                <button type="button" class="btn btn-outline-primary" id="addGlLine">
                    <i class="bx bx-plus me-1"></i> Add Line
                </button>
                <div class="d-flex gap-2">
                    <span class="badge bg-light text-dark align-self-center" id="balanceStatus">Checking balance</span>
                    <button class="btn btn-primary" type="submit" onclick="return confirm('Post this GL journal?')">
                        <i class="bx bx-save me-1"></i> Post Journal
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('glLinesTable');
    const addButton = document.getElementById('addGlLine');
    const totalDebit = document.getElementById('totalDebit');
    const totalCredit = document.getElementById('totalCredit');
    const balanceStatus = document.getElementById('balanceStatus');

    function recalc() {
        let debit = 0;
        let credit = 0;
        table.querySelectorAll('.gl-debit').forEach(input => debit += parseFloat(input.value || '0'));
        table.querySelectorAll('.gl-credit').forEach(input => credit += parseFloat(input.value || '0'));
        totalDebit.textContent = debit.toFixed(2);
        totalCredit.textContent = credit.toFixed(2);
        const balanced = Math.abs(debit - credit) < 0.01 && debit > 0;
        balanceStatus.className = 'badge align-self-center ' + (balanced ? 'bg-success' : 'bg-danger');
        balanceStatus.textContent = balanced ? 'Balanced' : 'Not Balanced';
    }

    function bindRow(row) {
        row.querySelectorAll('input').forEach(input => input.addEventListener('input', recalc));
        row.querySelector('.gl-remove-line').addEventListener('click', function () {
            if (table.querySelectorAll('tbody tr').length > 2) {
                row.remove();
                recalc();
            }
        });
    }

    table.querySelectorAll('tbody tr').forEach(bindRow);
    addButton.addEventListener('click', function () {
        const row = table.querySelector('tbody tr').cloneNode(true);
        row.querySelectorAll('input').forEach(input => input.value = input.name.includes('debit') || input.name.includes('credit') ? '0' : '');
        row.querySelector('select').value = '';
        table.querySelector('tbody').appendChild(row);
        bindRow(row);
        recalc();
    });
    recalc();
});
</script>
<?= $this->endSection() ?>
