<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$db = \Config\Database::connect();
$plannedOrders = [];
$plannedSummary = [];
if ($db->tableExists('production_mrp_planned_orders')) {
    $tenant = new \App\Services\TenantContext(session());
    $builder = $db->table('production_mrp_planned_orders po')
        ->select('po.*, r.run_no')
        ->join('production_mrp_runs r', 'r.id = po.mrp_run_id', 'left');
    if ($tenant->activeCompanyId() !== null) {
        $builder->where('po.company_id', $tenant->activeCompanyId());
    }
    if ($tenant->activeSiteId() !== null) {
        $builder->where('po.site_id', $tenant->activeSiteId());
    }
    $plannedOrders = $builder
        ->whereIn('po.status', ['planned', 'prepared', 'approved'])
        ->orderBy('po.id', 'DESC')
        ->get(50)
        ->getResultArray();

    foreach ($plannedOrders as $order) {
        $key = (string) ($order['plan_type'] ?? 'planning_task');
        $plannedSummary[$key] = ($plannedSummary[$key] ?? 0) + 1;
    }
    ksort($plannedSummary);
}
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">MRP Planning</h4>
                <p class="text-muted mb-0">Generate material requirement from forecast demand and BOM.</p>
            </div>
            <a href="<?= site_url('production/forecasts') ?>" class="btn btn-outline-primary">Forecast</a>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <form method="post" action="<?= site_url('production/mrp/run') ?>" class="row g-3 mb-4 border rounded p-3 bg-light">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" required value="<?= esc(old('from_date', $defaultFrom)) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" required value="<?= esc(old('to_date', $defaultTo)) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Item Filter</label>
                <select name="item_code" class="form-select">
                    <option value="">All forecast items</option>
                    <?php foreach ($items as $item): ?><?php $code = (string) ($item['item_code'] ?? $item['code'] ?? ''); ?>
                        <option value="<?= esc($code, 'attr') ?>"><?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '')) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit"><i class="bx bx-cog me-1"></i> Generate</button>
            </div>
        </form>

        <div class="card border mb-4" id="planned-order-board">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Planned Order Board</h5>
                        <p class="text-muted mb-0">Open planning result from MRP, ready to prepare, approve, or convert.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($plannedSummary as $type => $count): ?>
                            <span class="badge bg-light text-dark border"><?= esc($type) ?>: <?= number_format((float) $count, 0) ?></span>
                        <?php endforeach ?>
                    </div>
                </div>
                <?php if (! $db->tableExists('production_mrp_planned_orders')): ?>
                    <div class="alert alert-warning mb-0">Tabel planned order belum ada. Jalankan <code>database/hosting/2026-06-24_add_mrp_planned_orders.sql</code>.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Plan No</th>
                                    <th>Run</th>
                                    <th>Type</th>
                                    <th>Item</th>
                                    <th class="text-end">Qty</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($plannedOrders === []): ?><tr><td colspan="7" class="text-center text-muted py-3">No open planned orders.</td></tr><?php endif ?>
                            <?php foreach ($plannedOrders as $order): ?>
                                <?php $runId = (int) ($order['mrp_run_id'] ?? 0); $poId = (int) ($order['id'] ?? 0); ?>
                                <tr>
                                    <td class="fw-semibold"><?= esc($order['plan_no'] ?? '') ?></td>
                                    <td><?= esc($order['run_no'] ?? '') ?></td>
                                    <td><code><?= esc($order['plan_type'] ?? '') ?></code></td>
                                    <td><strong><?= esc($order['item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($order['item_name'] ?? '') ?></small></td>
                                    <td class="text-end"><?= number_format((float) ($order['qty'] ?? 0), 6) ?> <?= esc($order['uom_code'] ?? '') ?></td>
                                    <td><span class="badge bg-info-subtle text-info"><?= esc($order['status'] ?? 'planned') ?></span></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a class="btn btn-outline-primary" href="<?= site_url('production/mrp/runs/' . $runId) ?>?planned_order_id=<?= $poId ?>&planned_status=approved#planned-orders">Approve</a>
                                            <a class="btn btn-outline-success" href="<?= site_url('production/mrp/runs/' . $runId) ?>?planned_order_id=<?= $poId ?>&planned_status=converted#planned-orders">Convert</a>
                                            <a class="btn btn-light" href="<?= site_url('production/mrp/runs/' . $runId) ?>#planned-orders">Open Run</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <h5 class="mb-3">MRP Runs</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Run No</th>
                        <th>Period</th>
                        <th class="text-end">Demand</th>
                        <th class="text-end">Lines</th>
                        <th class="text-end">Gross Qty</th>
                        <th class="text-end">Net Qty</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($runs === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No MRP run yet.</td></tr><?php endif ?>
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td><strong><?= esc($run['run_no'] ?? '') ?></strong></td>
                        <td><?= esc(($run['from_date'] ?? '') . ' - ' . ($run['to_date'] ?? '')) ?></td>
                        <td class="text-end"><?= number_format((float) ($run['demand_count'] ?? 0), 0) ?></td>
                        <td class="text-end"><?= number_format((float) ($run['line_count'] ?? 0), 0) ?></td>
                        <td class="text-end"><?= number_format((float) ($run['gross_qty'] ?? 0), 4) ?></td>
                        <td class="text-end"><?= number_format((float) ($run['net_qty'] ?? 0), 4) ?></td>
                        <td><span class="badge bg-primary-subtle text-primary"><?= esc($run['status'] ?? '') ?></span></td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="<?= site_url('production/mrp/runs/' . (int) $run['id']) ?>">Open</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
