<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('ar/manual-invoices') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1">Manual A/R Invoice</h4>
                    <p class="text-muted mb-0">Post customer invoice directly into A/R receivable and GL.</p>
                </div>
                <a href="<?= site_url('ar/sales-invoices') ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Invoice No</label>
                    <input type="text" name="invoice_no" class="form-control" required value="<?= esc(old('invoice_no', 'MSI-' . date('Ymd-His'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Invoice Date</label>
                    <input type="date" name="invoice_date" class="form-control" required value="<?= esc(old('invoice_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc(old('due_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency_code" class="form-control" maxlength="10" value="<?= esc(old('currency_code', 'IDR')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Choose customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <?php $customerLabel = ($customer['code'] ?? $customer['customer'] ?? '-') . ' - ' . ($customer['name'] ?? $customer['customern'] ?? '-'); ?>
                            <option value="<?= esc($customer['id']) ?>" <?= old('customer_id') == $customer['id'] ? 'selected' : '' ?>><?= esc($customerLabel) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h4 class="card-title mb-0">Invoice Lines</h4>
                <button type="button" class="btn btn-outline-primary btn-sm" id="addLine"><i class="bx bx-plus me-1"></i> Add Line</button>
            </div>
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0" id="lineTable">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 220px;">Item</th>
                            <th style="min-width: 220px;">Description</th>
                            <th class="text-end" style="width: 110px;">Qty</th>
                            <th style="width: 90px;">UoM</th>
                            <th class="text-end" style="width: 140px;">Price</th>
                            <th class="text-end" style="width: 130px;">Discount</th>
                            <th class="text-end" style="width: 130px;">Tax</th>
                            <th class="text-end" style="width: 140px;">Total</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot class="table-light">
                        <tr><th colspan="7" class="text-end">Total</th><th class="text-end" id="grandTotal">0.00</th><th></th></tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Post this manual A/R invoice?')"><i class="bx bx-receipt me-1"></i> Post Invoice</button>
                <a href="<?= site_url('ar/sales-invoices') ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>

<template id="lineTemplate">
    <tr>
        <td>
            <select class="form-select form-select-sm item-select" name="line_item_id[]">
                <option value="">Manual item</option>
                <?php foreach ($items as $item): ?>
                    <?php $itemCode = $item['code'] ?? $item['item_code'] ?? ''; $itemName = $item['name'] ?? $item['item_name'] ?? ''; ?>
                    <option value="<?= esc($item['id']) ?>" data-code="<?= esc($itemCode) ?>" data-name="<?= esc($itemName) ?>" data-uom="<?= esc($item['sellinguom'] ?? $item['stockuom'] ?? 'PCS') ?>" data-price="<?= esc($item['sellingprice'] ?? $item['item_price'] ?? 0) ?>">
                        <?= esc($itemCode . ' - ' . $itemName) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <input type="hidden" name="line_item_code[]" class="line-code">
        </td>
        <td><input type="text" name="line_item_name[]" class="form-control form-control-sm line-name" required></td>
        <td><input type="number" name="line_qty[]" class="form-control form-control-sm text-end line-calc line-qty" min="0.0001" step="0.0001" value="1"></td>
        <td><input type="text" name="line_uom_code[]" class="form-control form-control-sm line-uom" value="PCS"></td>
        <td><input type="number" name="line_unit_amount[]" class="form-control form-control-sm text-end line-calc line-price" min="0" step="0.01" value="0"></td>
        <td><input type="number" name="line_discount_amount[]" class="form-control form-control-sm text-end line-calc line-discount" min="0" step="0.01" value="0"></td>
        <td><input type="number" name="line_tax_amount[]" class="form-control form-control-sm text-end line-calc line-tax" min="0" step="0.01" value="0"></td>
        <td class="text-end fw-semibold line-total">0.00</td>
        <td><button type="button" class="btn btn-light btn-sm remove-line"><i class="bx bx-trash"></i></button></td>
    </tr>
</template>

<script>
(() => {
    const tbody = document.querySelector('#lineTable tbody');
    const template = document.getElementById('lineTemplate');
    const grandTotal = document.getElementById('grandTotal');

    function recalc() {
        let total = 0;
        tbody.querySelectorAll('tr').forEach((row) => {
            const qty = parseFloat(row.querySelector('.line-qty').value || '0');
            const price = parseFloat(row.querySelector('.line-price').value || '0');
            const discount = parseFloat(row.querySelector('.line-discount').value || '0');
            const tax = parseFloat(row.querySelector('.line-tax').value || '0');
            const lineTotal = Math.max(0, (qty * price) - discount + tax);
            total += lineTotal;
            row.querySelector('.line-total').textContent = lineTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        });
        grandTotal.textContent = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function addLine() {
        tbody.appendChild(template.content.cloneNode(true));
        recalc();
    }

    document.getElementById('addLine').addEventListener('click', addLine);
    tbody.addEventListener('input', (event) => {
        if (event.target.classList.contains('line-calc')) recalc();
    });
    tbody.addEventListener('change', (event) => {
        if (! event.target.classList.contains('item-select')) return;
        const option = event.target.selectedOptions[0];
        const row = event.target.closest('tr');
        row.querySelector('.line-code').value = option.dataset.code || '';
        row.querySelector('.line-name').value = option.dataset.name || row.querySelector('.line-name').value;
        row.querySelector('.line-uom').value = option.dataset.uom || 'PCS';
        row.querySelector('.line-price').value = option.dataset.price || '0';
        recalc();
    });
    tbody.addEventListener('click', (event) => {
        if (! event.target.closest('.remove-line')) return;
        if (tbody.querySelectorAll('tr').length > 1) event.target.closest('tr').remove();
        recalc();
    });

    addLine();
})();
</script>
<?= $this->endSection() ?>
