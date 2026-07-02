<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$exportUrl = site_url('inventory/stock-balances') . '?' . http_build_query(['q' => $keyword, 'export' => 'xlsx']);
$audit ??= ['rows' => [], 'mismatch_count' => 0, 'qty_diff' => 0.0, 'value_diff' => 0.0];
$stockCardUrl = static function (array $balance): string {
    return site_url('inventory/stock-card') . '?' . http_build_query(array_filter([
        'item_code' => (string) ($balance['item_code'] ?? ''),
        'batch_no' => (string) ($balance['batch_no'] ?? ''),
        'warehouse_id' => (int) ($balance['warehouse_id'] ?? 0),
        'location_id' => (int) ($balance['location_id'] ?? 0),
    ], static fn ($value): bool => (string) $value !== '' && (string) $value !== '0'));
};
$balanceKey = static function (array $row): string {
    return strtoupper(trim((string) ($row['item_code'] ?? ''))) . '|'
        . trim((string) ($row['batch_no'] ?? '')) . '|'
        . (int) ($row['warehouse_id'] ?? 0) . '|'
        . (int) ($row['location_id'] ?? 0);
};
?>
<div class="row">
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Items</p><h4 class="mb-0"><?= esc(number_format((int) $summary['item_count'])) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Qty On Hand</p><h4 class="mb-0"><?= esc(number_format((float) $summary['qty_on_hand'], 4)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Qty Available</p><h4 class="mb-0"><?= esc(number_format((float) $summary['qty_available'], 4)) ?></h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card mini-stats-wid"><div class="card-body"><p class="text-muted mb-2">Stock Value</p><h4 class="mb-0"><?= esc(number_format((float) $summary['stock_value'], 2)) ?></h4></div></div>
    </div>
</div>

<div class="card border-<?= ((int) ($audit['mismatch_count'] ?? 0)) > 0 ? 'warning' : 'success' ?>">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="card-title mb-1">Movement Reconciliation</h5>
                <p class="text-muted mb-0">Compares current stock balance against inventory movement ledger for the displayed rows.</p>
            </div>
            <div class="text-end">
                <span class="badge bg-<?= ((int) ($audit['mismatch_count'] ?? 0)) > 0 ? 'warning text-dark' : 'success' ?>">
                    <?= esc((string) ((int) ($audit['mismatch_count'] ?? 0))) ?> mismatch
                </span>
                <div class="small text-muted mt-1">Qty Diff <?= esc(number_format((float) ($audit['qty_diff'] ?? 0), 6)) ?> | Value Diff <?= esc(number_format((float) ($audit['value_diff'] ?? 0), 2)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Stock Balance</h4>
                <p class="text-muted mb-0">Current stock on hand, reserved, available, average cost, stock value, and movement audit status.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= esc($exportUrl) ?>" class="btn btn-success"><i class="bx bx-download me-1"></i> Export XLSX</a>
                <a href="<?= site_url('inventory/stock-card') ?>" class="btn btn-outline-primary"><i class="bx bx-list-ul me-1"></i> Stock Card</a>
                <a href="<?= site_url('inventory/in-out') ?>" class="btn btn-outline-primary"><i class="bx bx-log-in-circle me-1"></i> In Out</a>
                <a href="<?= site_url('inventory/transfers') ?>" class="btn btn-outline-primary"><i class="bx bx-transfer me-1"></i> Transfer</a>
                <a href="<?= site_url('inventory/stock-opname') ?>" class="btn btn-outline-primary"><i class="bx bx-check-square me-1"></i> Opname</a>
                <a href="<?= site_url('inventory/stock-adjustment') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> Stock Adjustment</a>
            </div>
        </div>

        <form method="get" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Search item/warehouse/location" value="<?= esc($keyword) ?>">
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-primary" type="submit">Search</button>
                <a href="<?= site_url('inventory/stock-balances') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Batch</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th>UoM</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-end">Reserved</th>
                        <th class="text-end">Available</th>
                        <th class="text-end">Avg Cost</th>
                        <th class="text-end">Value</th>
                        <th>Audit</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($balances as $balance): ?>
                    <?php $rowAudit = $audit['rows'][$balanceKey($balance)] ?? ['status' => 'OK', 'qty_diff' => 0, 'value_diff' => 0]; ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($balance['item_code'] ?? '-') ?></td>
                        <td><?= esc(($balance['batch_no'] ?? '') !== '' ? $balance['batch_no'] : '-') ?></td>
                        <td><?= esc(trim(($balance['warehouse_code'] ?? '-') . ' ' . ($balance['warehouse_name'] ?? ''))) ?></td>
                        <td><?= esc(trim(($balance['location_code'] ?? '-') . ' ' . ($balance['location_name'] ?? ''))) ?></td>
                        <td><?= esc($balance['uom_code'] ?? '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($balance['qty_on_hand'] ?? 0), 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($balance['qty_reserved'] ?? 0), 4)) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($balance['qty_available'] ?? 0), 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($balance['avg_cost'] ?? 0), 6)) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($balance['stock_value'] ?? 0), 2)) ?></td>
                        <td>
                            <?php if (($rowAudit['status'] ?? 'OK') === 'OK'): ?>
                                <span class="badge bg-success">OK</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Mismatch</span>
                                <div class="small text-muted">Qty <?= esc(number_format((float) ($rowAudit['qty_diff'] ?? 0), 6)) ?> | Value <?= esc(number_format((float) ($rowAudit['value_diff'] ?? 0), 2)) ?></div>
                            <?php endif ?>
                        </td>
                        <td class="text-end"><a href="<?= esc($stockCardUrl($balance)) ?>" class="btn btn-sm btn-outline-primary">Stock Card</a></td>
                    </tr>
                <?php endforeach ?>

                <?php if ($balances === []): ?>
                    <tr><td colspan="12" class="text-center text-muted py-4">No stock balance found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
