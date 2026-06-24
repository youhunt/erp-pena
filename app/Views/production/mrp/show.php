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
            </div>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>

        <div class="row mb-4">
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Demand Items</div><h4><?= number_format((float) ($run['demand_count'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">MRP Lines</div><h4><?= number_format((float) ($run['line_count'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Gross Requirement</div><h4><?= number_format((float) ($run['gross_qty'] ?? 0), 4) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Net Requirement</div><h4><?= number_format((float) ($run['net_qty'] ?? 0), 4) ?></h4></div></div>
        </div>

        <div class="row mb-4" id="mrp-action-plan">
            <div class="col-xl-7">
                <div class="border rounded p-3 h-100">
                    <h5 class="mb-3">Action Plan Summary</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Suggested Action</th><th class="text-end">Lines</th></tr></thead>
                            <tbody>
                            <?php if ($actionGroups === []): ?><tr><td colspan="2" class="text-center text-muted">No action.</td></tr><?php endif ?>
                            <?php foreach ($actionGroups as $action => $count): ?>
                                <tr><td><code><?= esc($action !== '' ? $action : '-') ?></code></td><td class="text-end fw-semibold"><?= number_format((float) $count, 0) ?></td></tr>
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
                            <span class="badge bg-light text-dark border"><?= esc($status !== '' ? $status : 'open') ?>: <?= number_format((float) $count, 0) ?></span>
                        <?php endforeach ?>
                    </div>
                    <p class="text-muted mt-3 mb-0">Status action akan dipakai untuk follow-up material: open, in_progress, converted, closed, atau ignored.</p>
                </div>
            </div>
        </div>

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
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($lines === []): ?><tr><td colspan="11" class="text-center text-muted py-4">No MRP lines.</td></tr><?php endif ?>
                <?php foreach ($lines as $line): ?>
                    <?php
                        $net = (float) ($line['net_requirement'] ?? 0);
                        $type = (string) ($line['line_type'] ?? 'material');
                        $actionStatus = (string) ($line['action_status'] ?? 'open');
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
                        <td><?= esc($line['action_notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
