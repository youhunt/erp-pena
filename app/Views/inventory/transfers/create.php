<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">New Inventory Transfer</h4>
                <p class="text-muted mb-0">Create a transfer draft first, then submit or post it from the detail screen.</p>
            </div>
            <a href="<?= site_url('inventory/transfers') ?>" class="btn btn-light">Back</a>
        </div>

        <form method="post" action="<?= site_url('inventory/transfers') ?>">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Transfer No</label>
                    <input type="text" name="transfer_no" class="form-control" value="<?= esc(old('transfer_no', $transferNo)) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Transfer Date</label>
                    <input type="date" name="transfer_date" class="form-control" value="<?= esc(old('transfer_date', date('Y-m-d'))) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <input type="text" class="form-control" value="Draft on Save" readonly>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">From Warehouse</label>
                    <select name="from_warehouse_id" class="form-select">
                        <option value="">No Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= (int) $warehouse['id'] ?>" <?= old('from_warehouse_id') == $warehouse['id'] ? 'selected' : '' ?>><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">From Location</label>
                    <select name="from_location_id" class="form-select">
                        <option value="">No Location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" <?= old('from_location_id') == $location['id'] ? 'selected' : '' ?>><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">To Warehouse</label>
                    <select name="to_warehouse_id" class="form-select">
                        <option value="">No Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= (int) $warehouse['id'] ?>" <?= old('to_warehouse_id') == $warehouse['id'] ? 'selected' : '' ?>><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">To Location</label>
                    <select name="to_location_id" class="form-select">
                        <option value="">No Location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" <?= old('to_location_id') == $location['id'] ? 'selected' : '' ?>><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"><?= esc(old('notes')) ?></textarea>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-bordered align-middle" id="transfer-lines">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:220px">Item Code</th>
                            <th style="min-width:140px">Batch No</th>
                            <th style="min-width:120px">UoM</th>
                            <th style="min-width:120px" class="text-end">Qty</th>
                            <th style="min-width:140px" class="text-end">Unit Cost</th>
                            <th style="min-width:180px">Line Notes</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <tr>
                                <td>
                                    <input list="item-options" name="item_code[]" class="form-control" value="<?= esc(old('item_code.' . $i)) ?>" placeholder="ITEM001">
                                </td>
                                <td><input type="text" name="batch_no[]" class="form-control" value="<?= esc(old('batch_no.' . $i)) ?>" placeholder="Optional"></td>
                                <td><input type="text" name="uom_code[]" class="form-control" value="<?= esc(old('uom_code.' . $i, 'PCS')) ?>"></td>
                                <td><input type="number" step="0.0001" name="qty[]" class="form-control text-end" value="<?= esc(old('qty.' . $i, $i === 0 ? '1' : '')) ?>"></td>
                                <td><input type="number" step="0.0001" name="unit_cost[]" class="form-control text-end" value="<?= esc(old('unit_cost.' . $i, '0')) ?>"></td>
                                <td><input type="text" name="line_notes[]" class="form-control" value="<?= esc(old('line_notes.' . $i)) ?>"></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">×</button></td>
                            </tr>
                        <?php endfor ?>
                    </tbody>
                </table>
            </div>

            <datalist id="item-options">
                <?php foreach ($items as $item): ?>
                    <option value="<?= esc($item['item_code'] ?? $item['code'] ?? '') ?>"><?= esc($item['item_name'] ?? $item['name'] ?? '') ?></option>
                <?php endforeach ?>
            </datalist>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="addTransferLine()">
                    <i class="bx bx-plus me-1"></i> Add Line
                </button>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Save this inventory transfer as draft?')">
                    <i class="bx bx-save me-1"></i> Save Draft
                </button>
                <a href="<?= site_url('inventory/transfers') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function addTransferLine() {
    const tbody = document.querySelector('#transfer-lines tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input list="item-options" name="item_code[]" class="form-control" placeholder="ITEM001"></td>
        <td><input type="text" name="batch_no[]" class="form-control" placeholder="Optional"></td>
        <td><input type="text" name="uom_code[]" class="form-control" value="PCS"></td>
        <td><input type="number" step="0.0001" name="qty[]" class="form-control text-end"></td>
        <td><input type="number" step="0.0001" name="unit_cost[]" class="form-control text-end" value="0"></td>
        <td><input type="text" name="line_notes[]" class="form-control"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">×</button></td>`;
    tbody.appendChild(row);
}
</script>
<?= $this->endSection() ?>
