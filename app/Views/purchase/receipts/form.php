<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$oldQtys = old('qty_received', []);
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
<form method="post" action="<?= site_url('purchase/orders/' . $po['id'] . '/receive') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="card-title mb-1">Receive Purchase Order</h4>
                    <p class="text-muted mb-0"><?= esc($po['po_no']) ?> - <?= esc($po['supplier_name'] ?? '-') ?></p>
                </div>
                <a href="<?= site_url('purchase/orders/' . $po['id']) ?>" class="btn btn-light">Back to PO</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Receipt No</label>
                    <input type="text" name="receipt_no" class="form-control" required value="<?= esc(old('receipt_no', 'PR-' . date('Ymd-His'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Receipt Date</label>
                    <input type="date" name="receipt_date" class="form-control" required value="<?= esc(old('receipt_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">No Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <?php $warehouseId = (int) $warehouse['id']; ?>
                            <option value="<?= $warehouseId ?>" <?= $selectedWarehouseId === $warehouseId ? 'selected' : '' ?>><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Location</label>
                    <select name="location_id" class="form-select">
                        <option value="">No Location</option>
                        <?php foreach ($locations as $location): ?>
                            <?php $locationId = (int) $location['id']; ?>
                            <option value="<?= $locationId ?>" <?= $selectedLocationId === $locationId ? 'selected' : '' ?>><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
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
                Qty <strong>Receive Now</strong> bisa diedit sebagian. Kalau posting gagal, angka yang sudah diedit akan tetap dipertahankan agar mudah koreksi.
            </div>
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Batch No</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Received</th>
                            <th class="text-end">Outstanding</th>
                            <th class="text-end">Receive Now</th>
                            <th>UoM</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $index => $line): ?>
                        <?php
                        $outstanding = (float) ($line['qty_outstanding'] ?? $line['qty'] ?? 0);
                        $qtyValue = array_key_exists($index, $oldQtys) ? $oldQtys[$index] : $outstanding;
                        $batchValue = array_key_exists($index, $oldBatchNos) ? $oldBatchNos[$index] : '';
                        ?>
                        <tr>
                            <td><?= esc($line['po_line'] ?? $line['line_no']) ?><input type="hidden" name="purchase_order_line_id[]" value="<?= (int) $line['id'] ?>"></td>
                            <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                            <td><input type="text" name="batch_no[]" class="form-control" value="<?= esc((string) $batchValue) ?>" placeholder="Optional"></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_ordered'] ?? $line['qty'] ?? 0), 4)) ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_received'] ?? 0), 4)) ?></td>
                            <td class="text-end fw-semibold"><?= esc(number_format($outstanding, 4)) ?></td>
                            <td>
                                <input
                                    type="number"
                                    step="0.0001"
                                    max="<?= esc((string) $outstanding) ?>"
                                    name="qty_received[]"
                                    class="form-control text-end"
                                    value="<?= esc((string) $qtyValue) ?>"
                                >
                            </td>
                            <td><?= esc($line['uom_code'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach ?>

                    <?php if ($lines === []): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No outstanding line to receive.</td></tr>
                    <?php endif ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" <?= $lines === [] ? 'disabled' : '' ?> onclick="return confirm('Post this receipt and increase stock?')"><i class="bx bx-package me-1"></i> Post Receipt</button>
                <a href="<?= site_url('purchase/orders/' . $po['id']) ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
