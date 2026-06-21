<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$order ??= [];
$lines ??= [];
$isEdit = (bool) ($isEdit ?? false);
$action ??= $isEdit ? site_url('purchase/orders/' . (int) ($order['id'] ?? 0)) : site_url('purchase/orders');

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
    .po-lines-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #eff2f7;
        border-radius: .25rem;
    }
    .po-lines-table {
        min-width: 1780px;
        width: 1780px;
        table-layout: fixed;
        margin-bottom: 0;
    }
    .po-lines-table th,
    .po-lines-table td {
        vertical-align: middle;
        white-space: nowrap;
    }
    .po-lines-table .form-control,
    .po-lines-table .form-select,
    .po-lines-table .select2-container {
        width: 100% !important;
        min-width: 0;
    }
    .po-col-line { width: 80px; }
    .po-col-item-code { width: 210px; }
    .po-col-item-name { width: 190px; }
    .po-col-description { width: 240px; }
    .po-col-qty { width: 100px; }
    .po-col-uom { width: 90px; }
    .po-col-price { width: 120px; }
    .po-col-disc-percent { width: 110px; }
    .po-col-disc-amount { width: 120px; }
    .po-col-vat { width: 110px; }
    .po-col-wht { width: 110px; }
    .po-col-total { width: 130px; }
    .po-col-action { width: 60px; }
</style>
<form method="post" action="<?= esc($action, 'attr') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1"><?= esc($title ?? ($isEdit ? 'Edit Purchase Order' : 'Create Purchase Order')) ?></h4>
                    <p class="text-muted mb-0">Header berisi biaya global. VAT/WHT diisi sebagai kode pajak, nominal pajak dihitung dari detail/import bila tersedia.</p>
                </div>
                <a href="<?= $isEdit ? site_url('purchase/orders/' . (int) $order['id']) : site_url('purchase/orders') ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">PO No</label>
                    <input type="text" name="po_no" class="form-control" placeholder="<?= esc($isEdit ? 'Required' : (($suggestedPoNo ?? '') !== '' ? $suggestedPoNo : 'Auto if blank'), 'attr') ?>" value="<?= esc($value('po_no')) ?>">
                    <small class="text-muted">Kosongkan saat create untuk nomor otomatis.</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">PO Date</label>
                    <input type="date" name="po_date" class="form-control" required value="<?= esc($value('po_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Delivery Date</label>
                    <input type="date" name="delivery_date" class="form-control" value="<?= esc($value('delivery_date')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Arrive Date</label>
                    <input type="date" name="arrive_date" class="form-control" value="<?= esc($value('arrive_date')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select" id="supplierSelect">
                        <option value="">Manual / No Supplier Master</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <?php
                            $supplierId = (int) ($supplier['id'] ?? 0);
                            $supplierCode = (string) $pick($supplier, ['supplier_code', 'supplier', 'code', 'vendor_code', 'vend_code']);
                            $supplierName = (string) $pick($supplier, ['supplier_name', 'supplierna', 'suppliern', 'name', 'vendor_name', 'description']);
                            $supplierTerms = (string) $pick($supplier, ['terms_code', 'terms', 'payment_terms']);
                            $selectedSupplier = (int) old('supplier_id', $order['supplier_id'] ?? 0) === $supplierId;
                            ?>
                            <option value="<?= $supplierId ?>" <?= $selectedSupplier ? 'selected' : '' ?> data-code="<?= esc($supplierCode, 'attr') ?>" data-name="<?= esc($supplierName, 'attr') ?>" data-terms="<?= esc($supplierTerms, 'attr') ?>"><?= esc(trim($supplierCode . ' - ' . $supplierName, ' -')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Supplier Name</label>
                    <input type="text" name="supplier_name" id="supplierName" class="form-control" value="<?= esc($value('supplier_name')) ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Terms</label>
                    <input type="text" name="terms_code" id="termsCode" class="form-control" value="<?= esc($value('terms_code')) ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency_code" class="form-control" value="<?= esc($value('currency_code', 'IDR')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Header Discount</label>
                    <div class="input-group">
                        <span class="input-group-text">%</span>
                        <input type="number" step="0.0001" name="discount_percent" class="form-control calc-header text-end" value="<?= esc($value('discount_percent', '0')) ?>">
                        <span class="input-group-text">Amount</span>
                        <input type="number" step="0.01" name="discount_amount" class="form-control calc-header text-end" value="<?= esc($value('discount_amount', '0')) ?>">
                    </div>
                </div>
                <div class="col-md-3 mb-3"><label class="form-label">Freight</label><input type="number" step="0.01" name="freight_amount" class="form-control calc-header text-end" value="<?= esc($value('freight_amount', '0')) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Other Amount</label><input type="number" step="0.01" name="other_amount" class="form-control calc-header text-end" value="<?= esc($value('other_amount', '0')) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Special Charge</label><input type="number" step="0.01" name="special_charge_amount" class="form-control calc-header text-end" value="<?= esc($value('special_charge_amount', '0')) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">VAT Code</label><input type="text" name="vat_code" class="form-control" value="<?= esc($value('vat_code')) ?>" placeholder="PPN / VAT"></div>
                <div class="col-md-3 mb-3"><label class="form-label">WHT Code</label><input type="text" name="wht_code" class="form-control" value="<?= esc($value('wht_code')) ?>" placeholder="PPh / WHT"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" value="<?= esc($value('notes')) ?>"></div>
                <div class="col-md-12 mb-3"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"><?= esc($value('remarks')) ?></textarea></div>
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

            <div class="po-lines-scroll">
                <table class="table table-bordered table-sm align-middle po-lines-table" id="poLinesTable">
                    <colgroup>
                        <col class="po-col-line"><col class="po-col-item-code"><col class="po-col-item-name"><col class="po-col-description"><col class="po-col-qty"><col class="po-col-uom"><col class="po-col-price"><col class="po-col-disc-percent"><col class="po-col-disc-amount"><col class="po-col-vat"><col class="po-col-wht"><col class="po-col-total"><col class="po-col-action">
                    </colgroup>
                    <thead class="table-light">
                        <tr><th>Line</th><th>Item Code</th><th>Item Name</th><th>Description</th><th>Qty</th><th>UoM</th><th>Price</th><th>Disc %</th><th>Disc Amt</th><th>VAT</th><th>WHT</th><th class="text-end">Line Total</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lineRows as $i => $line): ?>
                            <?php $currentCode = (string) $lineValue($line, $i, 'item_code'); $currentName = (string) $lineValue($line, $i, 'item_name'); $selectedFound = false; ?>
                            <tr>
                                <td><input type="number" name="po_line[]" class="form-control form-control-sm text-end line-number" min="1" step="1" value="<?= esc($lineValue($line, $i, 'po_line', $line['line_no'] ?? ($i + 1))) ?>" required></td>
                                <td>
                                    <input type="hidden" name="item_id[]" class="item-id" value="<?= esc($lineValue($line, $i, 'item_id')) ?>">
                                    <input type="hidden" name="item_code_original[]" class="item-code-original" value="<?= esc($currentCode) ?>">
                                    <select name="item_code[]" class="form-select form-select-sm item-select">
                                        <option value="">Pilih / cari data</option>
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                            $code = (string) $pick($item, ['item_code', 'item', 'code', 'sku', 'product_code']);
                                            $name = (string) $pick($item, ['item_name', 'itemn', 'itemna', 'name', 'product_name', 'description']);
                                            $uom = (string) $pick($item, ['purchaseuom', 'purchase_uom', 'stockuom', 'stock_uom', 'uom_code', 'uom'], 'PCS');
                                            $price = (float) $pick($item, ['purchasep', 'purchase_price', 'item_price', 'price', 'cost_price'], 0);
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
                                <td><input type="number" step="0.0001" name="line_discount_percent[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'discount_percent', '0')) ?>"></td>
                                <td><input type="number" step="0.01" name="line_discount_amount[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'discount_amount', '0')) ?>"></td>
                                <td><input type="number" step="0.01" name="line_vat_amount[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'vat_amount', '0')) ?>"></td>
                                <td><input type="number" step="0.01" name="line_wht_amount[]" class="form-control form-control-sm calc text-end" value="<?= esc($lineValue($line, $i, 'wht_amount', '0')) ?>"></td>
                                <td class="text-end fw-semibold line-total">0.00</td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bx bx-trash"></i></button></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><th colspan="11" class="text-end">Subtotal</th><th class="text-end" id="subtotalText">0.00</th><th></th></tr>
                        <tr><th colspan="11" class="text-end">Total Discount</th><th class="text-end" id="discountText">0.00</th><th></th></tr>
                        <tr><th colspan="11" class="text-end">Freight + Special + Other</th><th class="text-end" id="chargeText">0.00</th><th></th></tr>
                        <tr><th colspan="11" class="text-end">VAT</th><th class="text-end" id="vatText">0.00</th><th></th></tr>
                        <tr><th colspan="11" class="text-end">WHT</th><th class="text-end" id="whtText">0.00</th><th></th></tr>
                        <tr><th colspan="11" class="text-end">Total</th><th class="text-end" id="totalText">0.00</th><th></th></tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i> <?= $isEdit ? 'Update PO' : 'Save PO' ?></button>
                <a href="<?= $isEdit ? site_url('purchase/orders/' . (int) $order['id']) : site_url('purchase/orders') ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('poLinesTable');
    const tbody = table.querySelector('tbody');
    const supplierSelect = document.getElementById('supplierSelect');
    const supplierName = document.getElementById('supplierName');
    const termsCode = document.getElementById('termsCode');
    function number(value) { const parsed = parseFloat(value || '0'); return Number.isFinite(parsed) ? parsed : 0; }
    function money(value) { return number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function header(name) { const el = document.querySelector('[name="' + name + '"]'); return el ? number(el.value) : 0; }
    function lineInput(row, name) { const el = row.querySelector('[name="' + name + '[]"]'); return el ? number(el.value) : 0; }
    function splitLabel(text) { text = (text || '').trim(); const separatorIndex = text.indexOf(' - '); return separatorIndex >= 0 ? text.slice(separatorIndex + 3).trim() : text; }
    function renderedSelect2Text(select) { if (!select) return ''; const container = select.nextElementSibling; const rendered = container ? container.querySelector('.select2-selection__rendered') : null; return rendered ? rendered.textContent.trim().replace(/^×\s*/, '') : ''; }
    function optionName(option, select = null) { if (option && option.dataset && option.dataset.name && option.dataset.name.trim() !== '') return option.dataset.name.trim(); if (option && option.textContent && option.textContent.trim() !== '') return splitLabel(option.textContent); return splitLabel(renderedSelect2Text(select)); }
    function optionTerms(option) { return option && option.dataset && option.dataset.terms ? option.dataset.terms.trim() : ''; }
    function fillSupplierFromSelected(force = false) {
        const option = supplierSelect.options[supplierSelect.selectedIndex];
        const selectedName = optionName(option, supplierSelect);
        const selectedTerms = optionTerms(option);
        if ((force || supplierName.value.trim() === '') && selectedName !== '') supplierName.value = selectedName;
        if ((force || termsCode.value.trim() === '') && selectedTerms !== '') termsCode.value = selectedTerms;
    }
    function fillItemRow(row, select) {
        const option = select.options[select.selectedIndex];
        if (!option || !option.value) return;
        row.querySelector('.item-id').value = option.dataset ? (option.dataset.id || '') : '';
        row.querySelector('.item-code-original').value = option.value || '';
        row.querySelector('[name="item_name[]"]').value = optionName(option, select) || '';
        row.querySelector('[name="uom_code[]"]').value = option.dataset ? (option.dataset.uom || 'PCS') : 'PCS';
        row.querySelector('[name="unit_price[]"]').value = option.dataset ? (option.dataset.price || '0') : '0';
        recalc();
    }
    function recalc() {
        let subtotal = 0, lineDiscount = 0, lineVat = 0, lineWht = 0;
        tbody.querySelectorAll('tr').forEach(function (row) {
            const qty = lineInput(row, 'qty'); const price = lineInput(row, 'unit_price'); const gross = qty * price;
            const discPct = lineInput(row, 'line_discount_percent'); const discAmt = lineInput(row, 'line_discount_amount'); const discount = (gross * discPct / 100) + discAmt;
            const vat = lineInput(row, 'line_vat_amount'); const wht = lineInput(row, 'line_wht_amount'); const lineTotal = gross - discount + vat - wht;
            subtotal += gross; lineDiscount += discount; lineVat += vat; lineWht += wht; row.querySelector('.line-total').textContent = money(lineTotal);
        });
        const percentDiscount = subtotal * header('discount_percent') / 100;
        const manualDiscount = header('discount_amount');
        const totalDiscount = percentDiscount + manualDiscount + lineDiscount;
        const charges = header('freight_amount') + header('special_charge_amount') + header('other_amount');
        const vat = lineVat;
        const wht = lineWht;
        const total = subtotal - totalDiscount + charges + vat - wht;
        document.getElementById('subtotalText').textContent = money(subtotal);
        document.getElementById('discountText').textContent = money(totalDiscount);
        document.getElementById('chargeText').textContent = money(charges);
        document.getElementById('vatText').textContent = money(vat);
        document.getElementById('whtText').textContent = money(wht);
        document.getElementById('totalText').textContent = money(total);
    }
    function renumberLines() { tbody.querySelectorAll('tr').forEach(function (row, index) { row.querySelector('.line-number').value = index + 1; }); }
    function bindRow(row) { row.querySelectorAll('.calc').forEach(input => input.addEventListener('input', recalc)); const select = row.querySelector('.item-select'); select.addEventListener('change', function () { fillItemRow(row, this); }); row.querySelector('.remove-line').addEventListener('click', function () { if (tbody.querySelectorAll('tr').length > 1) { row.remove(); renumberLines(); recalc(); } }); }
    document.querySelectorAll('.calc-header').forEach(input => input.addEventListener('input', recalc));
    document.getElementById('addLineBtn').addEventListener('click', function () {
        const clone = tbody.querySelector('tr').cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) { if (input.type === 'hidden') { input.value = ''; return; } if (input.name === 'uom_code[]') input.value = 'PCS'; else if (input.classList.contains('calc')) input.value = '0'; else input.value = ''; });
        clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
        tbody.appendChild(clone); renumberLines(); bindRow(clone); recalc();
    });
    supplierSelect.addEventListener('change', function () { fillSupplierFromSelected(true); });
    tbody.querySelectorAll('tr').forEach(bindRow);
    if (window.jQuery) { window.jQuery(document).on('change select2:select', '#supplierSelect', function () { setTimeout(function () { fillSupplierFromSelected(true); }, 0); }); window.jQuery(document).on('change select2:select', '.item-select', function () { const row = this.closest('tr'); if (row) setTimeout(() => fillItemRow(row, this), 0); }); }
    fillSupplierFromSelected(false); recalc();
});
</script>
<?= $this->endSection() ?>
