<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$pick = static function (array $row, array $keys, mixed $default = ''): mixed {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && trim((string) $row[$key]) !== '') {
            return $row[$key];
        }
    }

    return $default;
};
?>
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
                    <input type="text" name="so_no" class="form-control" placeholder="<?= esc(($suggestedSoNo ?? '') !== '' ? $suggestedSoNo : 'Auto if blank', 'attr') ?>" value="<?= esc(old('so_no')) ?>">
                    <small class="text-muted">Kosongkan untuk nomor otomatis.</small>
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
                            <?php
                            $customerId = (int) ($customer['id'] ?? 0);
                            $customerCode = (string) $pick($customer, ['customer_code', 'customer', 'code', 'cust_code']);
                            $customerNameValue = (string) $pick($customer, ['customer_name', 'customern', 'customernm', 'name', 'company_name', 'description']);
                            $customerTerms = (string) $pick($customer, ['terms_code', 'terms', 'payment_terms']);
                            $selectedCustomer = (int) old('customer_id', 0) === $customerId;
                            ?>
                            <option value="<?= $customerId ?>" <?= $selectedCustomer ? 'selected' : '' ?> data-code="<?= esc($customerCode, 'attr') ?>" data-name="<?= esc($customerNameValue, 'attr') ?>" data-terms="<?= esc($customerTerms, 'attr') ?>">
                                <?= esc(trim($customerCode . ' - ' . $customerNameValue, ' -')) ?>
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
                <div class="col-md-3 mb-3">
                    <label class="form-label">Terms</label>
                    <input type="text" name="terms_code" id="termsCode" class="form-control" value="<?= esc(old('terms_code')) ?>">
                </div>
                <div class="col-md-3 mb-3">
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
                            <th style="width:80px;">Line</th>
                            <th style="min-width:220px;">Item Code</th>
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
                                <td><input type="number" name="so_line[]" class="form-control text-end line-number" min="1" step="1" value="<?= esc((string) ($i + 1)) ?>" required></td>
                                <td>
                                    <input type="hidden" name="item_id[]" class="item-id">
                                    <select name="item_code[]" class="form-select item-select">
                                        <option value="">Pilih / cari data</option>
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                            $code = (string) $pick($item, ['item_code', 'item', 'code', 'sku', 'product_code']);
                                            $name = (string) $pick($item, ['item_name', 'itemn', 'itemna', 'name', 'product_name', 'description']);
                                            $uom = (string) $pick($item, ['sellinguom', 'selling_uom', 'stockuom', 'stock_uom', 'uom_code', 'uom'], 'PCS');
                                            $price = (float) $pick($item, ['sellingprice', 'selling_price', 'item_price', 'price', 'sales_price'], 0);
                                            ?>
                                            <option
                                                value="<?= esc($code) ?>"
                                                data-id="<?= (int) ($item['id'] ?? 0) ?>"
                                                data-code="<?= esc($code, 'attr') ?>"
                                                data-name="<?= esc($name, 'attr') ?>"
                                                data-uom="<?= esc($uom, 'attr') ?>"
                                                data-price="<?= esc((string) $price, 'attr') ?>"
                                            >
                                                <?= esc(trim($code . ' - ' . $name, ' -')) ?>
                                            </option>
                                        <?php endforeach ?>
                                    </select>
                                </td>
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
                        <tr><th colspan="8" class="text-end">Subtotal</th><th class="text-end" id="subtotalText">0.00</th><th></th></tr>
                        <tr><th colspan="8" class="text-end">Discount</th><th class="text-end" id="discountText">0.00</th><th></th></tr>
                        <tr><th colspan="8" class="text-end">Tax</th><th class="text-end" id="taxText">0.00</th><th></th></tr>
                        <tr><th colspan="8" class="text-end">Total</th><th class="text-end" id="totalText">0.00</th><th></th></tr>
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
    const termsCode = document.getElementById('termsCode');

    function number(value) {
        const parsed = parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function money(value) {
        return number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function splitLabel(text) {
        text = (text || '').trim();
        const separatorIndex = text.indexOf(' - ');
        return separatorIndex >= 0 ? text.slice(separatorIndex + 3).trim() : text;
    }

    function renderedSelect2Text(select) {
        if (!select) return '';
        const container = select.nextElementSibling;
        const rendered = container ? container.querySelector('.select2-selection__rendered') : null;
        return rendered ? rendered.textContent.trim().replace(/^×\s*/, '') : '';
    }

    function optionName(option, select = null) {
        if (option && option.dataset && option.dataset.name && option.dataset.name.trim() !== '') return option.dataset.name.trim();
        if (option && option.textContent && option.textContent.trim() !== '') return splitLabel(option.textContent);
        return splitLabel(renderedSelect2Text(select));
    }

    function optionTerms(option) {
        return option && option.dataset && option.dataset.terms ? option.dataset.terms.trim() : '';
    }

    function fillCustomerFromSelected(force = false) {
        const option = customerSelect.options[customerSelect.selectedIndex];
        const selectedName = optionName(option, customerSelect);
        const selectedTerms = optionTerms(option);
        if ((force || customerName.value.trim() === '') && selectedName !== '') customerName.value = selectedName;
        if ((force || termsCode.value.trim() === '') && selectedTerms !== '') termsCode.value = selectedTerms;
    }

    function fillItemRow(row, select) {
        const option = select.options[select.selectedIndex];
        row.querySelector('.item-id').value = option && option.dataset ? (option.dataset.id || '') : '';
        row.querySelector('[name="item_name[]"]').value = optionName(option, select) || '';
        row.querySelector('[name="uom_code[]"]').value = option && option.dataset ? (option.dataset.uom || 'PCS') : 'PCS';
        row.querySelector('[name="unit_price[]"]').value = option && option.dataset ? (option.dataset.price || '0') : '0';
        recalc();
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

    function renumberLines() {
        tbody.querySelectorAll('tr').forEach(function (row, index) {
            row.querySelector('.line-number').value = index + 1;
        });
    }

    function bindRow(row) {
        row.querySelectorAll('.calc').forEach(input => input.addEventListener('input', recalc));
        const select = row.querySelector('.item-select');
        select.addEventListener('change', function () { fillItemRow(row, this); });
        row.querySelector('.remove-line').addEventListener('click', function () {
            if (tbody.querySelectorAll('tr').length > 1) {
                row.remove();
                renumberLines();
                recalc();
            }
        });
    }

    document.getElementById('addLineBtn').addEventListener('click', function () {
        const clone = tbody.querySelector('tr').cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) {
            if (input.type === 'hidden') {
                input.value = '';
                return;
            }
            input.value = input.name === 'uom_code[]' ? 'PCS' : (input.classList.contains('calc') ? '0' : '');
        });
        clone.querySelectorAll('select').forEach(function (select) {
            select.selectedIndex = 0;
        });
        tbody.appendChild(clone);
        renumberLines();
        bindRow(clone);
        recalc();
    });

    customerSelect.addEventListener('change', function () { fillCustomerFromSelected(true); });
    tbody.querySelectorAll('tr').forEach(bindRow);

    if (window.jQuery) {
        window.jQuery(document).on('change select2:select', '#customerSelect', function () {
            setTimeout(function () { fillCustomerFromSelected(true); }, 0);
        });
        window.jQuery(document).on('change select2:select', '.item-select', function () {
            const row = this.closest('tr');
            if (row) setTimeout(() => fillItemRow(row, this), 0);
        });
    }

    fillCustomerFromSelected(false);
    recalc();
});
</script>
<?= $this->endSection() ?>
