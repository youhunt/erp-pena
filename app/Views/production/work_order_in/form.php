<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('production/work-order-in') ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="card-title mb-1">Post Work Order In</h4>
                    <p class="text-muted mb-0">Receive finished good from production into inventory.</p>
                </div>
                <a href="<?= site_url('production/work-order-in') ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">WO No</label><input type="text" name="wo_no" class="form-control" required value="<?= esc(old('wo_no', 'WOI-' . date('Ymd-His'))) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">WO Date</label><input type="date" name="wo_date" class="form-control" required value="<?= esc(old('wo_date', date('Y-m-d'))) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-select"><option value="">No Warehouse</option><?php foreach ($warehouses as $warehouse): ?><option value="<?= (int) $warehouse['id'] ?>"><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option><?php endforeach ?></select></div>
                <div class="col-md-3 mb-3"><label class="form-label">Location</label><select name="location_id" class="form-select"><option value="">No Location</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option><?php endforeach ?></select></div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Finished Good Item</label>
                    <select name="finished_item_id" id="finishedItem" class="form-select">
                        <option value="">Manual Item / Select Item</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" data-code="<?= esc($item['item_code'] ?? $item['code'] ?? '') ?>" data-name="<?= esc($item['item_name'] ?? $item['name'] ?? '') ?>" data-uom="<?= esc($item['stockuom'] ?? $item['uom_code'] ?? 'PCS') ?>" data-cost="<?= esc((string) ($item['item_price'] ?? 0)) ?>">
                                <?= esc(($item['item_code'] ?? $item['code'] ?? '-') . ' - ' . ($item['item_name'] ?? $item['name'] ?? '-')) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3"><label class="form-label">Item Code</label><input type="text" name="finished_item_code" id="itemCode" class="form-control" required value="<?= esc(old('finished_item_code')) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">UoM</label><input type="text" name="uom_code" id="uomCode" class="form-control" value="<?= esc(old('uom_code', 'PCS')) ?>"></div>
            </div>

            <div class="mb-3"><label class="form-label">Item Name</label><input type="text" name="finished_item_name" id="itemName" class="form-control" value="<?= esc(old('finished_item_name')) ?>"></div>

            <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">Plan Qty</label><input type="number" step="0.0001" name="qty_plan" class="form-control text-end" value="<?= esc(old('qty_plan', '0')) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Good Qty</label><input type="number" step="0.0001" name="qty_good" class="form-control text-end" required value="<?= esc(old('qty_good', '1')) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Reject Qty</label><input type="number" step="0.0001" name="qty_reject" class="form-control text-end" value="<?= esc(old('qty_reject', '0')) ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Unit Cost</label><input type="number" step="0.000001" name="unit_cost" id="unitCost" class="form-control text-end" value="<?= esc(old('unit_cost', '0')) ?>"></div>
            </div>

            <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea></div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Post Work Order In and increase finished good stock?')"><i class="bx bx-save me-1"></i> Post Work Order In</button>
                <a href="<?= site_url('production/work-order-in') ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const item = document.getElementById('finishedItem');
    item.addEventListener('change', function () {
        const option = item.options[item.selectedIndex];
        document.getElementById('itemCode').value = option.dataset.code || '';
        document.getElementById('itemName').value = option.dataset.name || '';
        document.getElementById('uomCode').value = option.dataset.uom || 'PCS';
        document.getElementById('unitCost').value = option.dataset.cost || '0';
    });
});
</script>
<?= $this->endSection() ?>
