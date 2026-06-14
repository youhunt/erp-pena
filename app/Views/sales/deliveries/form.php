<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('sales/orders/' . $so['id'] . '/deliver') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="card-title mb-1">Create Delivery Order</h4>
                    <p class="text-muted mb-0"><?= esc($so['so_no']) ?> - <?= esc($so['customer_name'] ?? '-') ?></p>
                </div>
                <a href="<?= site_url('sales/orders/' . $so['id']) ?>" class="btn btn-light">Back to SO</a>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Delivery No</label>
                    <input type="text" name="delivery_no" class="form-control" required value="<?= esc(old('delivery_no', 'DO-' . date('Ymd-His'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Delivery Date</label>
                    <input type="date" name="delivery_date" class="form-control" required value="<?= esc(old('delivery_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">No Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= (int) $warehouse['id'] ?>" <?= (int) ($selectedWarehouseId ?? 0) === (int) $warehouse['id'] ? 'selected' : '' ?>><?= esc(($warehouse['code'] ?? $warehouse['id']) . ' - ' . ($warehouse['name'] ?? '-')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Location</label>
                    <select name="location_id" class="form-select">
                        <option value="">No Location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" <?= (int) ($selectedLocationId ?? 0) === (int) $location['id'] ? 'selected' : '' ?>><?= esc(($location['code'] ?? $location['id']) . ' - ' . ($location['name'] ?? '-')) ?></option>
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
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Batch No</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Reserved</th>
                            <th class="text-end">Delivered</th>
                            <th class="text-end">Outstanding</th>
                            <th class="text-end">Available</th>
                            <th class="text-end">Deliver Now</th>
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
                        ?>
                        <tr>
                            <td><?= esc($line['line_no']) ?><input type="hidden" name="sales_order_line_id[]" value="<?= (int) $line['id'] ?>"></td>
                            <td><div class="fw-semibold"><?= esc($line['item_code'] ?? '-') ?></div><small class="text-muted"><?= esc($line['item_name'] ?? '-') ?></small></td>
                            <td><input type="text" name="batch_no[]" class="form-control" value="<?= esc(old('batch_no.' . $index)) ?>" placeholder="Optional"></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_ordered'] ?? $line['qty'] ?? 0), 4)) ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_reserved'] ?? 0), 4)) ?></td>
                            <td class="text-end"><?= esc(number_format((float) ($line['qty_delivered'] ?? 0), 4)) ?></td>
                            <td class="text-end fw-semibold"><?= esc(number_format($outstanding, 4)) ?></td>
                            <td class="text-end <?= $available <= 0 ? 'text-danger fw-semibold' : '' ?>"><?= esc(number_format($available, 4)) ?></td>
                            <td><input type="number" step="0.0001" max="<?= esc((string) min($outstanding, max(0, $available))) ?>" name="qty_delivered[]" class="form-control text-end" value="<?= esc((string) $suggestedQty) ?>"></td>
                            <td><?= esc($line['uom_code'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach ?>

                    <?php if ($lines === []): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No outstanding line to deliver.</td></tr>
                    <?php endif ?>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-info mt-4 mb-0">
                Stok dihitung dari warehouse dan location yang dipilih saat halaman dibuka. Jika stok kosong, lakukan Purchase Receipt atau Stock Adjustment dulu, lalu buka ulang form DO.
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" <?= $lines === [] ? 'disabled' : '' ?> onclick="return confirm('Post this delivery and decrease stock?')"><i class="bx bx-send me-1"></i> Post Delivery</button>
                <a href="<?= site_url('inventory/stock-adjustment') ?>" class="btn btn-outline-primary">Stock Adjustment</a>
                <a href="<?= site_url('sales/orders/' . $so['id']) ?>" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
