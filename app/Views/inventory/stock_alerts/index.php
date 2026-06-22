<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $exportUrl = site_url('inventory/stock-alerts') . '?' . http_build_query(['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? '', 'export' => 'xlsx']); ?>
<div class="row">
    <?php foreach ([
        'total' => ['label' => 'Total Setup', 'class' => 'primary'],
        'below_min' => ['label' => 'Below Min', 'class' => 'danger'],
        'reorder' => ['label' => 'Reorder', 'class' => 'warning'],
        'over_max' => ['label' => 'Over Max', 'class' => 'info'],
        'ok' => ['label' => 'OK', 'class' => 'success'],
    ] as $field => $meta): ?>
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card mini-stats-wid">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2"><?= esc($meta['label']) ?></p>
                            <h4 class="mb-0"><?= esc(number_format((int) ($summary[$field] ?? 0), 0, ',', '.')) ?></h4>
                        </div>
                        <span class="badge bg-<?= esc($meta['class']) ?>">&nbsp;</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Stock Alerts</h4>
                <p class="text-muted mb-0">Min/max/reorder monitoring by item location.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= esc($exportUrl) ?>" class="btn btn-success"><i class="bx bx-download me-1"></i> Export XLSX</a>
                <a href="<?= site_url('setup/item-locations') ?>" class="btn btn-outline-primary"><i class="bx bx-map me-1"></i> Item Locations</a>
                <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-outline-primary"><i class="bx bx-layer me-1"></i> Stock Balance</a>
            </div>
        </div>

        <form method="get" action="<?= site_url('inventory/stock-alerts') ?>" class="row g-2 align-items-end mb-4">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="<?= esc($filters['q'] ?? '') ?>" class="form-control" placeholder="Item, warehouse, location">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bx bx-search me-1"></i> Filter</button>
                <a href="<?= site_url('inventory/stock-alerts') ?>" class="btn btn-light"><i class="bx bx-reset me-1"></i> Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th class="text-end">Available</th>
                        <th class="text-end">Min</th>
                        <th class="text-end">Reorder</th>
                        <th class="text-end">Max</th>
                        <th class="text-end">Suggested Qty</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= esc($row['item_code'] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($row['item_name'] ?? '-') ?></small>
                        </td>
                        <td><?= esc(trim(($row['warehouse_code'] ?? '-') . ' ' . ($row['warehouse_name'] ?? ''))) ?></td>
                        <td><?= esc(trim(($row['location_code'] ?? '-') . ' ' . ($row['location_name'] ?? ''))) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($row['qty_available'] ?? 0), 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['min_qty'] ?? 0), 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['reorder_qty'] ?? 0), 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['max_qty'] ?? 0), 4)) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($row['suggested_qty'] ?? 0), 4)) ?></td>
                        <td><span class="badge bg-<?= esc($row['alert_badge'] ?? 'secondary') ?>"><?= esc($row['alert_label'] ?? '-') ?></span></td>
                    </tr>
                <?php endforeach ?>

                <?php if ($rows === []): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No item location alert found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
