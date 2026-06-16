<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-8">
        <form method="post" action="<?= site_url('inventory/stock-opname') ?>">
            <?= csrf_field() ?>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
                        <div>
                            <h4 class="card-title mb-1">Inventory Stock Opname</h4>
                            <p class="text-muted mb-0">Post physical count variance for multiple stock balances in one document.</p>
                        </div>
                        <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-light">Stock Balance</a>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Opname No</label>
                            <input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no', 'OPN-' . date('Ymd-His'))) ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Opname Date</label>
                            <input type="datetime-local" name="movement_date" class="form-control" value="<?= esc(old('movement_date', date('Y-m-d\TH:i'))) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Header Notes</label>
                            <input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <h4 class="card-title mb-0">Count Lines</h4>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addOpnameLine">
                            <i class="bx bx-plus me-1"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-nowrap align-middle mb-0" id="opnameLinesTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 48px;">#</th>
                                    <th style="min-width: 320px;">Stock Balance</th>
                                    <th style="min-width: 130px;">Batch</th>
                                    <th style="width: 130px;" class="text-end">System Qty</th>
                                    <th style="width: 130px;" class="text-end">Counted Qty</th>
                                    <th style="width: 100px;">UoM</th>
                                    <th style="width: 130px;" class="text-end">Unit Cost</th>
                                    <th style="min-width: 160px;">Line Notes</th>
                                    <th style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $oldCountedQtys = (array) old('line_counted_qty', ['']);
                                    $rowCount = max(1, count($oldCountedQtys));
                                ?>
                                <?php for ($i = 0; $i < $rowCount; $i++): ?>
                                    <tr class="opname-line-row">
                                        <td class="line-number text-muted"><?= $i + 1 ?></td>
                                        <td>
                                            <select name="line_balance_key[]" class="form-select stock-balance-select">
                                                <option value="">Select Stock Balance</option>
                                                <?php foreach ($balances as $balance): ?>
                                                    <?php
                                                        $itemCode = (string) ($balance['item_code'] ?? '');
                                                        $warehouseId = (string) ($balance['warehouse_id'] ?? '');
                                                        $locationId = (string) ($balance['location_id'] ?? '');
                                                        $batchNo = (string) ($balance['batch_no'] ?? '');
                                                        $balanceKey = $itemCode . '|' . $warehouseId . '|' . $locationId . '|' . $batchNo;
                                                    ?>
                                                    <option
                                                        value="<?= esc($balanceKey) ?>"
                                                        data-item="<?= esc($itemCode) ?>"
                                                        data-name="<?= esc($balance['item_name'] ?? $itemCode) ?>"
                                                        data-uom="<?= esc($balance['uom_code'] ?? 'PCS') ?>"
                                                        data-warehouse-id="<?= esc($warehouseId) ?>"
                                                        data-location-id="<?= esc($locationId) ?>"
                                                        data-warehouse-code="<?= esc((string) ($balance['warehouse_code'] ?? '')) ?>"
                                                        data-location-code="<?= esc((string) ($balance['location_code'] ?? '')) ?>"
                                                        data-batch-no="<?= esc($batchNo) ?>"
                                                        data-system-qty="<?= esc((string) ($balance['qty_on_hand'] ?? 0)) ?>"
                                                        data-cost="<?= esc((string) ($balance['avg_cost'] ?? 0)) ?>"
                                                        <?= old('line_balance_key.' . $i) === $balanceKey ? 'selected' : '' ?>
                                                    >
                                                        <?= esc($itemCode . ' - ' . ($balance['item_name'] ?? $itemCode) . ' / ' . ($balance['warehouse_code'] ?? '-') . ' / ' . ($balance['location_code'] ?? '-') . ' / Batch ' . ($batchNo !== '' ? $batchNo : '-') . ' / Qty ' . number_format((float) ($balance['qty_on_hand'] ?? 0), 4)) ?>
                                                    </option>
                                                <?php endforeach ?>
                                            </select>
                                            <input type="hidden" name="line_item_code[]" class="line-item-code" value="<?= esc(old('line_item_code.' . $i)) ?>">
                                            <input type="hidden" name="line_item_name[]" class="line-item-name" value="<?= esc(old('line_item_name.' . $i)) ?>">
                                            <input type="hidden" name="line_warehouse_id[]" class="line-warehouse-id" value="<?= esc(old('line_warehouse_id.' . $i)) ?>">
                                            <input type="hidden" name="line_location_id[]" class="line-location-id" value="<?= esc(old('line_location_id.' . $i)) ?>">
                                        </td>
                                        <td><input type="text" name="line_batch_no[]" class="form-control line-batch-no" readonly value="<?= esc(old('line_batch_no.' . $i)) ?>"></td>
                                        <td><input type="number" step="0.0001" name="line_system_qty[]" class="form-control text-end line-system-qty" readonly value="<?= esc(old('line_system_qty.' . $i, '0')) ?>"></td>
                                        <td><input type="number" step="0.0001" name="line_counted_qty[]" class="form-control text-end line-counted-qty" value="<?= esc(old('line_counted_qty.' . $i)) ?>"></td>
                                        <td><input type="text" name="line_uom_code[]" class="form-control line-uom-code" readonly value="<?= esc(old('line_uom_code.' . $i, 'PCS')) ?>"></td>
                                        <td><input type="number" step="0.000001" name="line_unit_cost[]" class="form-control text-end line-unit-cost" value="<?= esc(old('line_unit_cost.' . $i, '0')) ?>"></td>
                                        <td><input type="text" name="line_notes[]" class="form-control" value="<?= esc(old('line_notes.' . $i)) ?>"></td>
                                        <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-line"><i class="bx bx-trash"></i></button></td>
                                    </tr>
                                <?php endfor ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit" onclick="return confirm('Post this stock opname document?')">
                            <i class="bx bx-check-square me-1"></i> Post Opname
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
    const table = document.getElementById('opnameLinesTable');
    const addButton = document.getElementById('addOpnameLine');

    const renumber = function () {
        table.querySelectorAll('tbody tr').forEach(function (row, index) {
            row.querySelector('.line-number').textContent = String(index + 1);
            row.querySelector('.remove-line').disabled = table.querySelectorAll('tbody tr').length === 1;
        });
    };

    const applyBalance = function (row) {
        const select = row.querySelector('.stock-balance-select');
        const option = select.options[select.selectedIndex];
        if (!option || !option.value) return;

        row.querySelector('.line-item-code').value = option.dataset.item || '';
        row.querySelector('.line-item-name').value = option.dataset.name || '';
        row.querySelector('.line-warehouse-id').value = option.dataset.warehouseId || '';
        row.querySelector('.line-location-id').value = option.dataset.locationId || '';
        row.querySelector('.line-batch-no').value = option.dataset.batchNo || '';
        row.querySelector('.line-system-qty').value = option.dataset.systemQty || '0';
        row.querySelector('.line-counted-qty').value = option.dataset.systemQty || '0';
        row.querySelector('.line-uom-code').value = option.dataset.uom || 'PCS';
        row.querySelector('.line-unit-cost').value = option.dataset.cost || '0';
    };

    const bindRow = function (row) {
        row.querySelector('.stock-balance-select').addEventListener('change', function () {
            applyBalance(row);
        });

        row.querySelector('.remove-line').addEventListener('click', function () {
            if (table.querySelectorAll('tbody tr').length <= 1) return;
            row.remove();
            renumber();
        });
    };

    table.querySelectorAll('tbody tr').forEach(function (row) {
        bindRow(row);
        if (row.querySelector('.stock-balance-select').value && !row.querySelector('.line-item-code').value) {
            applyBalance(row);
        }
    });
    renumber();

    addButton.addEventListener('click', function () {
        const source = table.querySelector('tbody tr:last-child');
        const row = source.cloneNode(true);
        row.querySelectorAll('.select2-container').forEach(function (container) {
            container.remove();
        });
        row.querySelectorAll('input').forEach(function (input) {
            input.value = input.name === 'line_system_qty[]' ? '0' : (input.name === 'line_uom_code[]' ? 'PCS' : (input.name === 'line_unit_cost[]' ? '0' : ''));
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
