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

$lineRows = $lines !== [] ? $lines : array_fill(0, 3, []);
?>
<form method="post" action="<?= esc($action, 'attr') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1"><?= esc($title ?? ($isEdit ? 'Edit Purchase Order' : 'Create Purchase Order')) ?></h4>
                    <p class="text-muted mb-0">Commercial fields are entered at header level. Detail lines only contain item, description, quantity, UoM, and price.</p>
                </div>
                <a href="<?= $isEdit ? site_url('purchase/orders/' . (int) $order['id']) : site_url('purchase/orders') ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">PO No</label>
                    <input type="text" name="po_no" class="form-control" required value="<?= esc($value('po_no', 'PO-' . date('Ymd-His'))) ?>">
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
                            $supplierCode = (string) ($supplier['supplier'] ?? $supplier['code'] ?? '');
                            $supplierName = (string) ($supplier['supplierna'] ?? $supplier['name'] ?? '');
                            $selectedSupplier = (int) old('supplier_id', $order['supplier_id'] ?? 0) === $supplierId;
                            ?>
                            <option value="<?= $supplierId ?>" <?= $selectedSupplier ? 'selected' : '' ?> data-name="<?= esc($supplierName, 'attr') ?>" data-terms="<?= esc((string) ($supplier['terms_code'] ?? $supplier['terms'] ?? ''), 'attr') ?>">
                                <?= esc($supplierCode . ' - ' . $supplierName) ?>
                            </option>
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

                <div class="col-md-3 mb-3">
                    <label class="form-label">Header Discount %</label>
                    <input type="number" step="0.0001" name="discount_percent" class="form-control calc-header text-end" value="<?= esc($value('discount_percent', '0')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Header Discount Amount</label>
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
                <div class="col-md-3 mb-3">
                    <label class="form-label">Special Charge</label>
                    <input type="number" step="0.01" name="special_charge_amount" class="form-control calc-header text-end" value="<?= esc($value('special_charge_amount', '0')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">VAT</label>
                    <input type="number" step="0.01" name="vat_amount" class="form-control calc-header text-end" value="<?= esc($value('vat_amount', '0')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">WHT</label>
                    <input type="number" step="0.01" name="wht_amount" class="form-control calc-header text-end" value="<?= esc($value('wht_amount', '0')) ?>">
                </div>
                <div class="col-md-3 mb-3">
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
                <h4 class="card-title mb-0">Line Items</h4>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                    <i class="bx bx-plus me-1"></i> Add Line
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-nowrap align-middle" id="poLinesTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px;">Line</th>
                            <th style="min-width:220px;">Item Code</th>
                            <th style="min-width:200px;">Item Name</th>
                            <th style="min-width:260px;">Description</th>
                            <th style="width:110px;">Qty</th>
                            <th style="width:90px;">UoM</th>
                            <th style="width:130px;">Price</th>
                            <th style="width:140px;" class="text-end">Line Total</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lineRows as $i => $line): ?>
                            <tr>
                                <td><input type="number" name="po_line[]" class="form-control text-end line-number" min="1" step="1" value="<?= esc($lineValue($line, $i, 'po_line', $line['line_no'] ?? ($i + 1))) ?>" required></td>
                                <td>
                                    <input type="hidden" name="item_id[]" class="item-id" value="<?= esc($lineValue($line, $i, 'item_id')) ?>">
                                    <select name="item_code[]" class="form-select item-select">
                                        <option value="">Manual item</option>
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                            $code = (string) ($item['item_code'] ?? $item['code'] ?? '');
                                            $name = (string) ($item['item_name'] ?? $item['name'] ?? '');
                                            $uom = (string) ($item['purchaseuom'] ?? $item['stockuom'] ?? 'PCS');
                                            $price = (float) ($item['purchasep'] ?? $item['item_price'] ?? 0);
                                            $selectedItem = (string) $lineValue($line, $i, 'item_code') === $code;
                                            ?>
                                            <option value="<?= esc($code) ?>" <?= $selectedItem ? 'selected' : '' ?> data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= esc($name, 'attr') ?>" data-uom="<?= esc($uom, 'attr') ?>" data-price="<?= esc((string) $price, 'attr') ?>">
                                                <?= esc($code . ' - ' . $name) ?>
                                            </option>
                                        <?php endforeach ?>
                                    </select>
                                </td>
                                <td><input type="text" name="item_name[]" class="form-control item-name" value="<?= esc($lineValue($line, $i, 'item_name')) ?>"></td>
                                <td><input type="text" name="description[]" class="form-control" value="<?= esc($lineValue($line, $i, 'description')) ?>"></td>
                                <td><input type="number" step="0.0001" name="qty[]" class="form-control calc text-end" value="<?= esc($lineValue($line, $i, 'qty', $line['qty_ordered'] ?? ($i === 0 ? '1' : ''))) ?>"></td>
                                <td><input type="text" name="uom_code[]" class="form-control" value="<?= esc($lineValue($line, $i, 'uom_code', 'PCS')) ?>"></td>
                                <td><input type="number" step="0.01" name="unit_price[]" class="form-control calc text-end" value="<?= esc($lineValue($line, $i, 'unit_price', '0')) ?>"></td>
                                <td class="text-end fw-semibold line-total">0.00</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bx bx-trash"></i></button></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><th colspan="7" class="text-end">Subtotal</th><th class="text-end" id="subtotalText">0.00</th><th></th></tr>
                        <tr><th colspan="7" class="text-end">Header Discount</th><th class="text-end" id="discountText">0.00</th><th></th></tr>
                        <tr><th colspan="7" class="text-end">Freight + Special + Other</th><th class="text-end" id="chargeText">0.00</th><th></th></tr>
                        <tr><th colspan="7" class="text-end">VAT</th><th class="text-end" id="vatText">0.00</th><th></th></tr>
                        <tr><th colspan="7" class="text-end">WHT</th><th class="text-end" id="whtText">0.00</th><th></th></tr>
                        <tr><th colspan="7" class="text-end">Total</th><th class="text-end" id="totalText">0.00</th><th></th></tr>
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

    function number(value) {
        const parsed = parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function money(value) {
        return number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function header(name) {
        const el = document.querySelector('[name="' + name + '"]');
        return el ? number(el.value) : 0;
    }

    function recalc() {
        let subtotal = 0;
        tbody.querySelectorAll('tr').forEach(function (row) {
            const qty = number(row.querySelector('[name="qty[]"]').value);
            const price = number(row.querySelector('[name="unit_price[]"]').value);
            const lineTotal = qty * price;
            subtotal += lineTotal;
            row.querySelector('.line-total').textContent = money(lineTotal);
        });

        let headerDiscount = header('discount_amount');
        if (headerDiscount <= 0 && header('discount_percent') > 0) {
            headerDiscount = subtotal * header('discount_percent') / 100;
        }
        const charges = header('freight_amount') + header('special_charge_amount') + header('other_amount');
        const vat = header('vat_amount');
        const wht = header('wht_amount');
        const total = subtotal - headerDiscount + charges + vat - wht;

        document.getElementById('subtotalText').textContent = money(subtotal);
        document.getElementById('discountText').textContent = money(headerDiscount);
        document.getElementById('chargeText').textContent = money(charges);
        document.getElementById('vatText').textContent = money(vat);
        document.getElementById('whtText').textContent = money(wht);
        document.getElementById('totalText').textContent = money(total);
    }

    function renumberLines() {
        tbody.querySelectorAll('tr').forEach(function (row, index) {
            row.querySelector('.line-number').value = index + 1;
        });
    }

    function bindRow(row) {
        row.querySelectorAll('.calc').forEach(input => input.addEventListener('input', recalc));
        row.querySelector('.item-select').addEventListener('change', function () {
            const option = this.options[this.selectedIndex];
            row.querySelector('.item-id').value = option?.dataset.id || '';
            row.querySelector('[name="item_name[]"]').value = option?.dataset.name || '';
            row.querySelector('[name="uom_code[]"]').value = option?.dataset.uom || 'PCS';
            row.querySelector('[name="unit_price[]"]').value = option?.dataset.price || '0';
            recalc();
        });
        row.querySelector('.remove-line').addEventListener('click', function () {
            if (tbody.querySelectorAll('tr').length > 1) {
                row.remove();
                renumberLines();
                recalc();
            }
        });
    }

    document.querySelectorAll('.calc-header').forEach(input => input.addEventListener('input', recalc));
    document.getElementById('addLineBtn').addEventListener('click', function () {
        const clone = tbody.querySelector('tr').cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) {
            if (input.type === 'hidden') {
                input.value = '';
                return;
            }
            if (input.name === 'uom_code[]') input.value = 'PCS';
            else if (input.classList.contains('calc')) input.value = '0';
            else input.value = '';
        });
        clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
        tbody.appendChild(clone);
        renumberLines();
        bindRow(clone);
        recalc();
    });

    supplierSelect.addEventListener('change', function () {
        const option = supplierSelect.options[supplierSelect.selectedIndex];
        if (option && option.dataset.name) supplierName.value = option.dataset.name;
        if (option && option.dataset.terms) termsCode.value = option.dataset.terms;
    });

    tbody.querySelectorAll('tr').forEach(bindRow);
    recalc();
});
</script>
<?= $this->endSection() ?>
