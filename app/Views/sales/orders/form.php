<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('sales/orders') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1">Create Sales Order</h4>
                    <p class="text-muted mb-0">Manual SO entry for the active company/site.</p>
                </div>
                <a href="<?= site_url('sales/orders') ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">SO No</label>
                    <input type="text" name="so_no" class="form-control" required value="<?= esc(old('so_no', 'SO-' . date('Ymd-His'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">SO Date</label>
                    <input type="date" name="so_date" class="form-control" required value="<?= esc(old('so_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" id="customerSelect">
                        <option value="">Manual / No Customer Master</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= (int) $customer['id'] ?>" data-name="<?= esc($customer['name']) ?>">
                                <?= esc($customer['code'] . ' - ' . $customer['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency_code" class="form-control" value="<?= esc(old('currency_code', 'IDR')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="customer_name" id="customerName" class="form-control" value="<?= esc(old('customer_name')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title mb-0">Line Items</h4>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                    <i class="bx bx-plus me-1"></i> Add Line
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-nowrap align-middle" id="soLinesTable">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:140px;">Item Code</th>
                            <th style="min-width:220px;">Item Name</th>
                            <th style="width:110px;">Qty</th>
                            <th style="width:100px;">UoM</th>
                            <th style="width:140px;">Unit Price</th>
                            <th style="width:140px;">Discount</th>
                            <th style="width:140px;">Tax</th>
                            <th style="width:140px;" class="text-end">Line Total</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <tr>
                                <td><input type="text" name="item_code[]" class="form-control item-code"></td>
                                <td><input type="text" name="item_name[]" class="form-control item-name"></td>
                                <td><input type="number" step="0.0001" name="qty[]" class="form-control calc text-end" value="<?= $i === 0 ? '1' : '' ?>"></td>
                                <td><input type="text" name="uom_code[]" class="form-control" value="PCS"></td>
                                <td><input type="number" step="0.01" name="unit_price[]" class="form-control calc text-end" value="0"></td>
                                <td><input type="number" step="0.01" name="discount_amount[]" class="form-control calc text-end" value="0"></td>
                                <td><input type="number" step="0.01" name="tax_amount[]" class="form-control calc text-end" value="0"></td>
                                <td class="text-end fw-semibold line-total">0.00</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bx bx-trash"></i></button></td>
                            </tr>
                        <?php endfor ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><th colspan="7" class="text-end">Subtotal</th><th class="text-end" id="subtotalText">0.00</th><th></th></tr>
                        <tr><th colspan="7" class="text-end">Discount</th><th class="text-end" id="discountText">0.00</th><th></th></tr>
                        <tr><th colspan="7" class="text-end">Tax</th><th class="text-end" id="taxText">0.00</th><th></th></tr>
                        <tr><th colspan="7" class="text-end">Total</th><th class="text-end" id="totalText">0.00</th><th></th></tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i> Save SO</button>
                <a href="<?= site_url('sales/orders') ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('soLinesTable');
    const tbody = table.querySelector('tbody');
    const customerSelect = document.getElementById('customerSelect');
    const customerName = document.getElementById('customerName');

    function number(value) {
        const parsed = parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function money(value) {
        return number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function recalc() {
        let subtotal = 0, discount = 0, tax = 0;
        tbody.querySelectorAll('tr').forEach(function (row) {
            const qty = number(row.querySelector('[name="qty[]"]').value);
            const price = number(row.querySelector('[name="unit_price[]"]').value);
            const disc = number(row.querySelector('[name="discount_amount[]"]').value);
            const tx = number(row.querySelector('[name="tax_amount[]"]').value);
            const lineSubtotal = qty * price;
            const lineTotal = lineSubtotal - disc + tx;
            subtotal += lineSubtotal;
            discount += disc;
            tax += tx;
            row.querySelector('.line-total').textContent = money(lineTotal);
        });
        document.getElementById('subtotalText').textContent = money(subtotal);
        document.getElementById('discountText').textContent = money(discount);
        document.getElementById('taxText').textContent = money(tax);
        document.getElementById('totalText').textContent = money(subtotal - discount + tax);
    }

    function bindRow(row) {
        row.querySelectorAll('.calc').forEach(input => input.addEventListener('input', recalc));
        row.querySelector('.remove-line').addEventListener('click', function () {
            if (tbody.querySelectorAll('tr').length > 1) {
                row.remove();
                recalc();
            }
        });
    }

    document.getElementById('addLineBtn').addEventListener('click', function () {
        const clone = tbody.querySelector('tr').cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) {
            input.value = input.name === 'uom_code[]' ? 'PCS' : (input.classList.contains('calc') ? '0' : '');
        });
        tbody.appendChild(clone);
        bindRow(clone);
        recalc();
    });

    customerSelect.addEventListener('change', function () {
        const option = customerSelect.options[customerSelect.selectedIndex];
        if (option && option.dataset.name) {
            customerName.value = option.dataset.name;
        }
    });

    tbody.querySelectorAll('tr').forEach(bindRow);
    recalc();
});
</script>
<?= $this->endSection() ?>
