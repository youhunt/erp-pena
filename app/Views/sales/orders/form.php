<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$order ??= [];
$lines ??= [];
$isEdit = (bool) ($isEdit ?? false);
$action ??= $isEdit ? site_url('sales/orders/' . (int) ($order['id'] ?? 0)) : site_url('sales/orders');
$value = static fn (string $field, mixed $default = ''): string => (string) old($field, $order[$field] ?? $default);
$lineValue = static function (array $line, int $index, string $field, mixed $default = ''): string {
    $old = old($field . '.' . $index);
    if ($old !== null) {
        return (string) $old;
    }
    return (string) ($line[$field] ?? $default);
};
$pick = static function (array $row, array $keys, mixed $default = ''): mixed {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && trim((string) $row[$key]) !== '') {
            return $row[$key];
        }
    }
    return $default;
};
$lineRows = $lines !== [] ? $lines : array_fill(0, 3, []);
?>
<style>
    .so-lines-scroll { width: 100%; max-width: 100%; overflow-x: auto; overflow-y: visible; -webkit-overflow-scrolling: touch; border: 1px solid #eff2f7; border-radius: .25rem; }
    .so-lines-table { min-width: 2060px; width: 2060px; table-layout: fixed; margin-bottom: 0; }
    .so-lines-table th, .so-lines-table td { vertical-align: middle; white-space: nowrap; }
    .so-lines-table .form-control, .so-lines-table .form-select, .so-lines-table .select2-container { width: 100% !important; min-width: 0; }
    .so-col-line { width: 80px; }
    .so-col-item-code { width: 230px; }
    .so-col-item-name { width: 210px; }
    .so-col-description { width: 240px; }
    .so-col-qty { width: 110px; }
    .so-col-uom { width: 90px; }
    .so-col-price { width: 130px; }
    .so-col-disc-percent { width: 110px; }
    .so-col-disc-amount { width: 120px; }
    .so-col-freight { width: 120px; }
    .so-col-special { width: 130px; }
    .so-col-other { width: 120px; }
    .so-col-total { width: 140px; }
    .so-col-action { width: 60px; }
</style>
<form method="post" action="<?= esc($action, 'attr') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="discount_percent" value="0">

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1"><?= esc($title ?? ($isEdit ? 'Edit Sales Order' : 'Create Sales Order')) ?></h4>
                    <p class="text-muted mb-0">Manual SO entry for the active company/site.</p>
                </div>
                <a href="<?= $isEdit ? site_url('sales/orders/' . (int) ($order['id'] ?? 0)) : site_url('sales/orders') ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">SO No</label>
                    <input type="text" name="so_no" class="form-control" placeholder="<?= esc($isEdit ? 'Required' : (($suggestedSoNo ?? '') !== '' ? $suggestedSoNo : 'Auto if blank'), 'attr') ?>" value="<?= esc($value('so_no')) ?>">
                    <small class="text-muted">Kosongkan saat create untuk nomor otomatis.</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">SO Date</label>
                    <input type="date" name="so_date" class="form-control" required value="<?= esc($value('so_date', date('Y-m-d'))) ?>">
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
                            $selectedCustomer = (int) old('customer_id', $order['customer_id'] ?? 0) === $customerId;
                            ?>
                            <option value="<?= $customerId ?>" <?= $selectedCustomer ? 'selected' : '' ?> data-code="<?= esc($customerCode, 'attr') ?>" data-name="<?= esc($customerNameValue, 'attr') ?>" data-terms="<?= esc($customerTerms, 'attr') ?>"><?= esc(trim($customerCode . ' - ' . $customerNameValue, ' -')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency_code" class="form-control" value="<?= esc($value('currency_code', 'IDR')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="customer_name" id="customerName" class="form-control" value="<?= esc($value('customer_name')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Terms</label>
                    <input type="text" name="terms_code" id="termsCode" class="form-control" value="<?= esc($value('terms_code')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Discount Amt</label>
                    <input type="number" step="0.01" name="discount_amount" class="form-control calc-header text-end" value="<?= esc($value('discount_amount', '0')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Freight</label>
                    <input type="number" step="0.01" name="freight_amount" class="form-control calc-header text-end" value="<?= esc($value('freight_amount', '0')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Other Amount</label>
                    <input type="number" step="0.01" name="other_amount" class="form-control calc-header text-end" value="<?= esc($value('other_amount', '0')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc($value('notes')) ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2"><?= esc($value('remarks')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="card-title mb-0">Line Items</h4>
                    <small class="text-muted">Geser tabel ke kanan/kiri untuk melihat semua kolom line.</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn"><i class="bx bx-plus me-1"></i> Add Line</button>
            </div>

            <div class="so-lines-scroll">
                <table class="table table-bordered table-sm align-middle so-lines-table" id="soLinesTable">
                    <colgroup>
                        <col class="so-col-line"><col class="so-col-item-code"><col class="so-col-item-name"><col class="so-col-description"><col class="so-col-qty"><col class="so-col-uom"><col class="so-col-price"><col class="so-col-disc-percent"><col class="so-col-disc-amount"><col class="so-col-freight"><col class="so-col-special"><col class="so-col-other"><col class="so-col-total"><col class="so-col-action">
                    </colgroup>
                    <thead class="table-light">
                        <tr><th>Line</th><th>Item Code</th><th>Item Name</th><th>Description</th><th>Qty</th><th>UoM</th><th>Unit Price</th><th>Disc %</th><th>Disc Amt</th><th>Freight</th><th>Special Charge</th><th>Other Amt</th><th class="text-end">Line Total</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lineRows as $i => $line): ?>
                        <?php $currentCode = (string) $lineValue($line, $i, 'item_code'); $currentName = (string) $lineValue($line, $i, 'item_name'); $selectedFound = false; ?>
                        <tr>
                            <td><input type="number" name="so_line[]" class="form-control form-control-sm text-end line-number" min="1" step="1" value="<?= esc($lineValue($line, $i, 'so_line', $line['line_no'] ?? ($i + 1))) ?>" required></td>
                            <td>
                                <input type="hidden" name="item_id[]" class="item-id" value="<?= esc($lineValue($line, $i, 'item_id')) ?>">
                                <input type="hidden" name="item_code_original[]" class="item-code-original" value="<?= esc($currentCode) ?>">
                                <select name="item_code[]" class="form-select form-select-sm item-select">
                                    <option value="">Pilih / cari data</option>
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $code = (string) $pick($item, ['item_code', 'item', 'code', 'sku', 'product_code']);
                                        $name = (string) $pick($item, ['item_name', 'itemn', 'itemna', 'name', 'product_name', 'description']);
                                        $uom = (string) $pick($item, ['sellinguom', 'selling_uom', 'stockuom', 'stock_uom', 'uom_code', 'uom'], 'PCS');
                                        $price = (float) $pick($item, ['sellingprice', 'selling_price', 'item_price', 'price', 'sales_price'], 0);
                                        $selectedItem = $currentCode !== '' && $currentCode === $code;
                                        if ($selectedItem) { $selectedFound = true; }
                                        ?>
                                        <option value="<?= esc($code) ?>" <?= $selectedItem ? 'selected' : '' ?> data-id="<?= (int) ($item['id'] ?? 0) ?>" data-code="<?= esc($code, 'attr') ?>" data-name="<?= esc($name, 'attr') ?>" data-uom="<?= esc($uom, 'attr') ?>" data-price="<?= esc((string) $price, 'attr') ?>"><?= esc(trim($code . ' - ' . $name, ' -')) ?></option>
                                    <?php endforeach ?>
                                    <?php if ($currentCode !== '' && ! $selectedFound): ?>
                                        <option value="<?= esc($currentCode) ?>" selected data-id="<?= esc($lineValue($line, $i, 'item_id')) ?>" data-code="<?= esc($currentCode, 'attr') ?>" data-name="<?= esc($currentName, 'attr') ?>" data-uom="<?= esc($lineValue($line, $i, 'uom_code', 'PCS'), 'attr') ?>" data-price="<?= esc($lineValue($line, $i, 'unit_price', '0'), 'attr') ?>"><?= esc(trim($currentCode . ' - ' . $currentName, ' -')) ?> (existing)</option>
                                    <?php endif ?>
                                </select>
                            </td>
                            <td><input type="text" name="item_name[]" class="form-control form-control-sm item-name" value="<?= esc($currentName) ?>"></td>
                            <td><input type="text" name="description[]" class="form-control form-control-sm" value="<?= esc($lineValue($line, $i, 'description')) ?>"></td>
                            <td><input type="number" step="0.0001" name="qty[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'qty', $line['qty_ordered'] ?? ($i === 0 ? '1' : ''))) ?>"></td>
                            <td><input type="text" name="uom_code[]" class="form-control form-control-sm" value="<?= esc($lineValue($line, $i, 'uom_code', 'PCS')) ?>"></td>
                            <td><input type="number" step="0.01" name="unit_price[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'unit_price', '0')) ?>"></td>
                            <td><input type="number" step="0.0001" name="discount_percent[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'discount_percent', '0')) ?>"></td>
                            <td><input type="number" step="0.01" name="discount_amount[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'discount_amount', '0')) ?>"></td>
                            <td><input type="number" step="0.01" name="freight_amount_line[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'freight_amount', '0')) ?>"></td>
                            <td><input type="number" step="0.01" name="special_charge_amount[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'special_charge_amount', '0')) ?>"></td>
                            <td><input type="number" step="0.01" name="other_amount_line[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'other_amount', '0')) ?>"></td>
                            <td class="text-end fw-semibold line-total">0.00</td>
                            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bx bx-trash"></i></button></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><th colspan="12" class="text-end">Subtotal</th><th class="text-end" id="subtotalText">0.00</th><th></th></tr>
                        <tr><th colspan="12" class="text-end">Discount</th><th class="text-end" id="discountText">0.00</th><th></th></tr>
                        <tr><th colspan="12" class="text-end">Charges</th><th class="text-end" id="chargeText">0.00</th><th></th></tr>
                        <tr><th colspan="12" class="text-end">Total</th><th class="text-end" id="totalText">0.00</th><th></th></tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i> <?= $isEdit ? 'Update SO' : 'Save SO' ?></button>
                <a href="<?= $isEdit ? site_url('sales/orders/' . (int) ($order['id'] ?? 0)) : site_url('sales/orders') ?>" class="btn btn-light">Cancel</a>
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
    function number(value) { const parsed = parseFloat(value || '0'); return Number.isFinite(parsed) ? parsed : 0; }
    function money(value) { return number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function header(name) { const el = document.querySelector('[name="' + name + '"]'); return el ? number(el.value) : 0; }
    function rowNum(row, name) { const el = row.querySelector('[name="' + name + '[]"]'); return el ? number(el.value) : 0; }
    function splitLabel(text) { text = (text || '').trim(); const separatorIndex = text.indexOf(' - '); return separatorIndex >= 0 ? text.slice(separatorIndex + 3).trim() : text; }
    function renderedSelect2Text(select) { if (!select) return ''; const container = select.nextElementSibling; const rendered = container ? container.querySelector('.select2-selection__rendered') : null; return rendered ? rendered.textContent.trim().replace(/^×\s*/, '') : ''; }
    function optionIsPlaceholder(option) { return !option || option.value === ''; }
    function optionName(option, select = null) { if (optionIsPlaceholder(option)) return ''; if (option && option.dataset && option.dataset.name && option.dataset.name.trim() !== '') return option.dataset.name.trim(); if (option && option.textContent && option.textContent.trim() !== '') return splitLabel(option.textContent); return splitLabel(renderedSelect2Text(select)); }
    function optionTerms(option) { if (optionIsPlaceholder(option)) return ''; return option && option.dataset && option.dataset.terms ? option.dataset.terms.trim() : ''; }
    function fillCustomerFromSelected(force = false) { const option = customerSelect.options[customerSelect.selectedIndex]; if (optionIsPlaceholder(option)) { if (force && customerName.value.trim() === 'Manual / No Customer Master') customerName.value = ''; return; } const selectedName = optionName(option, customerSelect); const selectedTerms = optionTerms(option); if ((force || customerName.value.trim() === '' || customerName.value.trim() === 'Manual / No Customer Master') && selectedName !== '') customerName.value = selectedName; if ((force || termsCode.value.trim() === '') && selectedTerms !== '') termsCode.value = selectedTerms; }
    function fillItemRow(row, select) { const option = select.options[select.selectedIndex]; if (optionIsPlaceholder(option)) { row.querySelector('.item-id').value = ''; recalc(); return; } row.querySelector('.item-id').value = option && option.dataset ? (option.dataset.id || '') : ''; row.querySelector('.item-code-original').value = option.value || ''; row.querySelector('[name="item_name[]"]').value = optionName(option, select) || ''; row.querySelector('[name="uom_code[]"]').value = option && option.dataset ? (option.dataset.uom || 'PCS') : 'PCS'; row.querySelector('[name="unit_price[]"]').value = option && option.dataset ? (option.dataset.price || '0') : '0'; recalc(); }
    function recalc() { let subtotal = 0, discount = 0, charges = 0; tbody.querySelectorAll('tr').forEach(function (row) { const qty = rowNum(row, 'qty'); const price = rowNum(row, 'unit_price'); const gross = qty * price; const discPct = rowNum(row, 'discount_percent'); const discAmt = rowNum(row, 'discount_amount'); const lineDiscount = (gross * discPct / 100) + discAmt; const lineCharges = rowNum(row, 'freight_amount_line') + rowNum(row, 'special_charge_amount') + rowNum(row, 'other_amount_line'); const lineTotal = gross - lineDiscount + lineCharges; subtotal += gross; discount += lineDiscount; charges += lineCharges; row.querySelector('.line-total').textContent = money(lineTotal); }); discount += header('discount_amount'); charges += header('freight_amount') + header('other_amount'); document.getElementById('subtotalText').textContent = money(subtotal); document.getElementById('discountText').textContent = money(discount); document.getElementById('chargeText').textContent = money(charges); document.getElementById('totalText').textContent = money(subtotal - discount + charges); }
    function renumberLines() { tbody.querySelectorAll('tr').forEach(function (row, index) { row.querySelector('.line-number').value = index + 1; }); }
    function bindRow(row) { row.querySelectorAll('.calc').forEach(input => input.addEventListener('input', recalc)); const select = row.querySelector('.item-select'); select.addEventListener('change', function () { fillItemRow(row, this); }); row.querySelector('.remove-line').addEventListener('click', function () { if (tbody.querySelectorAll('tr').length > 1) { row.remove(); renumberLines(); recalc(); } }); }
    document.querySelectorAll('.calc-header').forEach(input => input.addEventListener('input', recalc));
    document.getElementById('addLineBtn').addEventListener('click', function () { const clone = tbody.querySelector('tr').cloneNode(true); clone.querySelectorAll('input').forEach(function (input) { if (input.type === 'hidden') { input.value = ''; return; } if (input.name === 'uom_code[]') input.value = 'PCS'; else if (input.classList.contains('calc')) input.value = '0'; else input.value = ''; }); clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; }); tbody.appendChild(clone); renumberLines(); bindRow(clone); recalc(); });
    customerSelect.addEventListener('change', function () { fillCustomerFromSelected(true); });
    tbody.querySelectorAll('tr').forEach(bindRow);
    if (window.jQuery) { window.jQuery(document).on('change select2:select', '#customerSelect', function () { setTimeout(function () { fillCustomerFromSelected(true); }, 0); }); window.jQuery(document).on('change select2:select', '.item-select', function () { const row = this.closest('tr'); if (row) setTimeout(() => fillItemRow(row, this), 0); }); }
    fillCustomerFromSelected(false); recalc();
});
</script>
<?= $this->endSection() ?>
