<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$oldQtys = old('qty_delivered', []);
$oldBatchNos = old('batch_no', []);
if (! is_array($oldQtys)) {
    $oldQtys = [];
}
if (! is_array($oldBatchNos)) {
    $oldBatchNos = [];
}
$selectedWarehouseId = (int) old('warehouse_id', $selectedWarehouseId ?? 0);
$selectedLocationId = (int) old('location_id', $selectedLocationId ?? 0);
?>
<form method="post" action="<?= site_url('sales/orders/' . $so['id'] . '/deliver') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="card-title mb-1">Create Delivery Order</h4>
                    <p class="text-muted mb-0"><?= esc($so['so_no']) ?> - <?= esc($so['customer_name'] ?? '-') ?></p>
                </div>
                <a href="<?= site_url('sales/orders/' . $so['id']) ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back to SO</a>
            </div>

            <?php if (session('error')): ?>
                <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php endif ?>
            <?php if (session('message')): ?>
                <div class="alert alert-success"><?= esc(session('message')) ?></div>
            <?php endif ?>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Delivery No</label>
                    <input type="text" name="delivery_no" class="form-control" placeholder="<?= esc(($suggestedDeliveryNo ?? '') !== '' ? $suggestedDeliveryNo : 'Auto if blank', 'attr') ?>" value="<?= esc(old('delivery_no')) ?>">
                    <small class="text-muted">Kosongkan untuk nomor otomatis.</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Delivery Date</label>
                    <input type="date" name="delivery_date" class="form-control" required value="<?= esc(old('delivery_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" id="deliveryWarehouse" class="form-select" required>
                        <option value="">Select Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <?php $warehouseId = (int) $warehouse['id']; ?>
                            <option value="<?= $warehouseId ?>" <?= $selectedWarehouseId === $warehouseId ? 'selected' : '' ?>><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Location</label>
                    <select name="location_id" id="deliveryLocation" class="form-select" required>
                        <option value="" data-warehouse-id="">Select Location</option>
                        <?php foreach ($locations as $location): ?>
                            <?php $locationId = (int) $location['id']; ?>
                            <option value="<?= $locationId ?>" data-warehouse-id="<?= (int) ($location['warehouse_id'] ?? 0) ?>" <?= $selectedLocationId === $locationId ? 'selected' : '' ?>><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-3">Outstanding Lines</h4>
            <div class="alert alert-info py-2">
                Isi <strong>Deliver Now</strong> sesuai qty barang yang benar-benar dikirim. Setelah posting berhasil, sistem akan update SO delivered/outstanding dan mengurangi stock inventory.
            </div>
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0" id="deliveryLinesTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th style="min-width:150px;">Batch No</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Reserved</th>
                            <th class="text-end">Delivered</th>
                            <th class="text-end">Outstanding</th>
                            <th class="text-end">Available</th>
                            <th class="text-end" style="min-width:150px;">Deliver Now</th>
                            <th>UoM</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $index => $line): ?>
                        <?php
                            $itemCode = (string) ($line['item_code'] ?? '');
                            $available = (float) (($stockByItem[$itemCode]['available'] ?? 0));
                            $outstanding = (float) ($line['qty_outstanding'] ?? $line['qty'] ?? 0);
                            $suggestedQty = min($outstanding, max(0, $available));
                            $qtyValue = array_key_exists($index, $oldQtys) ? $oldQtys[$index] : $suggestedQty;
                            $batchValue = array_key_exists($index, $oldBatchNos) ? $oldBatchNos[$index] : '';
                        ?>
                        <tr>
                            <td><?= esc($line['so_line'] ?? $line['line_no']) ?><input type="hidden" name="sales_order_line_id[]" value="<?= (int) $line['id'] ?>"></td>
                            <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                            <td><input type="text" name="batch_no[]" class="form-control" value="<?= esc((string) $batchValue) ?>" placeholder="Optional"></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_ordered'] ?? $line['qty'] ?? 0), 4)) ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_reserved'] ?? 0), 4)) ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_delivered'] ?? 0), 4)) ?></td>
                            <td class="text-end fw-semibold outstanding-qty"><?= esc(number_format($outstanding, 4, '.', '')) ?></td>
                            <td class="text-end <?= $available <= 0 ? 'text-danger fw-semibold' : '' ?>"><?= esc(number_format($available, 4, '.', '')) ?></td>
                            <td>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    name="qty_delivered[]"
                                    class="form-control text-end deliver-now"
                                    data-outstanding="<?= esc((string) $outstanding, 'attr') ?>"
                                    data-available="<?= esc((string) max(0, $available), 'attr') ?>"
                                    value="<?= esc((string) $qtyValue) ?>"
                                >
                            </td>
                            <td><?= esc($line['uom_code'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach ?>

                    <?php if ($lines === []): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No outstanding line to deliver.</td></tr>
                    <?php endif ?>
                    </tbody>
                    <?php if ($lines !== []): ?>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="8" class="text-end">Total Deliver Now</th>
                            <th class="text-end" id="totalDeliverNow">0.0000</th>
                            <th></th>
                        </tr>
                    </tfoot>
                    <?php endif ?>
                </table>
            </div>

            <div class="alert alert-warning mt-4 mb-0">
                Stok dihitung berdasarkan warehouse dan location yang dipilih saat halaman dibuka. Jika stok terlihat kosong, lakukan Purchase Receipt atau Stock Adjustment dulu, lalu buka ulang form DO.
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" <?= $lines === [] ? 'disabled' : '' ?> onclick="return confirm('Post delivery ini? SO qty akan terupdate dan stock inventory akan berkurang.')"><i class="bx bx-send me-1"></i> Post Delivery & Update Stock</button>
                <a href="<?= site_url('inventory/stock-adjustment') ?>" class="btn btn-outline-primary">Stock Adjustment</a>
                <a href="<?= site_url('sales/orders/' . $so['id']) ?>" class="btn btn-light">Back to SO</a>
            </div>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const warehouse = document.getElementById('deliveryWarehouse');
    const location = document.getElementById('deliveryLocation');
    const totalDeliverNow = document.getElementById('totalDeliverNow');

    function number(value) {
        value = String(value || '').trim();
        if (value.indexOf(',') >= 0 && value.indexOf('.') < 0) value = value.replace(',', '.');
        else value = value.replace(/,/g, '');
        const parsed = parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function recalcDeliverTotal() {
        let total = 0;
        document.querySelectorAll('.deliver-now').forEach(function (input) {
            total += number(input.value);
        });
        if (totalDeliverNow) totalDeliverNow.textContent = total.toLocaleString(undefined, {minimumFractionDigits: 4, maximumFractionDigits: 4});
    }

    document.querySelectorAll('.deliver-now').forEach(function (input) {
        input.addEventListener('input', function () {
            const outstanding = number(input.dataset.outstanding);
            const available = number(input.dataset.available);
            const limit = Math.min(outstanding, available);
            const value = number(input.value);
            input.classList.toggle('is-invalid', value < 0 || value > limit);
            recalcDeliverTotal();
        });
    });

    function syncLocations() {
        if (!warehouse || !location) return;
        const warehouseId = warehouse.value;
        let selectedVisible = false;

        Array.from(location.options).forEach(function (option) {
            if (option.value === '') {
                option.hidden = false;
                return;
            }
            const visible = warehouseId !== '' && option.dataset.warehouseId === warehouseId;
            option.hidden = !visible;
            if (visible && option.selected) selectedVisible = true;
        });

        if (!selectedVisible) {
            const firstVisible = Array.from(location.options).find(function (option) {
                return option.value !== '' && !option.hidden;
            });
            location.value = firstVisible ? firstVisible.value : '';
        }
    }

    if (warehouse && location) warehouse.addEventListener('change', syncLocations);
    syncLocations();
    recalcDeliverTotal();
});
</script>
<?= $this->endSection() ?>
