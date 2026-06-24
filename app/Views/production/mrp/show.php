<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$actionFilter ??= '';
$statusFilter ??= '';
$hasActionColumns = (bool) ($hasActionColumns ?? false);
$hasPlannedOrderTable = (bool) ($hasPlannedOrderTable ?? false);
$plannedOrders ??= [];
$runId = (int) ($run['id'] ?? 0);
$runUrl = site_url('production/mrp/runs/' . $runId);
$actionGroups = [];
$statusGroups = [];
foreach ($lines as $line) {
    $a = (string) ($line['suggested_action'] ?? '-');
    $s = (string) ($line['action_status'] ?? 'open');
    $actionGroups[$a] = ($actionGroups[$a] ?? 0) + 1;
    $statusGroups[$s] = ($statusGroups[$s] ?? 0) + 1;
}
ksort($actionGroups);
ksort($statusGroups);
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">MRP Run <?= esc($run['run_no'] ?? '') ?></h4>
                <p class="text-muted mb-0">Period <?= esc(($run['from_date'] ?? '') . ' - ' . ($run['to_date'] ?? '')) ?></p>
            </div>
            <a href="<?= site_url('production/mrp') ?>" class="btn btn-light">Back</a>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>
        <?php if (! $hasActionColumns): ?><div class="alert alert-warning">Jalankan SQL action plan columns / installer full Forecast-MRP dulu.</div><?php endif ?>
        <?php if (! $hasPlannedOrderTable): ?><div class="alert alert-warning">Jalankan <code>database/hosting/2026-06-24_add_mrp_planned_orders.sql</code>.</div><?php endif ?>

        <div class="row mb-4">
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Demand Items</div><h4><?= number_format((float) ($run['demand_count'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">MRP Lines</div><h4><?= number_format((float) ($run['line_count'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Gross Req.</div><h4><?= number_format((float) ($run['gross_qty'] ?? 0), 4) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Net Req.</div><h4><?= number_format((float) ($run['net_qty'] ?? 0), 4) ?></h4></div></div>
        </div>

        <div class="row mb-4" id="mrp-action-plan">
            <div class="col-xl-7">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Action Plan Summary</h5>
                        <?php if ($hasActionColumns && $hasPlannedOrderTable): ?>
                            <a href="<?= $runUrl ?>?generate_planned_orders=1#planned-orders" class="btn btn-sm btn-success">Generate Planned Orders</a>
                        <?php endif ?>
                    </div>
                    <?php foreach ($actionGroups as $action => $count): ?>
                        <a class="badge bg-light text-dark border me-1 mb-1" href="<?= $runUrl ?>?action=<?= urlencode($action) ?>#mrp-lines"><code><?= esc($action) ?></code>: <?= number_format((float) $count, 0) ?></a>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="border rounded p-3 h-100">
                    <h5 class="mb-3">Action Status</h5>
                    <?php foreach ($statusGroups as $status => $count): ?>
                        <a class="badge bg-light text-dark border me-1 mb-1" href="<?= $runUrl ?>?status=<?= urlencode($status) ?>#mrp-lines"><?= esc($status) ?>: <?= number_format((float) $count, 0) ?></a>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <div class="card border" id="planned-orders">
            <div class="card-body">
                <h5 class="mb-3">MRP Planned Orders</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Plan No</th><th>Type</th><th>Item</th><th class="text-end">Qty</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                        <?php if ($plannedOrders === []): ?><tr><td colspan="6" class="text-center text-muted py-3">No planned orders yet.</td></tr><?php endif ?>
                        <?php foreach ($plannedOrders as $order): ?>
                            <?php $poId = (int) ($order['id'] ?? 0); $poStatus = (string) ($order['status'] ?? 'planned'); ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($order['plan_no'] ?? '') ?></td>
                                <td><code><?= esc($order['plan_type'] ?? '') ?></code></td>
                                <td><strong><?= esc($order['item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($order['item_name'] ?? '') ?></small></td>
                                <td class="text-end"><?= number_format((float) ($order['qty'] ?? 0), 6) ?> <?= esc($order['uom_code'] ?? '') ?></td>
                                <td><span class="badge bg-info-subtle text-info"><?= esc($poStatus) ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a class="btn btn-outline-info" href="<?= $runUrl ?>?planned_order_id=<?= $poId ?>&planned_status=prepared#planned-orders">Prepare</a>
                                        <a class="btn btn-outline-primary" href="<?= $runUrl ?>?planned_order_id=<?= $poId ?>&planned_status=approved#planned-orders">Approve</a>
                                        <a class="btn btn-outline-success" href="<?= $runUrl ?>?planned_order_id=<?= $poId ?>&planned_status=converted#planned-orders">Convert</a>
                                        <a class="btn btn-outline-secondary" href="<?= $runUrl ?>?planned_order_id=<?= $poId ?>&planned_status=cancelled#planned-orders">Cancel</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <form method="get" action="<?= $runUrl ?>" class="row g-2 my-3">
            <div class="col-md-4"><input type="text" name="action" value="<?= esc($actionFilter, 'attr') ?>" class="form-control" placeholder="suggested_action filter"></div>
            <div class="col-md-3"><input type="text" name="status" value="<?= esc($statusFilter, 'attr') ?>" class="form-control" placeholder="action_status filter"></div>
            <div class="col-md-3"><button class="btn btn-light">Filter</button> <a href="<?= $runUrl ?>#mrp-lines" class="btn btn-outline-secondary">Reset</a></div>
        </form>

        <div class="table-responsive" id="mrp-lines">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>No</th><th>Type</th><th>Parent</th><th>Material</th><th class="text-end">Gross</th><th class="text-end">Stock</th><th class="text-end">Net</th><th>Action</th><th>Status</th><th>Planned Doc</th><th>Update</th></tr></thead>
                <tbody>
                <?php if ($lines === []): ?><tr><td colspan="11" class="text-center text-muted py-4">No MRP lines.</td></tr><?php endif ?>
                <?php foreach ($lines as $line): ?>
                    <?php $lineId = (int) ($line['id'] ?? 0); $net = (float) ($line['net_requirement'] ?? 0); ?>
                    <tr>
                        <td><?= esc($line['line_no'] ?? '') ?></td>
                        <td><?= esc($line['line_type'] ?? '') ?></td>
                        <td><?= esc($line['parent_item_code'] ?? '') ?></td>
                        <td><strong><?= esc($line['component_item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($line['component_item_name'] ?? '') ?></small></td>
                        <td class="text-end"><?= number_format((float) ($line['gross_requirement'] ?? 0), 6) ?></td>
                        <td class="text-end"><?= number_format((float) ($line['stock_available'] ?? 0), 6) ?></td>
                        <td class="text-end fw-bold <?= $net > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($net, 6) ?></td>
                        <td><code><?= esc($line['suggested_action'] ?? '') ?></code></td>
                        <td><span class="badge bg-warning-subtle text-warning"><?= esc($line['action_status'] ?? 'open') ?></span></td>
                        <td><small><?= esc(trim((string) ($line['planned_doc_type'] ?? '') . ' ' . (string) ($line['planned_doc_no'] ?? ''))) ?></small></td>
                        <td>
                            <?php if ($hasActionColumns && $lineId > 0): ?>
                                <div class="btn-group btn-group-sm">
                                    <a class="btn btn-outline-primary" href="<?= $runUrl ?>?action_line_id=<?= $lineId ?>&action_status=in_progress#mrp-lines">Start</a>
                                    <a class="btn btn-outline-success" href="<?= $runUrl ?>?action_line_id=<?= $lineId ?>&action_status=converted#mrp-lines">Done</a>
                                    <a class="btn btn-outline-secondary" href="<?= $runUrl ?>?action_line_id=<?= $lineId ?>&action_status=ignored#mrp-lines">Ignore</a>
                                </div>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
