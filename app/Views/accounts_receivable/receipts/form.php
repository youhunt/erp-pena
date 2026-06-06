<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('ar/sales-invoices/' . $receivable['sales_invoice_id'] . '/receipt') ?>">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-1">A/R Receivable</h4>
                    <p class="text-muted mb-3"><?= esc($receivable['invoice_no']) ?> - <?= esc($receivable['customer_name'] ?? '-') ?></p>
                    <table class="table table-sm mb-0">
                        <tr><th>Invoice Amount</th><td class="text-end"><?= esc(number_format((float) $receivable['invoice_amount'], 2)) ?></td></tr>
                        <tr><th>Received</th><td class="text-end"><?= esc(number_format((float) $receivable['paid_amount'], 2)) ?></td></tr>
                        <tr><th>Outstanding</th><td class="text-end fw-semibold"><?= esc(number_format((float) $receivable['outstanding_amount'], 2)) ?></td></tr>
                        <tr><th>Status</th><td><?= esc($receivable['status'] ?? '-') ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h4 class="card-title mb-1">Post Customer Receipt</h4>
                            <p class="text-muted mb-0">This will reduce A/R outstanding, update cash/bank balance, and post GL when account setup is available.</p>
                        </div>
                        <a href="<?= site_url('ar/sales-invoices/' . $receivable['sales_invoice_id']) ?>" class="btn btn-light">Back</a>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Receipt No</label>
                            <input type="text" name="receipt_no" class="form-control" required value="<?= esc(old('receipt_no', 'ARR-' . date('Ymd-His'))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Receipt Date</label>
                            <input type="date" name="receipt_date" class="form-control" required value="<?= esc(old('receipt_date', date('Y-m-d'))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Receipt Amount</label>
                            <input type="number" step="0.01" min="0.01" max="<?= esc($receivable['outstanding_amount']) ?>" name="receipt_amount" class="form-control" required value="<?= esc(old('receipt_amount', number_format((float) $receivable['outstanding_amount'], 2, '.', ''))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Method</label>
                            <select name="receipt_method" class="form-select" required>
                                <?php foreach (['bank' => 'Bank', 'cash' => 'Cash', 'transfer' => 'Transfer', 'giro' => 'Giro'] as $value => $label): ?>
                                    <option value="<?= esc($value) ?>" <?= old('receipt_method', 'bank') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cash/Bank Code</label>
                            <select name="cash_bank_code" class="form-select" required>
                                <option value="">Choose cash/bank</option>
                                <?php foreach ($cashBankAccounts ?? [] as $account): ?>
                                    <option value="<?= esc($account['cash_bank_code']) ?>" <?= old('cash_bank_code', 'BANK-IDR') === $account['cash_bank_code'] ? 'selected' : '' ?>>
                                        <?= esc($account['cash_bank_code'] . ' - ' . $account['cash_bank_name'] . ' (' . number_format((float) $account['current_balance'], 2) . ')') ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference No</label>
                            <input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no')) ?>" placeholder="Transfer/ref no">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Post this A/R receipt?')"><i class="bx bx-money-withdraw me-1"></i> Post Receipt</button>
                        <a href="<?= site_url('ar/sales-invoices/' . $receivable['sales_invoice_id']) ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
