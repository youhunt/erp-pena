<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Opening Qty</p><h4 class="mb-0"><?= esc(number_format((float) $summary['opening_qty'], 4)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Qty In</p><h4 class="mb-0 text-success"><?= esc(number_format((float) $summary['qty_in'], 4)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Qty Out</p><h4 class="mb-0 text-danger"><?= esc(number_format((float) $summary['qty_out'], 4)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Ending Qty</p><h4 class="mb-0"><?= esc(number_format((float) $summary['ending_qty'], 4)) ?></h4></div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Stock Card</h4>
                <p class="text-muted mb-0">Chronological stock movement per item, warehouse, and location.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-outline-primary"><i class="bx bx-layer me-1"></i> Stock Balance</a>
                <a href="<?= site_url('inventory/transfers') ?>" class="btn btn-outline-primary"><i class="bx bx-transfer me-1"></i> Transfer</a>
            </div>
        </div>

        <form method="get" class="row g-2 mb-4">
            <div class="col-md-3">
                <label class="form-label">Item</label>
                <select name="item_code" class="form-select">
                    <option value="">All Items</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= esc($item['item_code']) ?>" <?= ($filters['item_code'] ?? '') === ($item['item_code'] ?? '') ? 'selected' : '' ?>>
                            <?= esc($item['item_code']) ?><?= ! empty($item['item_name']) ? ' - ' . esc($item['item_name']) : '' ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Warehouse</label>
                <select name="warehouse_id" class="form-select">
                    <option value="">All Warehouses</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= (int) $warehouse['id'] ?>" <?= (int) ($filters['warehouse_id'] ?? 0) === (int) $warehouse['id'] ? 'selected' : '' ?>>
                            <?= esc($warehouse['code'] ?? $warehouse['name'] ?? $warehouse['id']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Location</label>
                <select name="location_id" class="form-select">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= (int) $location['id'] ?>" <?= (int) ($filters['location_id'] ?? 0) === (int) $location['id'] ? 'selected' : '' ?>>
                            <?= esc($location['code'] ?? $location['name'] ?? $location['id']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= esc($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= esc($filters['date_to']) ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end gap-2">
                <button class="btn btn-primary" type="submit"><i class="bx bx-search"></i></button>
                <a href="<?= site_url('inventory/stock-card') ?>" class="btn btn-light"><i class="bx bx-reset"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th class="text-end">Qty In</th>
                        <th class="text-end">Qty Out</th>
                        <th class="text-end">Balance</th>
                        <th class="text-end">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light">
                        <td><?= esc($filters['date_from']) ?></td>
                        <td colspan="5" class="fw-semibold">Opening Balance</td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) $opening['qty'], 4)) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) $opening['stock_value'], 2)) ?></td>
                    </tr>

                    <?php foreach ($movements as $movement): ?>
                        <tr>
                            <td><?= esc(substr((string) $movement['movement_date'], 0, 10)) ?></td>
                            <td>
                                <div class="fw-semibold"><?= esc($movement['item_code'] ?? '-') ?></div>
                                <div class="text-muted small"><?= esc($movement['item_name'] ?? '') ?></div>
                            </td>
                            <td><?= esc($movement['warehouse_code'] ?? '-') ?></td>
                            <td><?= esc($movement['location_code'] ?? '-') ?></td>
                            <td><?= esc($movement['movement_type'] ?? '-') ?></td>
                            <td>
                                <div><?= esc($movement['reference_no'] ?? '-') ?></div>
                                <div class="text-muted small"><?= esc($movement['reference_type'] ?? '') ?></div>
                            </td>
                            <td class="text-end text-success"><?= esc(number_format((float) $movement['qty_in'], 4)) ?></td>
                            <td class="text-end text-danger"><?= esc(number_format((float) $movement['qty_out'], 4)) ?></td>
                            <td class="text-end fw-semibold"><?= esc(number_format((float) $movement['running_qty'], 4)) ?></td>
                            <td class="text-end"><?= esc(number_format((float) $movement['running_value'], 2)) ?></td>
                        </tr>
                    <?php endforeach ?>

                    <?php if ($movements === []): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No stock movement found for selected filter.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
