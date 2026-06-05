<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('purchase/receipts/' . $receipt['id'] . '/invoice') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="card-title mb-1">Create Purchase Invoice</h4>
                    <p class="text-muted mb-0"><?= esc($receipt['receipt_no']) ?> - <?= esc($receipt['supplier_name'] ?? '-') ?></p>
                </div>
                <a href="<?= site_url('purchase/receipts/' . $receipt['id']) ?>" class="btn btn-light">Back to Receipt</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Invoice No</label>
                    <input type="text" name="invoice_no" class="form-control" required value="<?= esc(old('invoice_no', 'PI-' . date('Ymd-His'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Invoice Date</label>
                    <input type="date" name="invoice_date" class="form-control" required value="<?= esc(old('invoice_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc(old('due_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency_code" class="form-control" maxlength="10" value="<?= esc(old('currency_code', 'IDR')) ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-3">Receipt Lines</h4>
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light"><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><th>UoM</th><th class="text-end">Cost</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                    <?php $total = 0.0; ?>
                    <?php foreach ($lines as $line): ?>
                        <?php $subtotal = (float) ($line['qty_received'] ?? 0) * (float) ($line['unit_cost'] ?? 0); $total += $subtotal; ?>
                        <tr>
                            <td><?= esc($line['line_no']) ?></td>
                            <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                            <td class="text-end"><?= esc(number_format((float) $line['qty_received'], 4)) ?></td>
                            <td><?= esc($line['uom_code'] ?? '-') ?></td>
                            <td class="text-end"><?= esc(number_format((float) $line['unit_cost'], 2)) ?></td>
                            <td class="text-end fw-semibold"><?= esc(number_format($subtotal, 2)) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><th colspan="5" class="text-end">Estimated Total</th><th class="text-end"><?= esc(number_format($total, 2)) ?></th></tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Post this purchase invoice and open A/P payable?')"><i class="bx bx-receipt me-1"></i> Post Invoice</button>
                <a href="<?= site_url('purchase/receipts/' . $receipt['id']) ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
