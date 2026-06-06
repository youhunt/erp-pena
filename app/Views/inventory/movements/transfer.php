<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Inventory Transfer</h4>
                <p class="text-muted mb-4">Move stock between warehouse/location in the active company and site.</p>

                <form method="post" action="<?= site_url('inventory/transfers') ?>">
                    <?= csrf_field() ?>

                    <?= view('inventory/movements/partials/item_location_fields', [
                        'items' => $items,
                        'warehouses' => $warehouses,
                        'locations' => $locations,
                        'referenceNo' => 'TRF-' . date('Ymd-His'),
                    ]) ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">To Warehouse</label>
                            <select name="to_warehouse_id" class="form-select">
                                <option value="">No Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?= (int) $warehouse['id'] ?>"><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">To Location</label>
                            <select name="to_location_id" class="form-select">
                                <option value="">No Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= (int) $location['id'] ?>"><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Qty</label>
                        <input type="number" step="0.0001" name="qty" class="form-control text-end" required value="<?= esc(old('qty', '1')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit" onclick="return confirm('Post this inventory transfer?')">
                            <i class="bx bx-transfer me-1"></i> Post Transfer
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
<?= $this->endSection() ?>
