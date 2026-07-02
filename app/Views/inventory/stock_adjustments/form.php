<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$contextItemCodes ??= [];
$contextQtyByCode ??= [];
$sourceSoNo ??= '';
$sourceSoId = (int) ($sourceSoId ?? 0);
$isSoContext = $contextItemCodes !== [];
$oldQtys = old('qty', []);
$oldUnitCosts = old('unit_cost', []);
if (! is_array($oldQtys)) $oldQtys = [];
if (! is_array($oldUnitCosts)) $oldUnitCosts = [];
$itemByCode = [];
foreach ($items as $item) {
    $code = strtoupper(trim((string) ($item['code'] ?? $item['item_code'] ?? '')));
    if ($code !== '') $itemByCode[$code] = $item;
}
$bulkRows = [];
foreach ($contextItemCodes as $idx => $code) {
    $code = strtoupper(trim((string) $code));
    if ($code === '') continue;
    $item = $itemByCode[$code] ?? [];
    $bulkRows[] = [
        'code' => $code,
        'name' => (string) ($item['name'] ?? $item['item_name'] ?? $code),
        'uom' => (string) ($item['uom_code'] ?? $item['base_uom_code'] ?? 'PCS'),
        'cost' => (string) ($item['purchase_price'] ?? $item['standard_cost'] ?? $item['unit_cost'] ?? $item['avg_cost'] ?? '0'),
        'qty' => (string) ($oldQtys[$idx] ?? ($contextQtyByCode[$code] ?? 1)),
    ];
}
$defaultContextItemCode = '';
foreach ($items as $contextItem) {
    $defaultContextItemCode = (string) ($contextItem['code'] ?? $contextItem['item_code'] ?? '');
    if ($defaultContextItemCode !== '') break;
}
$selectedItemCode = (string) old('item_code', count($items) === 1 ? $defaultContextItemCode : '');
?>

<?php if ($isSoContext): ?>
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1">SO Stock Adjustment</h4>
                        <p class="text-muted mb-0">Add stock for the selected Sales Order items only. The item list and default quantities are taken from the SO outstanding lines.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($sourceSoId > 0): ?>
                            <a href="<?= site_url('sales/orders/' . $sourceSoId . '/deliver') ?>" class="btn btn-light">Back to Delivery</a>
                        <?php endif ?>
                        <a href="<?= site_url('inventory/stock-adjustment') ?>" class="btn btn-outline-secondary">Manual Adjustment</a>
                    </div>
                </div>

                <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>
                <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>

                <div class="alert alert-primary py-2">
                    <strong>Sales Order<?= $sourceSoNo !== '' ? ' ' . esc($sourceSoNo) : '' ?>:</strong>
                    this form is limited to these SO items: <?= esc(implode(', ', $contextItemCodes)) ?>.
                </div>

                <form method="post" action="<?= site_url('inventory/stock-adjustment') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="source_so_id" value="<?= esc((string) $sourceSoId) ?>">
                    <input type="hidden" name="source_so_no" value="<?= esc($sourceSoNo) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Reference No</label>
                            <input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no', 'ADJ-SO-' . date('Ymd-His'))) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select name="warehouse_id" id="warehouseSelect" class="form-select" required>
                                <option value="">Select / search Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <?php $warehouseId = (int) $warehouse['id']; ?>
                                    <option value="<?= $warehouseId ?>" <?= (string) old('warehouse_id') === (string) $warehouseId ? 'selected' : '' ?>><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <select name="location_id" id="locationSelect" class="form-select" required>
                                <option value="__auto__" data-warehouse-id="">Auto Location by Warehouse</option>
                                <?php foreach ($locations as $location): ?>
                                    <?php $locationId = (int) $location['id']; ?>
                                    <option value="<?= $locationId ?>" data-warehouse-id="<?= esc((string) ($location['warehouse_id'] ?? ''), 'attr') ?>" <?= (string) old('location_id') === (string) $locationId ? 'selected' : '' ?>><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text" id="locationHelp">Select warehouse first so the location can be resolved.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" value="<?= esc(old('notes', $sourceSoNo !== '' ? 'Stock adjustment for Sales Order ' . $sourceSoNo : 'SO stock adjustment')) ?>">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:60px">#</th>
                                    <th style="min-width:170px">Item Code</th>
                                    <th style="min-width:260px">Item Name</th>
                                    <th style="width:130px">UoM</th>
                                    <th class="text-end" style="width:170px">Qty to Add</th>
                                    <th class="text-end" style="width:180px">Unit Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bulkRows as $i => $row): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><input type="text" name="item_code[]" class="form-control form-control-sm" readonly value="<?= esc($row['code']) ?>"></td>
                                    <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="<?= esc($row['name']) ?>"></td>
                                    <td><input type="text" name="uom_code[]" class="form-control form-control-sm" value="<?= esc($row['uom']) ?>"></td>
                                    <td><input type="number" step="0.0001" name="qty[]" class="form-control form-control-sm text-end bulk-qty" value="<?= esc($row['qty']) ?>"></td>
                                    <td><input type="number" step="0.000001" name="unit_cost[]" class="form-control form-control-sm text-end" value="<?= esc($oldUnitCosts[$i] ?? $row['cost']) ?>"></td>
                                </tr>
                            <?php endforeach ?>
                            <?php if ($bulkRows === []): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No SO item found for adjustment.</td></tr>
                            <?php endif ?>
                            </tbody>
                            <?php if ($bulkRows !== []): ?>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Total Qty</th>
                                    <th class="text-end" id="bulkQtyTotal">0.0000</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                            <?php endif ?>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        Qty is defaulted from SO outstanding quantity. Set a line to 0 if that item does not need stock adjustment now. After posting, the system returns to Delivery with the selected warehouse/location already refreshed.
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Post SO stock adjustment for these items?')" <?= $bulkRows === [] ? 'disabled' : '' ?>>
                            <i class="bx bx-save me-1"></i> Save SO Stock Adjustment
                        </button>
                        <?php if ($sourceSoId > 0): ?><a href="<?= site_url('sales/orders/' . $sourceSoId . '/deliver') ?>" class="btn btn-light">Cancel</a><?php endif ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Stock Adjustment</h4>
                <p class="text-muted mb-3">Manual stock correction. Select warehouse, location, item, qty, and unit cost.</p>

                <div class="alert alert-info py-2">
                    <strong>Procedure:</strong> select Warehouse → select Location → select Item → fill Qty and Unit Cost → Post Adjustment.
                </div>

                <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>
                <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>

                <?php if ($items === []): ?>
                    <div class="alert alert-warning">Item master is empty or does not match the active company-site. Fill Manual Item Code below to continue testing stock engine.</div>
                <?php endif ?>

                <form method="post" action="<?= site_url('inventory/stock-adjustment') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3"><label class="form-label">Reference No</label><input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no', 'ADJ-' . date('Ymd-His'))) ?>"></div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select name="warehouse_id" id="warehouseSelect" class="form-select" required>
                                <option value="">Select / search Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <?php $warehouseId = (int) $warehouse['id']; ?>
                                    <option value="<?= $warehouseId ?>" <?= (string) old('warehouse_id') === (string) $warehouseId ? 'selected' : '' ?>><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text">Warehouse is required so stock is posted to a clear storage location.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <select name="location_id" id="locationSelect" class="form-select" required>
                                <option value="__auto__" data-warehouse-id="">Auto Location by Warehouse</option>
                                <?php foreach ($locations as $location): ?>
                                    <?php $locationId = (int) $location['id']; ?>
                                    <option value="<?= $locationId ?>" data-warehouse-id="<?= esc((string) ($location['warehouse_id'] ?? ''), 'attr') ?>" <?= (string) old('location_id') === (string) $locationId ? 'selected' : '' ?>><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text" id="locationHelp">Select Auto Location if unsure; the system will use the first location for the selected warehouse.</div>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">Select Item</label>
                        <select name="item_code" class="form-select" id="itemSelect">
                            <option value="">Manual Item / Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <?php $itemCode = (string) ($item['code'] ?? $item['item_code'] ?? ''); $itemUom = (string) ($item['uom_code'] ?? $item['base_uom_code'] ?? 'PCS'); $itemCost = (string) ($item['purchase_price'] ?? $item['standard_cost'] ?? $item['unit_cost'] ?? $item['avg_cost'] ?? '0'); ?>
                                <option value="<?= esc($itemCode) ?>" data-name="<?= esc($item['name'] ?? $item['item_name'] ?? '', 'attr') ?>" data-uom="<?= esc($itemUom, 'attr') ?>" data-cost="<?= esc($itemCost, 'attr') ?>" <?= $selectedItemCode === $itemCode ? 'selected' : '' ?>><?= esc(($itemCode ?: '-') . ' - ' . ($item['name'] ?? $item['item_name'] ?? '-')) ?></option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text">Select from item master so name, UoM, and unit cost can be filled automatically.</div>
                    </div>

                    <div class="mb-3"><label class="form-label">Manual Item Code</label><input type="text" name="manual_item_code" id="manualItemCode" class="form-control" value="<?= esc(old('manual_item_code')) ?>" placeholder="Example: ITEM-001"><div class="form-text">Fill manually only if the item does not exist in item master.</div></div>
                    <div class="mb-3"><label class="form-label">Item Name</label><input type="text" name="item_name" id="itemName" class="form-control" value="<?= esc(old('item_name')) ?>" placeholder="Example: Testing Item"></div>

                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Qty +/- <span class="text-danger">*</span></label><input type="number" step="0.0001" name="qty" id="qtyInput" class="form-control text-end" required value="<?= esc(old('qty', '1')) ?>"><div class="form-text">Positive qty adds stock, negative qty reduces stock.</div></div>
                        <div class="col-md-4 mb-3"><label class="form-label">UoM</label><input type="text" name="uom_code" id="uomCode" class="form-control" value="<?= esc(old('uom_code', 'PCS')) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Unit Cost</label><input type="number" step="0.000001" name="unit_cost" id="unitCost" class="form-control text-end" value="<?= esc(old('unit_cost', '0')) ?>"><div class="form-text">Required &gt; 0 when adding stock.</div></div>
                    </div>

                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea></div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Post this stock adjustment?')"><i class="bx bx-save me-1"></i> Post Adjustment</button>
                        <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
<?php endif ?>
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Recent Stock Movements<?= $contextItemCodes !== [] ? ' for SO Items' : '' ?></h4>
                <div class="table-responsive">
                    <table class="table table-sm table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Item</th><th>Dir</th><th class="text-end">Qty</th><th>Ref</th><th>GL</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentMovements as $movement): ?>
                            <tr>
                                <td><?= esc($movement['movement_date'] ?? '-') ?></td>
                                <td><?= esc($movement['movement_type'] ?? '-') ?></td>
                                <td><div class="fw-semibold"><?= esc($movement['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($movement['item_name'] ?? '-') ?></small></td>
                                <td><span class="badge bg-<?= ($movement['direction'] ?? '') === 'in' ? 'success' : 'danger' ?>"><?= esc($movement['direction'] ?? '-') ?></span></td>
                                <td class="text-end"><?= esc(number_format((float) ($movement['qty'] ?? 0), 4)) ?></td>
                                <td><?= esc($movement['reference_no'] ?? '-') ?></td>
                                <td><?= ! empty($movement['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $movement['gl_entry_id']) . '">#' . esc($movement['gl_entry_id']) . '</a>' : '-' ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($recentMovements === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No stock movement yet.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php if (! $isSoContext): ?>
    </div>
</div>
<?php endif ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const warehouseSelect = document.getElementById('warehouseSelect');
    const locationSelect = document.getElementById('locationSelect');
    const locationHelp = document.getElementById('locationHelp');
    const itemSelect = document.getElementById('itemSelect');
    const manualItemCode = document.getElementById('manualItemCode');
    const itemName = document.getElementById('itemName');
    const uomCode = document.getElementById('uomCode');
    const unitCost = document.getElementById('unitCost');
    const bulkQtyTotal = document.getElementById('bulkQtyTotal');

    function refreshSelect2(select) {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && window.jQuery(select).data('select2')) {
            window.jQuery(select).trigger('change.select2');
        }
    }

    function autoSelectLocation() {
        if (!warehouseSelect || !locationSelect) return;
        const warehouseId = String(warehouseSelect.value || '');
        if (!warehouseId) {
            locationSelect.value = '__auto__';
            if (locationHelp) locationHelp.textContent = 'Select warehouse first so the location can be resolved.';
            refreshSelect2(locationSelect);
            return;
        }

        const current = locationSelect.options[locationSelect.selectedIndex];
        if (current && current.value && current.value !== '__auto__' && String(current.dataset.warehouseId || '') === warehouseId) {
            if (locationHelp) locationHelp.textContent = 'Location is valid for the selected warehouse.';
            return;
        }

        let firstMatchValue = '';
        Array.from(locationSelect.options).forEach(function (option) {
            if (!firstMatchValue && option.value && option.value !== '__auto__' && String(option.dataset.warehouseId || '') === warehouseId) firstMatchValue = option.value;
        });

        if (firstMatchValue) {
            locationSelect.value = firstMatchValue;
            if (locationHelp) locationHelp.textContent = 'Location was selected automatically based on the warehouse. You can change it if needed.';
        } else {
            locationSelect.value = '__auto__';
            if (locationHelp) locationHelp.textContent = 'No location exists for this warehouse. The system will use/create MAIN Location when posting.';
        }
        refreshSelect2(locationSelect);
    }

    function fillItemFields() {
        if (!itemSelect || !manualItemCode || !itemName || !uomCode || !unitCost) return;
        const option = itemSelect.options[itemSelect.selectedIndex];
        if (option && option.value) {
            manualItemCode.value = option.value;
            itemName.value = option.dataset.name || '';
            uomCode.value = option.dataset.uom || 'PCS';
            if (option.dataset.cost && Number(option.dataset.cost) > 0) unitCost.value = option.dataset.cost;
        }
    }

    function recalcBulkQty() {
        if (!bulkQtyTotal) return;
        let total = 0;
        document.querySelectorAll('.bulk-qty').forEach(function (input) { total += Number(input.value || 0) || 0; });
        bulkQtyTotal.textContent = total.toLocaleString(undefined, {minimumFractionDigits: 4, maximumFractionDigits: 4});
    }

    if (warehouseSelect) warehouseSelect.addEventListener('change', autoSelectLocation);
    if (itemSelect) itemSelect.addEventListener('change', fillItemFields);
    document.querySelectorAll('.bulk-qty').forEach(function (input) { input.addEventListener('input', recalcBulkQty); });

    autoSelectLocation();
    fillItemFields();
    recalcBulkQty();
});
</script>
<?= $this->endSection() ?>
