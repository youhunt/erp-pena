<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Inventory Stock Opname</h4>
                <p class="text-muted mb-4">Post physical count variance against current stock ledger quantity.</p>

                <form method="post" action="<?= site_url('inventory/stock-opname') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Select Stock Balance</label>
                        <select class="form-select" id="balanceSelect">
                            <option value="">Manual / Select Balance</option>
                            <?php foreach ($balances as $balance): ?>
                                <option
                                    value="<?= esc($balance['item_code'] ?? '') ?>"
                                    data-item="<?= esc($balance['item_code'] ?? '') ?>"
                                    data-uom="<?= esc($balance['uom_code'] ?? 'PCS') ?>"
                                    data-warehouse-id="<?= esc((string) ($balance['warehouse_id'] ?? '')) ?>"
                                    data-location-id="<?= esc((string) ($balance['location_id'] ?? '')) ?>"
                                    data-batch-no="<?= esc((string) ($balance['batch_no'] ?? '')) ?>"
                                    data-system-qty="<?= esc((string) ($balance['qty_on_hand'] ?? 0)) ?>"
                                    data-cost="<?= esc((string) ($balance['avg_cost'] ?? 0)) ?>"
                                >
                                    <?= esc(($balance['item_code'] ?? '-') . ' / ' . ($balance['warehouse_code'] ?? '-') . ' / ' . ($balance['location_code'] ?? '-') . ' / Batch ' . (($balance['batch_no'] ?? '') !== '' ? $balance['batch_no'] : '-') . ' / Qty ' . number_format((float) ($balance['qty_on_hand'] ?? 0), 4)) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <?= view('inventory/movements/partials/item_location_fields', [
                        'items' => $items,
                        'warehouses' => $warehouses,
                        'locations' => $locations,
                        'referenceNo' => 'OPN-' . date('Ymd-His'),
                    ]) ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">System Qty</label>
                            <input type="number" step="0.0001" name="system_qty" id="systemQty" class="form-control text-end" readonly value="<?= esc(old('system_qty', '0')) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Counted Qty</label>
                            <input type="number" step="0.0001" name="counted_qty" id="countedQty" class="form-control text-end" required value="<?= esc(old('counted_qty', '0')) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit" onclick="return confirm('Post stock opname variance?')">
                            <i class="bx bx-check-square me-1"></i> Post Opname
                        </button>
                        <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <?= view('inventory/movements/partials/recent_movements', ['recentMovements' => $recentMovements]) ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const balanceSelect = document.getElementById('balanceSelect');
    balanceSelect.addEventListener('change', function () {
        const option = balanceSelect.options[balanceSelect.selectedIndex];
        if (!option || !option.value) return;

        document.querySelector('.inventory-manual-code').value = option.dataset.item || '';
        document.querySelector('.inventory-uom-code').value = option.dataset.uom || 'PCS';
        document.querySelector('.inventory-unit-cost').value = option.dataset.cost || '0';
        document.getElementById('systemQty').value = option.dataset.systemQty || '0';
        document.getElementById('countedQty').value = option.dataset.systemQty || '0';

        const warehouse = document.querySelector('[name="warehouse_id"]');
        const location = document.querySelector('[name="location_id"]');
        const batchNo = document.querySelector('[name="batch_no"]');
        if (warehouse) {
            warehouse.value = option.dataset.warehouseId || '';
            warehouse.dispatchEvent(new Event('change'));
        }
        if (location) location.value = option.dataset.locationId || '';
        if (batchNo) batchNo.value = option.dataset.batchNo || '';
    });
});
</script>
<?= $this->endSection() ?>
