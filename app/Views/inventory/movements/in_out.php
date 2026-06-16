<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Inventory In Out</h4>
                <p class="text-muted mb-4">Post manual inventory receipt or issue into stock ledger.</p>

                <form method="post" action="<?= site_url('inventory/in-out') ?>">
                    <?= csrf_field() ?>

                    <?= view('inventory/movements/partials/item_location_fields', [
                        'items' => $items,
                        'warehouses' => $warehouses,
                        'locations' => $locations,
                        'referenceNo' => 'IO-' . date('Ymd-His'),
                    ]) ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Direction</label>
                            <select name="direction" class="form-select" required>
                                <option value="in">Stock In</option>
                                <option value="out">Stock Out</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qty</label>
                            <input type="number" step="0.0001" name="qty" class="form-control text-end" required value="<?= esc(old('qty', '1')) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit" onclick="return confirm('Post this inventory movement?')">
                            <i class="bx bx-save me-1"></i> Post Movement
                        </button>
                        <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <?= view('inventory/movements/partials/recent_documents', ['recentDocuments' => $recentDocuments]) ?>
        <?= view('inventory/movements/partials/recent_movements', ['recentMovements' => $recentMovements]) ?>
    </div>
</div>
<?= $this->endSection() ?>
