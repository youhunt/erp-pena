<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$oldQtys = old('qty_received', []);
$oldBatchNos = old('batch_no', []);
$oldUnitPrices = old('unit_price', []);
$oldFreights = old('freight_amount', []);
$oldSpecialPrices = old('special_price', []);
if (! is_array($oldQtys)) $oldQtys = [];
if (! is_array($oldBatchNos)) $oldBatchNos = [];
if (! is_array($oldUnitPrices)) $oldUnitPrices = [];
if (! is_array($oldFreights)) $oldFreights = [];
if (! is_array($oldSpecialPrices)) $oldSpecialPrices = [];
$selectedWarehouseId = (int) old('warehouse_id', $selectedWarehouseId ?? 0);
$selectedLocationId = (int) old('location_id', $selectedLocationId ?? 0);
$itemDisplay = static function (array $line): array {
    $code = trim((string) ($line['item_code'] ?? $line['item'] ?? $line['item_no'] ?? ''));
    $name = trim((string) ($line['item_name'] ?? $line['description'] ?? ''));
    if ($code === '' && $name !== '') {
        $code = $name;
        $name = '';
    }
    return [$code !== '' ? $code : '-', $name !== '' ? $name : '-'];
};
?>
<form method="post" action="<?= site_url('purchase/orders/' . $po['id'] . '/receive') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="card-title mb-1">Receive Purchase Order</h4>
                    <p class="text-muted mb-0"><?= esc($po['po_no']) ?> - <?= esc($po['supplier_name'] ?? '-') ?></p>
                </div>
                <a href="<?= site_url('purchase/orders/' . $po['id']) ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back to PO</a>
            </div>

            <?php if (session('error')): ?>
                <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php endif ?>
            <?php if (session('message')): ?>
                <div class="alert alert-success"><?= esc(session('message')) ?></div>
            <?php endif ?>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Receipt No</label>
                    <input type="text" name="receipt_no" class="form-control" placeholder="<?= esc(($suggestedReceiptNo ?? '') !== '' ? $suggestedReceiptNo : 'Auto if blank', 'attr') ?>" value="<?= esc(old('receipt_no')) ?>">
                    <small class="text-muted">Kosongkan untuk nomor otomatis.</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Receipt Date</label>
                    <input type="date" name="receipt_date" class="form-control" required value="<?= esc(old('receipt_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" id="receiptWarehouse" class="form-select" required>
                        <option value="">Select Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <?php $warehouseId = (int) $warehouse['id']; ?>
                            <option value="<?= $warehouseId ?>" <?= $selectedWarehouseId === $warehouseId ? 'selected' : '' ?>><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Location</label>
                    <select name="location_id" id="receiptLocation" class="form-select" required data-selected-location-id="<?= esc((string) $selectedLocationId, 'attr') ?>">
                        <option value="">Select Location</option>
                        <?php foreach ($locations as $location): ?>
                            <?php $locationId = (int) $location['id']; ?>
                            <option value="<?= $locationId ?>" data-warehouse-id="<?= (int) ($location['warehouse_id'] ?? 0) ?>" <?= $selectedLocationId === $locationId ? 'selected' : '' ?>><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                    <small class="text-muted">Location otomatis difilter sesuai warehouse yang dipilih.</small>
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
            <h4 class="card-title mb-3">Outstanding Lines</h4>
            <div class="alert alert-info py-2">
                Pada PO Receipt, line menampilkan <strong>Price</strong>, <strong>Freight</strong>, dan <strong>Special Price</strong> yang bisa diedit. Nilai stock/GL receipt dihitung dari Price + Freight + Special Price.
            </div>
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0" id="receiptLinesTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th style="min-width:150px;">Batch No</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Received</th>
                            <th class="text-end">Outstanding</th>
                            <th class="text-end" style="min-width:140px;">Receive Now</th>
                            <th class="text-end" style="min-width:140px;">Price</th>
                            <th class="text-end" style="min-width:140px;">Freight</th>
                            <th class="text-end" style="min-width:150px;">Special Price</th>
                            <th class="text-end" style="min-width:160px;">Line Value</th>
                            <th>UoM</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $index => $line): ?>
                        <?php
                        $outstanding = (float) ($line['qty_outstanding'] ?? $line['qty'] ?? 0);
                        $qtyValue = array_key_exists($index, $oldQtys) ? $oldQtys[$index] : $outstanding;
                        $batchValue = array_key_exists($index, $oldBatchNos) ? $oldBatchNos[$index] : '';
                        $priceValue = array_key_exists($index, $oldUnitPrices) ? $oldUnitPrices[$index] : ($line['unit_price'] ?? 0);
                        $freightValue = array_key_exists($index, $oldFreights) ? $oldFreights[$index] : ($line['freight_amount'] ?? 0);
                        $specialValue = array_key_exists($index, $oldSpecialPrices) ? $oldSpecialPrices[$index] : ($line['special_price'] ?? $line['special_charge_amount'] ?? 0);
                        [$displayCode, $displayName] = $itemDisplay($line);
                        ?>
                        <tr>
                            <td><?= esc($line['po_line'] ?? $line['line_no']) ?><input type="hidden" name="purchase_order_line_id[]" value="<?= (int) $line['id'] ?>"></td>
                            <td><div class="fw-semibold"><?= esc($displayCode) ?></div><small class="text-muted"><?= esc($displayName) ?></small></td>
                            <td><input type="text" name="batch_no[]" class="form-control" value="<?= esc((string) $batchValue) ?>" placeholder="Optional"></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_ordered'] ?? $line['qty'] ?? 0), 4)) ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_received'] ?? 0), 4)) ?></td>
                            <td class="text-end fw-semibold outstanding-qty"><?= esc(number_format($outstanding, 4, '.', '')) ?></td>
                            <td><input type="text" inputmode="decimal" name="qty_received[]" class="form-control text-end receive-now" data-outstanding="<?= esc((string) $outstanding, 'attr') ?>" value="<?= esc((string) $qtyValue) ?>"></td>
                            <td><input type="text" inputmode="decimal" name="unit_price[]" class="form-control text-end unit-price" value="<?= esc((string) $priceValue) ?>"></td>
                            <td><input type="text" inputmode="decimal" name="freight_amount[]" class="form-control text-end freight-amount" value="<?= esc((string) $freightValue) ?>"></td>
                            <td><input type="text" inputmode="decimal" name="special_price[]" class="form-control text-end special-price" value="<?= esc((string) $specialValue) ?>"></td>
                            <td class="text-end fw-semibold line-cost">0.00<input type="hidden" name="unit_cost[]" class="unit-cost-hidden" value="0"></td>
                            <td><?= esc($line['uom_code'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach ?>

                    <?php if ($lines === []): ?>
                        <tr><td colspan="12" class="text-center text-muted py-4">No outstanding line to receive.</td></tr>
                    <?php endif ?>
                    </tbody>
                    <?php if ($lines !== []): ?>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="6" class="text-end">Total Receive Now</th>
                            <th class="text-end" id="totalReceiveNow">0.0000</th>
                            <th colspan="3" class="text-end">Total Receipt Value</th>
                            <th class="text-end" id="totalReceiptCost">0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                    <?php endif ?>
                </table>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" <?= $lines === [] ? 'disabled' : '' ?> onclick="return confirm('Post receipt ini? Price/Freight/Special Price akan membentuk nilai stock dan GL receipt.')"><i class="bx bx-package me-1"></i> Post Receipt & Update Stock</button>
                <a href="<?= site_url('purchase/orders/' . $po['id']) ?>" class="btn btn-light">Back to PO</a>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const warehouse = document.getElementById('receiptWarehouse');
    const location = document.getElementById('receiptLocation');
    const totalReceiveNow = document.getElementById('totalReceiveNow');
    const totalReceiptCost = document.getElementById('totalReceiptCost');
    const locationOptionsUrl = '<?= site_url('setup/options/locations') ?>';
    const originalLocations = location
        ? Array.from(location.options).filter(option => option.value !== '').map(option => ({
            value: option.value,
            text: option.text,
            warehouseId: option.dataset.warehouseId || '',
            selected: option.selected
        }))
        : [];

    function number(value) {
        value = String(value || '').trim();
        if (value.indexOf(',') >= 0 && value.indexOf('.') < 0) value = value.replace(',', '.');
        else value = value.replace(/,/g, '');
        const parsed = parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function recalcTotals() {
        let totalQty = 0;
        let totalValue = 0;
        document.querySelectorAll('#receiptLinesTable tbody tr').forEach(function (row) {
            const qtyInput = row.querySelector('.receive-now');
            const priceInput = row.querySelector('.unit-price');
            const freightInput = row.querySelector('.freight-amount');
            const specialInput = row.querySelector('.special-price');
            const hiddenCost = row.querySelector('.unit-cost-hidden');
            const lineCostCell = row.querySelector('.line-cost');
            if (! qtyInput || ! priceInput || ! freightInput || ! specialInput || ! lineCostCell) return;
            const qty = number(qtyInput.value);
            const unitPrice = number(priceInput.value);
            const freight = number(freightInput.value);
            const special = number(specialInput.value);
            const receiptUnitValue = unitPrice + freight + special;
            const lineValue = qty * receiptUnitValue;
            if (hiddenCost) hiddenCost.value = receiptUnitValue.toFixed(6);
            totalQty += qty;
            totalValue += lineValue;
            lineCostCell.firstChild.nodeValue = lineValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        });
        if (totalReceiveNow) totalReceiveNow.textContent = totalQty.toLocaleString(undefined, {minimumFractionDigits: 4, maximumFractionDigits: 4});
        if (totalReceiptCost) totalReceiptCost.textContent = totalValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function hasSelect2(select) {
        return !!(select && window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && window.jQuery(select).data('select2'));
    }
    function destroySelect2(select) { if (hasSelect2(select)) window.jQuery(select).select2('destroy'); }
    function initSelect2(select) {
        if (!select || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
        if (window.PenaSelect) { window.PenaSelect.init(select.parentElement || document); return; }
        if (!window.jQuery(select).data('select2')) window.jQuery(select).select2({ width: '100%' });
    }
    function notifySelectChanged(select, reinitSelect2) {
        if (!select) return;
        if (reinitSelect2) initSelect2(select);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.jQuery) jQuery(select).trigger('change.select2').trigger('change');
    }
    function setLocationOptions(options, selectedValue) {
        if (!location) return;
        const wasEnhanced = hasSelect2(location);
        destroySelect2(location);
        location.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = options.length ? 'Select Location' : 'No location for selected warehouse';
        location.appendChild(placeholder);
        options.forEach(function (item) {
            const option = document.createElement('option');
            option.value = String(item.value || '');
            option.textContent = String(item.label || item.text || item.value || '');
            location.appendChild(option);
        });
        const fallback = options[0] ? String(options[0].value || '') : '';
        const selectedExists = options.some(item => String(item.value || '') === String(selectedValue || ''));
        location.value = selectedExists ? String(selectedValue) : fallback;
        location.dataset.selectedLocationId = location.value;
        notifySelectChanged(location, wasEnhanced);
    }
    function setLocationLoading(text) {
        if (!location) return;
        const wasEnhanced = hasSelect2(location);
        destroySelect2(location);
        location.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = text;
        location.appendChild(option);
        location.value = '';
        notifySelectChanged(location, wasEnhanced);
    }
    function fetchLocations() {
        if (!warehouse || !location) return;
        const warehouseId = warehouse.value;
        const selectedBefore = location.value || location.dataset.selectedLocationId || '';
        if (!warehouseId) { setLocationOptions([], ''); return; }
        setLocationLoading('Loading locations...');
        fetch(locationOptionsUrl + '?warehouse_id=' + encodeURIComponent(warehouseId), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(response => response.ok ? response.json() : Promise.reject(new Error('Location request failed')))
            .then(options => setLocationOptions(Array.isArray(options) ? options : [], selectedBefore))
            .catch(() => {
                const matching = originalLocations.filter(item => item.warehouseId === warehouseId).map(item => ({value: item.value, label: item.text}));
                setLocationOptions(matching, selectedBefore);
            });
    }

    document.querySelectorAll('.receive-now').forEach(function (input) {
        input.addEventListener('input', function () {
            const outstanding = number(input.dataset.outstanding);
            const value = number(input.value);
            input.classList.toggle('is-invalid', value < 0 || value > outstanding);
            recalcTotals();
        });
    });
    document.querySelectorAll('.unit-price, .freight-amount, .special-price').forEach(function (input) {
        input.addEventListener('input', function () {
            const value = number(input.value);
            input.classList.toggle('is-invalid', value < 0);
            recalcTotals();
        });
    });
    if (warehouse) warehouse.addEventListener('change', fetchLocations);
    fetchLocations();
    recalcTotals();
});
</script>
<?= $this->endSection() ?>
