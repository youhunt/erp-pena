<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Stock Adjustment</h4>
                <p class="text-muted mb-4">Post stock correction into inventory ledger and balance.</p>

                <form method="post" action="<?= site_url('inventory/stock-adjustment') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Reference No</label>
                        <input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no', 'ADJ-' . date('Ymd-His'))) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Warehouse</label>
                        <select name="warehouse_id" class="form-select">
                            <option value="">No Warehouse</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?= (int) $warehouse['id'] ?>"><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select name="location_id" class="form-select">
                            <option value="">No Location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= (int) $location['id'] ?>"><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <select name="item_code" class="form-select" id="itemSelect" required>
                            <option value="">Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= esc($item['code'] ?? '') ?>" data-name="<?= esc($item['name'] ?? '') ?>" data-uom="<?= esc($item['uom_code'] ?? $item['base_uom_code'] ?? 'PCS') ?>">
                                    <?= esc(($item['code'] ?? '-') . ' - ' . ($item['name'] ?? '-')) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="item_name" id="itemName" class="form-control" value="<?= esc(old('item_name')) ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Qty +/-</label>
                            <input type="number" step="0.0001" name="qty" class="form-control text-end" required value="<?= esc(old('qty', '1')) ?>">
                            <div class="form-text">Positive adds stock, negative reduces stock.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">UoM</label>
                            <input type="text" name="uom_code" id="uomCode" class="form-control" value="<?= esc(old('uom_code', 'PCS')) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit Cost</label>
                            <input type="number" step="0.000001" name="unit_cost" class="form-control text-end" value="<?= esc(old('unit_cost', '0')) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Post this stock adjustment?')">
                            <i class="bx bx-save me-1"></i> Post Adjustment
                        </button>
                        <a href="<?= site_url('dashboard') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Recent Stock Movements</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Item</th>
                                <th>Dir</th>
                                <th class="text-end">Qty</th>
                                <th>Ref</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentMovements as $movement): ?>
                            <tr>
                                <td><?= esc($movement['movement_date'] ?? '-') ?></td>
                                <td><?= esc($movement['movement_type'] ?? '-') ?></td>
                                <td>
                                    <div class="fw-semibold"><?= esc($movement['item_code'] ?? '-') ?></div>
                                    <small class="text-muted"><?= esc($movement['item_name'] ?? '-') ?></small>
                                </td>
                                <td><span class="badge bg-<?= ($movement['direction'] ?? '') === 'in' ? 'success' : 'danger' ?>"><?= esc($movement['direction'] ?? '-') ?></span></td>
                                <td class="text-end"><?= esc(number_format((float) ($movement['qty'] ?? 0), 4)) ?></td>
                                <td><?= esc($movement['reference_no'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach ?>

                        <?php if ($recentMovements === []): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No stock movement yet.</td></tr>
                        <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemSelect = document.getElementById('itemSelect');
    const itemName = document.getElementById('itemName');
    const uomCode = document.getElementById('uomCode');

    itemSelect.addEventListener('change', function () {
        const option = itemSelect.options[itemSelect.selectedIndex];
        if (option) {
            itemName.value = option.dataset.name || '';
            uomCode.value = option.dataset.uom || 'PCS';
        }
    });
});
</script>
<?= $this->endSection() ?>
