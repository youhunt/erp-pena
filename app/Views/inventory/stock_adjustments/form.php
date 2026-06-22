<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Stock Adjustment</h4>
                <p class="text-muted mb-3">Koreksi stok manual. Pilih warehouse dulu, location akan otomatis terfilter.</p>

                <div class="alert alert-info py-2">
                    <strong>Prosedur:</strong> pilih Warehouse → pilih Location → pilih Item → isi Qty dan Unit Cost → Post Adjustment.
                </div>

                <?php if (session('error')): ?>
                    <div class="alert alert-danger"><?= esc(session('error')) ?></div>
                <?php endif ?>
                <?php if (session('message')): ?>
                    <div class="alert alert-success"><?= esc(session('message')) ?></div>
                <?php endif ?>

                <?php if ($items === []): ?>
                    <div class="alert alert-warning">
                        Master item belum ada / belum cocok dengan active company-site. Isi <strong>Manual Item Code</strong> di bawah untuk tetap testing stock engine.
                    </div>
                <?php endif ?>

                <form method="post" action="<?= site_url('inventory/stock-adjustment') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Reference No</label>
                        <input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no', 'ADJ-' . date('Ymd-His'))) ?>">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select name="warehouse_id" id="warehouseSelect" class="form-select" required>
                                <option value="">Pilih / cari Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <?php $warehouseId = (int) $warehouse['id']; ?>
                                    <option value="<?= $warehouseId ?>" <?= (string) old('warehouse_id') === (string) $warehouseId ? 'selected' : '' ?>>
                                        <?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text">Warehouse wajib dipilih agar stok masuk ke lokasi yang jelas.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <select name="location_id" id="locationSelect" class="form-select" required>
                                <option value="">Pilih warehouse dulu</option>
                                <?php foreach ($locations as $location): ?>
                                    <?php $locationId = (int) $location['id']; ?>
                                    <option value="<?= $locationId ?>" data-warehouse-id="<?= esc((string) ($location['warehouse_id'] ?? ''), 'attr') ?>" <?= (string) old('location_id') === (string) $locationId ? 'selected' : '' ?>>
                                        <?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text" id="locationHelp">Location otomatis hanya muncul sesuai warehouse.</div>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">Select Item</label>
                        <select name="item_code" class="form-select" id="itemSelect">
                            <option value="">Manual Item / Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <?php
                                    $itemCode = (string) ($item['code'] ?? '');
                                    $itemUom = (string) ($item['uom_code'] ?? $item['base_uom_code'] ?? 'PCS');
                                    $itemCost = (string) ($item['purchase_price'] ?? $item['standard_cost'] ?? $item['unit_cost'] ?? $item['avg_cost'] ?? '0');
                                ?>
                                <option value="<?= esc($itemCode) ?>" data-name="<?= esc($item['name'] ?? '', 'attr') ?>" data-uom="<?= esc($itemUom, 'attr') ?>" data-cost="<?= esc($itemCost, 'attr') ?>" <?= old('item_code') === $itemCode ? 'selected' : '' ?>>
                                    <?= esc(($item['code'] ?? '-') . ' - ' . ($item['name'] ?? '-')) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text">Pilih dari master item agar nama, UoM, dan unit cost terisi otomatis.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Manual Item Code</label>
                        <input type="text" name="manual_item_code" id="manualItemCode" class="form-control" value="<?= esc(old('manual_item_code')) ?>" placeholder="Contoh: ITEM-001">
                        <div class="form-text">Isi manual hanya jika item belum ada di master.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="item_name" id="itemName" class="form-control" value="<?= esc(old('item_name')) ?>" placeholder="Contoh: Barang Testing">
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Qty +/- <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" name="qty" id="qtyInput" class="form-control text-end" required value="<?= esc(old('qty', '1')) ?>">
                            <div class="form-text">Positif tambah stok, negatif kurangi stok.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">UoM</label>
                            <input type="text" name="uom_code" id="uomCode" class="form-control" value="<?= esc(old('uom_code', 'PCS')) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit Cost</label>
                            <input type="number" step="0.000001" name="unit_cost" id="unitCost" class="form-control text-end" value="<?= esc(old('unit_cost', '0')) ?>">
                            <div class="form-text">Wajib &gt; 0 untuk tambah stok.</div>
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
                        <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-light">Cancel</a>
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
                                <th>GL</th>
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
                                <td><?= ! empty($movement['gl_entry_id']) ? '<a href="' . site_url('gl/entries/' . $movement['gl_entry_id']) . '">#' . esc($movement['gl_entry_id']) . '</a>' : '-' ?></td>
                            </tr>
                        <?php endforeach ?>

                        <?php if ($recentMovements === []): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No stock movement yet.</td></tr>
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
    const warehouseSelect = document.getElementById('warehouseSelect');
    const locationSelect = document.getElementById('locationSelect');
    const locationHelp = document.getElementById('locationHelp');
    const itemSelect = document.getElementById('itemSelect');
    const manualItemCode = document.getElementById('manualItemCode');
    const itemName = document.getElementById('itemName');
    const uomCode = document.getElementById('uomCode');
    const unitCost = document.getElementById('unitCost');
    const oldLocationValue = '<?= esc((string) old('location_id'), 'js') ?>';

    const allLocationOptions = Array.from(locationSelect.querySelectorAll('option[data-warehouse-id]')).map(function (option) {
        return option.cloneNode(true);
    });

    function refreshSelect2(select) {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && window.jQuery(select).data('select2')) {
            window.jQuery(select).trigger('change.select2');
        }
    }

    function refreshLocations() {
        const warehouseId = warehouseSelect.value;
        locationSelect.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = warehouseId ? 'Pilih / cari Location' : 'Pilih warehouse dulu';
        locationSelect.appendChild(placeholder);

        if (!warehouseId) {
            locationHelp.textContent = 'Pilih warehouse dulu agar location terfilter.';
            refreshSelect2(locationSelect);
            return;
        }

        let matchCount = 0;
        allLocationOptions.forEach(function (option) {
            if (String(option.dataset.warehouseId || '') === String(warehouseId)) {
                matchCount++;
                const cloned = option.cloneNode(true);
                if (oldLocationValue && cloned.value === oldLocationValue) {
                    cloned.selected = true;
                }
                locationSelect.appendChild(cloned);
            }
        });

        if (matchCount === 0) {
            const autoOption = document.createElement('option');
            autoOption.value = '__auto__';
            autoOption.textContent = 'Auto-create MAIN Location untuk warehouse ini';
            autoOption.selected = true;
            locationSelect.appendChild(autoOption);
            locationHelp.textContent = 'Belum ada location untuk warehouse ini. Sistem akan membuat MAIN Location saat posting.';
        } else {
            locationHelp.textContent = 'Location otomatis hanya muncul sesuai warehouse.';
            if (locationSelect.options.length === 2 && !oldLocationValue) {
                locationSelect.selectedIndex = 1;
            }
        }

        refreshSelect2(locationSelect);
    }

    function fillItemFields() {
        const option = itemSelect.options[itemSelect.selectedIndex];
        if (option && option.value) {
            manualItemCode.value = option.value;
            itemName.value = option.dataset.name || '';
            uomCode.value = option.dataset.uom || 'PCS';
            if (option.dataset.cost && Number(option.dataset.cost) > 0) {
                unitCost.value = option.dataset.cost;
            }
        }
    }

    warehouseSelect.addEventListener('change', refreshLocations);
    itemSelect.addEventListener('change', fillItemFields);

    refreshLocations();
    fillItemFields();
});
</script>
<?= $this->endSection() ?>
