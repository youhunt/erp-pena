<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-8">
        <form method="post" action="<?= site_url('inventory/in-out') ?>">
            <?= csrf_field() ?>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
                        <div>
                            <h4 class="card-title mb-1">Inventory In Out</h4>
                            <p class="text-muted mb-0">Post multi-line manual inventory receipt or issue into stock ledger.</p>
                        </div>
                        <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-light">Stock Balance</a>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Reference No</label>
                            <input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no', 'IO-' . date('Ymd-His'))) ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Movement Date</label>
                            <input type="datetime-local" name="movement_date" class="form-control" value="<?= esc(old('movement_date', date('Y-m-d\TH:i'))) ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Direction</label>
                            <select name="direction" class="form-select" required>
                                <option value="in" <?= old('direction', 'in') === 'in' ? 'selected' : '' ?>>Stock In</option>
                                <option value="out" <?= old('direction') === 'out' ? 'selected' : '' ?>>Stock Out</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Warehouse</label>
                            <select name="warehouse_id" class="form-select inventory-warehouse">
                                <option value="">No Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?= (int) $warehouse['id'] ?>" <?= old('warehouse_id') == (string) $warehouse['id'] ? 'selected' : '' ?>><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Location</label>
                            <select name="location_id" class="form-select inventory-location">
                                <option value="">No Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= (int) $location['id'] ?>" data-warehouse-id="<?= esc((string) ($location['warehouse_id'] ?? '')) ?>" <?= old('location_id') == (string) $location['id'] ? 'selected' : '' ?>><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-9 mb-3">
                            <label class="form-label">Header Notes</label>
                            <input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <h4 class="card-title mb-0">Lines</h4>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addInOutLine">
                            <i class="bx bx-plus me-1"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-nowrap align-middle mb-0" id="inOutLinesTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 48px;">#</th>
                                    <th style="min-width: 220px;">Item</th>
                                    <th style="min-width: 150px;">Manual Code</th>
                                    <th style="min-width: 180px;">Item Name</th>
                                    <th style="min-width: 120px;">Batch</th>
                                    <th style="width: 120px;" class="text-end">Qty</th>
                                    <th style="width: 100px;">UoM</th>
                                    <th style="width: 140px;" class="text-end">Unit Cost</th>
                                    <th style="min-width: 160px;">Line Notes</th>
                                    <th style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $oldQtys = (array) old('line_qty', ['1']);
                                    $rowCount = max(1, count($oldQtys));
                                ?>
                                <?php for ($i = 0; $i < $rowCount; $i++): ?>
                                    <tr class="inout-line-row">
                                        <td class="line-number text-muted"><?= $i + 1 ?></td>
                                        <td>
                                            <select name="line_item_code[]" class="form-select inventory-item-select">
                                                <option value="">Manual Item / Select Item</option>
                                                <?php foreach ($items as $item): ?>
                                                    <?php $code = (string) ($item['item_code'] ?? $item['code'] ?? ''); ?>
                                                    <option value="<?= esc($code) ?>" data-name="<?= esc($item['item_name'] ?? $item['name'] ?? '') ?>" data-uom="<?= esc($item['stockuom'] ?? $item['uom_code'] ?? 'PCS') ?>" data-cost="<?= esc((string) ($item['item_price'] ?? $item['standard_cost'] ?? 0)) ?>" <?= old('line_item_code.' . $i) === $code ? 'selected' : '' ?>>
                                                        <?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '-')) ?>
                                                    </option>
                                                <?php endforeach ?>
                                            </select>
                                        </td>
                                        <td><input type="text" name="line_manual_item_code[]" class="form-control inventory-manual-code" value="<?= esc(old('line_manual_item_code.' . $i)) ?>"></td>
                                        <td><input type="text" name="line_item_name[]" class="form-control inventory-item-name" value="<?= esc(old('line_item_name.' . $i)) ?>"></td>
                                        <td><input type="text" name="line_batch_no[]" class="form-control" value="<?= esc(old('line_batch_no.' . $i)) ?>"></td>
                                        <td><input type="number" step="0.0001" name="line_qty[]" class="form-control text-end" required value="<?= esc(old('line_qty.' . $i, $i === 0 ? '1' : '')) ?>"></td>
                                        <td><input type="text" name="line_uom_code[]" class="form-control inventory-uom-code" value="<?= esc(old('line_uom_code.' . $i, 'PCS')) ?>"></td>
                                        <td><input type="number" step="0.000001" name="line_unit_cost[]" class="form-control text-end inventory-unit-cost" value="<?= esc(old('line_unit_cost.' . $i, '0')) ?>"></td>
                                        <td><input type="text" name="line_notes[]" class="form-control" value="<?= esc(old('line_notes.' . $i)) ?>"></td>
                                        <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-line"><i class="bx bx-trash"></i></button></td>
                                    </tr>
                                <?php endfor ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit" onclick="return confirm('Post this inventory movement document?')">
                            <i class="bx bx-save me-1"></i> Post Movement
                        </button>
                        <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-xl-4">
        <?= view('inventory/movements/partials/recent_documents', ['recentDocuments' => $recentDocuments]) ?>
        <?= view('inventory/movements/partials/recent_movements', ['recentMovements' => $recentMovements]) ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const warehouse = document.querySelector('.inventory-warehouse');
    const location = document.querySelector('.inventory-location');
    const table = document.getElementById('inOutLinesTable');
    const addButton = document.getElementById('addInOutLine');

    const renumber = function () {
        table.querySelectorAll('tbody tr').forEach(function (row, index) {
            row.querySelector('.line-number').textContent = String(index + 1);
            row.querySelector('.remove-line').disabled = table.querySelectorAll('tbody tr').length === 1;
        });
    };

    const filterLocations = function () {
        if (!warehouse || !location) return;
        const selectedWarehouse = warehouse.value || '';
        location.querySelectorAll('option').forEach(function (option) {
            if (!option.value) return;
            const optionWarehouse = option.dataset.warehouseId || '';
            const visible = selectedWarehouse === '' || optionWarehouse === '' || optionWarehouse === selectedWarehouse;
            option.hidden = !visible;
            if (!visible && option.selected) location.value = '';
        });
    };

    const bindRow = function (row) {
        const select = row.querySelector('.inventory-item-select');
        select.addEventListener('change', function () {
            const option = select.options[select.selectedIndex];
            if (!option || !option.value) return;
            row.querySelector('.inventory-manual-code').value = '';
            row.querySelector('.inventory-item-name').value = option.dataset.name || '';
            row.querySelector('.inventory-uom-code').value = option.dataset.uom || 'PCS';
            row.querySelector('.inventory-unit-cost').value = option.dataset.cost || '0';
        });

        row.querySelector('.remove-line').addEventListener('click', function () {
            if (table.querySelectorAll('tbody tr').length <= 1) return;
            row.remove();
            renumber();
        });
    };

    if (warehouse) warehouse.addEventListener('change', filterLocations);
    filterLocations();
    table.querySelectorAll('tbody tr').forEach(bindRow);
    renumber();

    addButton.addEventListener('click', function () {
        const source = table.querySelector('tbody tr:last-child');
        const row = source.cloneNode(true);
        row.querySelectorAll('.select2-container').forEach(function (container) {
            container.remove();
        });
        row.querySelectorAll('input').forEach(function (input) {
            input.value = input.name === 'line_qty[]' ? '1' : (input.name === 'line_uom_code[]' ? 'PCS' : (input.name === 'line_unit_cost[]' ? '0' : ''));
        });
        row.querySelectorAll('select').forEach(function (select) {
            select.classList.remove('select2-hidden-accessible');
            select.removeAttribute('data-select2-id');
            select.removeAttribute('aria-hidden');
            select.removeAttribute('tabindex');
            select.style.display = '';
            select.selectedIndex = 0;
            select.querySelectorAll('option').forEach(function (option) {
                option.removeAttribute('data-select2-id');
            });
        });
        table.querySelector('tbody').appendChild(row);
        bindRow(row);
        if (window.PenaSelect) {
            window.PenaSelect.init(row);
        }
        renumber();
    });
});
</script>
<?= $this->endSection() ?>
