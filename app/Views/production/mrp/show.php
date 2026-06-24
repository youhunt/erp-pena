<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$actionGroups = [];
$statusGroups = [];
foreach ($lines as $line) {
    $action = (string) ($line['suggested_action'] ?? '');
    $status = (string) ($line['action_status'] ?? 'open');
    $actionGroups[$action] = ($actionGroups[$action] ?? 0) + 1;
    $statusGroups[$status] = ($statusGroups[$status] ?? 0) + 1;
}
ksort($actionGroups);
ksort($statusGroups);
$actionFilter ??= '';
$statusFilter ??= '';
$actionStatuses ??= ['open', 'in_progress', 'converted', 'closed', 'ignored'];
$hasActionColumns = (bool) ($hasActionColumns ?? false);
$hasPlannedOrderTable = (bool) ($hasPlannedOrderTable ?? false);
$plannedOrders ??= [];
$plannedGroups = [];
foreach ($plannedOrders as $order) {
    $type = (string) ($order['plan_type'] ?? 'planning_task');
    $plannedGroups[$type] = ($plannedGroups[$type] ?? 0) + 1;
}
ksort($plannedGroups);
$runId = (int) ($run['id'] ?? 0);
$runUrl = site_url('production/mrp/runs/' . $runId);
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="card-title mb-1">MRP Run <?= esc($run['run_no'] ?? '') ?></h4>
                <p class="text-muted mb-0">Period <?= esc(($run['from_date'] ?? '') . ' - ' . ($run['to_date'] ?? '')) ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/mrp') ?>" class="btn btn-light">Back</a>
                <a href="#mrp-action-plan" class="btn btn-outline-primary">Action Plan</a>
                <a href="#planned-orders" class="btn btn-outline-success">Planned Orders</a>
            </div>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>
        <?php if (! $hasActionColumns): ?>
            <div class="alert alert-warning">Kolom MRP Action Plan belum tersedia. Jalankan <code>database/hosting/2026-06-24_add_mrp_action_plan_columns.sql</code> atau installer full Forecast/MRP.</div>
        <?php endif ?>
        <?php if (! $hasPlannedOrderTable): ?>
            <div class="alert alert-warning">Tabel Planned Orders belum tersedia. Jalankan <code>database/hosting/2026-06-24_add_mrp_planned_orders.sql</code>.</div>
        <?php endif ?>

        <div class="row mb-4">
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Demand Items</div><h4><?= number_format((float) ($run['demand_count'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">MRP Lines</div><h4><?= number_format((float) ($run['line_count'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Gross Requirement</div><h4><?= number_format((float) ($run['gross_qty'] ?? 0), 4) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Net Requirement</div><h4><?= number_format((float) ($run['net_qty'] ?? 0), 4) ?></h4></div></div>
        </div>

        <div class="row mb-4" id="mrp-action-plan">
            <div class="col-xl-7">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Action Plan Summary</h5>
                        <?php if ($hasPlannedOrderTable && $hasActionColumns): ?>
                            <a class="btn btn-sm btn-success" href="<?= $runUrl ?>?generate_planned_orders=1#planned-orders">Generate Planned Orders</a>
                        <?php endif ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Suggested Action</th><th class="text-end">Lines</th><th class="text-end">Filter</th></tr></thead>
                            <tbody>
                            <?php if ($actionGroups === []): ?><tr><td colspan="3" class="text-center text-muted">No action.</td></tr><?php endif ?>
                            <?php foreach ($actionGroups as $action => $count): ?>
                                <tr>
                                    <td><code><?= esc($action !== '' ? $action : '-') ?></code></td>
                                    <td class="text-end fw-semibold"><?= number_format((float) $count, 0) ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-light" href="<?= $runUrl ?>?action=<?= urlencode($action) ?>#mrp-action-plan">View</a></td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="border rounded p-3 h-100">
                    <h5 class="mb-3">Execution Status</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($statusGroups === []): ?><span class="text-muted">No status.</span><?php endif ?>
                        <?php foreach ($statusGroups as $status => $count): ?>
                            <a class="badge bg-light text-dark border" href="<?= $runUrl ?>?status=<?= urlencode($status) ?>#mrp-action-plan"><?= esc($status !== '' ? $status : 'open') ?>: <?= number_format((float) $count, 0) ?></a>
                        <?php endforeach ?>
                    </div>
                    <p class="text-muted mt-3 mb-0">Status action dipakai untuk follow-up: open, in_progress, converted, closed, ignored.</p>
                </div>
            </div>
        </div>

        <div class="card border" id="planned-orders">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">MRP Planned Orders</h5>
                        <p class="text-muted mb-0">Draft rencana dari MRP sebelum dikonversi ke PR/WO final.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($plannedGroups as $type => $count): ?>
                            <span class="badge bg-light text-dark border"><?= esc($type) ?>: <?= number_format((float) $count, 0) ?></span>
                        <?php endforeach ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Plan No</th><th>Type</th><th>Item</th><th>UoM</th><th class="text-end">Qty</th><th>Status</th><th>Target</th></tr></thead>
                        <tbody>
                        <?php if ($plannedOrders === []): ?><tr><td colspan="7" class="text-center text-muted py-3">No planned orders yet.</td></tr><?php endif ?>
                        <?php foreach ($plannedOrders as $order): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($order['plan_no'] ?? '') ?></td>
                                <td><code><?= esc($order['plan_type'] ?? '') ?></code></td>
                                <td><strong><?= esc($order['item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($order['item_name'] ?? '') ?></small></td>
                                <td><?= esc($order['uom_code'] ?? '') ?></td>
                                <td class="text-end"><?= number_format((float) ($order['qty'] ?? 0), 6) ?></td>
                                <td><span class="badge bg-info-subtle text-info"><?= esc($order['status'] ?? 'planned') ?></span></td>
                                <td><?= esc(trim((string) ($order['target_doc_type'] ?? '') . ' ' . (string) ($order['target_doc_no'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <form method="get" action="<?= $runUrl ?>" class="row g-2 my-3">
            <div class="col-md-4">
                <select name="action" class="form-select">
                    <option value="">All Suggested Actions</option>
                    <?php foreach (array_keys($actionGroups) as $action): ?>
                        <option value="<?= esc($action, 'attr') ?>" <?= $actionFilter === $action ? 'selected' : '' ?>><?= esc($action !== '' ? $action : '-') ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <?php foreach ($actionStatuses as $status): ?>
                        <option value="<?= esc($status, 'attr') ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= esc($status) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-light" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= $runUrl ?>#mrp-action-plan">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Type</th>
                        <th>Parent / Demand Item</th>
                        <th>Material</th>
                        <th>UoM</th>
                        <th class="text-end">Gross Req.</th>
                        <th class="text-end">Stock Available</th>
                        <th class="text-end">Net Req.</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>Planned Doc</th>
                        <th>Quick Update</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($lines === []): ?><tr><td colspan="12" class="text-center text-muted py-4">No MRP lines.</td></tr><?php endif ?>
                <?php foreach ($lines as $line): ?>
                    <?php
                        $net = (float) ($line['net_requirement'] ?? 0);
                        $type = (string) ($line['line_type'] ?? 'material');
                        $actionStatus = (string) ($line['action_status'] ?? 'open');
                        $lineId = (int) ($line['id'] ?? 0);
                        $statusClass = match ($actionStatus) {
                            'converted', 'closed' => 'bg-success-subtle text-success',
                            'in_progress' => 'bg-primary-subtle text-primary',
                            'ignored' => 'bg-secondary-subtle text-secondary',
                            default => 'bg-warning-subtle text-warning',
                        };
                    ?>
                    <tr>
                        <td><?= esc($line['line_no'] ?? '') ?></td>
                        <td><span class="badge <?= $type === 'missing_bom' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' ?>"><?= esc($type) ?></span></td>
                        <td><?= esc($line['parent_item_code'] ?? '') ?></td>
                        <td><strong><?= esc($line['component_item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($line['component_item_name'] ?? '') ?></small></td>
                        <td><?= esc($line['uom_code'] ?? '') ?></td>
                        <td class="text-end"><?= number_format((float) ($line['gross_requirement'] ?? 0), 6) ?></td>
                        <td class="text-end"><?= number_format((float) ($line['stock_available'] ?? 0), 6) ?></td>
                        <td class="text-end fw-bold <?= $net > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($net, 6) ?></td>
                        <td><code><?= esc($line['suggested_action'] ?? '') ?></code></td>
                        <td><span class="badge <?= $statusClass ?>"><?= esc($actionStatus !== '' ? $actionStatus : 'open') ?></span></td>
                        <td><small><?= esc(trim((string) ($line['planned_doc_type'] ?? '') . ' ' . (string) ($line['planned_doc_no'] ?? ''))) ?></small></td>
                        <td>
                            <?php if ($hasActionColumns && $lineId > 0): ?>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a class="btn btn-outline-primary" href="<?= $runUrl ?>?action_line_id=<?= $lineId ?>&action_status=in_progress#mrp-action-plan">Start</a>
                                    <a class="btn btn-outline-success" href="<?= $runUrl ?>?action_line_id=<?= $lineId ?>&action_status=converted#mrp-action-plan">Converted</a>
                                    <a class="btn btn-outline-secondary" href="<?= $runUrl ?>?action_line_id=<?= $lineId ?>&action_status=ignored#mrp-action-plan">Ignore</a>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
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
