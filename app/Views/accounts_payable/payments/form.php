<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('ap/purchase-invoices/' . $payable['purchase_invoice_id'] . '/payment') ?>">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-1">A/P Payable</h4>
                    <p class="text-muted mb-3"><?= esc($payable['invoice_no']) ?> - <?= esc($payable['supplier_name'] ?? '-') ?></p>
                    <table class="table table-sm mb-0">
                        <tr><th>Invoice Amount</th><td class="text-end"><?= esc(number_format((float) $payable['invoice_amount'], 2)) ?></td></tr>
                        <tr><th>Paid</th><td class="text-end"><?= esc(number_format((float) $payable['paid_amount'], 2)) ?></td></tr>
                        <tr><th>Outstanding</th><td class="text-end fw-semibold"><?= esc(number_format((float) $payable['outstanding_amount'], 2)) ?></td></tr>
                        <tr><th>Status</th><td><?= esc($payable['status'] ?? '-') ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h4 class="card-title mb-1">Post Supplier Payment</h4>
                            <p class="text-muted mb-0">This will reduce A/P outstanding. GL posting will be added in finance backbone phase.</p>
                        </div>
                        <a href="<?= site_url('ap/purchase-invoices/' . $payable['purchase_invoice_id']) ?>" class="btn btn-light">Back</a>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Payment No</label>
                            <input type="text" name="payment_no" class="form-control" required value="<?= esc(old('payment_no', 'APP-' . date('Ymd-His'))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" required value="<?= esc(old('payment_date', date('Y-m-d'))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Amount</label>
                            <input type="number" step="0.01" min="0.01" max="<?= esc($payable['outstanding_amount']) ?>" name="payment_amount" class="form-control" required value="<?= esc(old('payment_amount', number_format((float) $payable['outstanding_amount'], 2, '.', ''))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Method</label>
                            <select name="payment_method" class="form-select" required>
                                <?php foreach (['bank' => 'Bank', 'cash' => 'Cash', 'transfer' => 'Transfer', 'giro' => 'Giro'] as $value => $label): ?>
                                    <option value="<?= esc($value) ?>" <?= old('payment_method', 'bank') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cash/Bank Code</label>
                            <input type="text" name="cash_bank_code" class="form-control" value="<?= esc(old('cash_bank_code')) ?>" placeholder="BANK-IDR">
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
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Post this A/P payment?')"><i class="bx bx-money me-1"></i> Post Payment</button>
                        <a href="<?= site_url('ap/purchase-invoices/' . $payable['purchase_invoice_id']) ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
