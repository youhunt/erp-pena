<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Development</p><h4><?= esc((string) $overall['internal_development']) ?>%</h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Demo</p><h4><?= esc((string) $overall['internal_demo']) ?>%</h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">UAT</p><h4><?= esc((string) $overall['uat_readiness']) ?>%</h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Production</p><h4 class="text-warning"><?= esc((string) $overall['production_readiness']) ?>%</h4></div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">PENA ERP Development Status</h4>
                <p class="text-muted mb-0">Latest development journey, module readiness, and UAT focus.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= site_url('system/development-status?export=xlsx') ?>" class="btn btn-success"><i class="bx bx-download me-1"></i> Export XLSX</a>
                <a href="<?= site_url('purchase/orders') ?>" class="btn btn-outline-primary">Purchase Flow</a>
                <a href="<?= site_url('sales/orders') ?>" class="btn btn-outline-primary">Sales Flow</a>
                <a href="<?= site_url('gl/entries') ?>" class="btn btn-outline-primary">GL Validation</a>
                <a href="<?= site_url('inventory/stock-card') ?>" class="btn btn-outline-primary">Stock Card</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Area</th><th>Status</th><th class="text-end">Readiness</th><th>Notes</th></tr>
                </thead>
                <tbody>
                <?php foreach ($modules as $module): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($module['area']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= esc($module['status']) ?></span></td>
                        <td class="text-end fw-semibold"><?= esc((string) $module['readiness']) ?>%</td>
                        <td><?= esc($module['notes']) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h4 class="card-title mb-1">ERP Core UAT Flow Board</h4>
                <p class="text-muted mb-0">Gunakan board ini untuk test alur utama dari transaksi sampai audit.</p>
            </div>
            <span class="badge bg-warning-subtle text-warning">Internal UAT</span>
        </div>
        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Flow</th><th>Steps</th><th>Status</th><th class="text-end">Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($coreFlows as $flow): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($flow['flow']) ?></td>
                        <td><?= esc($flow['steps']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= esc($flow['status']) ?></span></td>
                        <td class="text-end">
                            <a href="<?= esc($flow['entry']) ?>" class="btn btn-sm btn-outline-primary">Start</a>
                            <a href="<?= esc($flow['audit']) ?>" class="btn btn-sm btn-outline-secondary">Audit</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-7">
        <div class="card"><div class="card-body">
            <h4 class="card-title mb-3">Current UAT Focus</h4>
            <ol class="mb-0">
                <?php foreach ($uatFocus as $focus): ?>
                    <li class="mb-2"><?= esc($focus) ?></li>
                <?php endforeach ?>
            </ol>
        </div></div>
    </div>
    <div class="col-xl-5">
        <div class="card"><div class="card-body">
            <h4 class="card-title mb-3">ERP Core Guardrails</h4>
            <ul class="mb-0">
                <?php foreach ($coreGuardrails as $guardrail): ?>
                    <li class="mb-2"><?= esc($guardrail) ?></li>
                <?php endforeach ?>
            </ul>
        </div></div>
    </div>
</div>

<div class="row">
    <div class="col-xl-7">
        <div class="card"><div class="card-body">
            <h4 class="card-title mb-3">Next Core Backlog</h4>
            <div class="table-responsive">
                <table class="table table-sm table-nowrap align-middle mb-0">
                    <thead class="table-light"><tr><th>Priority</th><th>Item</th><th>Target</th></tr></thead>
                    <tbody>
                    <?php foreach ($nextCoreBacklog as $item): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc((string) $item['priority']) ?></td>
                            <td><?= esc($item['item']) ?></td>
                            <td><?= esc($item['target']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
    <div class="col-xl-5">
        <div class="card"><div class="card-body">
            <h4 class="card-title mb-3">Updated Markdown Docs</h4>
            <ul class="mb-0">
                <li><code>docs/15-development-journey-status.md</code></li>
                <li><code>docs/16-core-uat-status-checklist.md</code></li>
                <li><code>docs/28-erp-core-continuation.md</code></li>
                <li><code>README.md</code></li>
            </ul>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
