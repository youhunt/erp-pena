<div class="mb-3">
    <label class="form-label">Reference No</label>
    <input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no', $referenceNo ?? 'INV-' . date('Ymd-His'))) ?>">
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Warehouse</label>
        <select name="warehouse_id" class="form-select">
            <option value="">No Warehouse</option>
            <?php foreach ($warehouses as $warehouse): ?>
                <option value="<?= (int) $warehouse['id'] ?>"><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Location</label>
        <select name="location_id" class="form-select">
            <option value="">No Location</option>
            <?php foreach ($locations as $location): ?>
                <option value="<?= (int) $location['id'] ?>"><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
            <?php endforeach ?>
        </select>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Select Item</label>
    <select name="item_code" class="form-select inventory-item-select">
        <option value="">Manual Item / Select Item</option>
        <?php foreach ($items as $item): ?>
            <?php $code = (string) ($item['item_code'] ?? $item['code'] ?? ''); ?>
            <option value="<?= esc($code) ?>" data-name="<?= esc($item['item_name'] ?? $item['name'] ?? '') ?>" data-uom="<?= esc($item['stockuom'] ?? $item['uom_code'] ?? 'PCS') ?>" data-cost="<?= esc((string) ($item['item_price'] ?? $item['standard_cost'] ?? 0)) ?>">
                <?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '-')) ?>
            </option>
        <?php endforeach ?>
    </select>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Manual Item Code</label>
        <input type="text" name="manual_item_code" class="form-control inventory-manual-code" value="<?= esc(old('manual_item_code')) ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Item Name</label>
        <input type="text" name="item_name" class="form-control inventory-item-name" value="<?= esc(old('item_name')) ?>">
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">UoM</label>
        <input type="text" name="uom_code" class="form-control inventory-uom-code" value="<?= esc(old('uom_code', 'PCS')) ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Unit Cost</label>
        <input type="number" step="0.000001" name="unit_cost" class="form-control text-end inventory-unit-cost" value="<?= esc(old('unit_cost', '0')) ?>">
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Batch No</label>
    <input type="text" name="batch_no" class="form-control inventory-batch-no" value="<?= esc(old('batch_no')) ?>" placeholder="Optional batch / lot no">
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.inventory-item-select').forEach(function (select) {
        select.addEventListener('change', function () {
            const option = select.options[select.selectedIndex];
            const form = select.closest('form');
            if (!form || !option || !option.value) return;

            form.querySelector('.inventory-manual-code').value = '';
            form.querySelector('.inventory-item-name').value = option.dataset.name || '';
            form.querySelector('.inventory-uom-code').value = option.dataset.uom || 'PCS';
            form.querySelector('.inventory-unit-cost').value = option.dataset.cost || '0';
        });
    });
});
</script>
