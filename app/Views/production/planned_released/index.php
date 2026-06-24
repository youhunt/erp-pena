<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="card-title mb-1">Planned Released</h4>
                <p class="text-muted mb-0">Board planned order dari MRP untuk dipersiapkan, disetujui, dan dikonversi menjadi dokumen kerja.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/mrp') ?>" class="btn btn-outline-primary">MRP</a>
                <a href="<?= site_url('production/forecasts') ?>" class="btn btn-light">Forecast</a>
            </div>
        </div>

        <?php if (! $hasTable): ?>
            <div class="alert alert-warning">Tabel planned order belum tersedia. Jalankan <code>database/hosting/2026-06-24_add_mrp_planned_orders.sql</code>.</div>
        <?php endif ?>

        <div class="row mb-4">
            <div class="col-md-2"><div class="border rounded p-3"><div class="text-muted">Total</div><h4><?= number_format((float) ($summary['total'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-2"><div class="border rounded p-3"><div class="text-muted">Planned</div><h4 class="text-warning"><?= number_format((float) ($summary['planned'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-2"><div class="border rounded p-3"><div class="text-muted">Prepared</div><h4 class="text-info"><?= number_format((float) ($summary['prepared'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-2"><div class="border rounded p-3"><div class="text-muted">Approved</div><h4 class="text-primary"><?= number_format((float) ($summary['approved'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-2"><div class="border rounded p-3"><div class="text-muted">Converted</div><h4 class="text-success"><?= number_format((float) ($summary['converted'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-2"><div class="border rounded p-3"><div class="text-muted">Cancelled</div><h4 class="text-secondary"><?= number_format((float) ($summary['cancelled'] ?? 0), 0) ?></h4></div></div>
        </div>

        <form method="get" action="<?= site_url('modules/planned-released') ?>" class="row g-2 mb-4">
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <?php foreach (['planned','prepared','approved','converted','cancelled'] as $s): ?>
                        <option value="<?= esc($s, 'attr') ?>" <?= $status === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4">
                <select name="type" class="form-select">
                    <option value="">All Plan Type</option>
                    <?php foreach (array_keys($typeSummary) as $t): ?>
                        <option value="<?= esc($t, 'attr') ?>" <?= $type === $t ? 'selected' : '' ?>><?= esc($t) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= site_url('modules/planned-released') ?>">Reset</a>
            </div>
        </form>

        <div class="mb-3">
            <?php foreach ($typeSummary as $typeName => $count): ?>
                <span class="badge bg-light text-dark border me-1 mb-1"><code><?= esc($typeName) ?></code>: <?= number_format((float) $count, 0) ?></span>
            <?php endforeach ?>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Plan No</th>
                        <th>MRP Run</th>
                        <th>Plan Type</th>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th>Status</th>
                        <th>Target Doc</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No planned release found.</td></tr><?php endif ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                        $runId = (int) ($row['mrp_run_id'] ?? 0);
                        $planId = (int) ($row['id'] ?? 0);
                        $rowStatus = (string) ($row['status'] ?? 'planned');
                        $badge = match ($rowStatus) {
                            'converted' => 'bg-success-subtle text-success',
                            'approved' => 'bg-primary-subtle text-primary',
                            'prepared' => 'bg-info-subtle text-info',
                            'cancelled' => 'bg-secondary-subtle text-secondary',
                            default => 'bg-warning-subtle text-warning',
                        };
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($row['plan_no'] ?? '') ?></td>
                        <td><a href="<?= site_url('production/mrp/runs/' . $runId) ?>#planned-orders"><?= esc($row['run_no'] ?? '') ?></a><br><small class="text-muted"><?= esc(($row['from_date'] ?? '') . ' - ' . ($row['to_date'] ?? '')) ?></small></td>
                        <td><code><?= esc($row['plan_type'] ?? '') ?></code></td>
                        <td><strong><?= esc($row['item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($row['item_name'] ?? '') ?></small></td>
                        <td class="text-end"><?= number_format((float) ($row['qty'] ?? 0), 6) ?> <?= esc($row['uom_code'] ?? '') ?></td>
                        <td><span class="badge <?= $badge ?>"><?= esc($rowStatus) ?></span></td>
                        <td><?= esc(trim((string) ($row['target_doc_type'] ?? '') . ' ' . (string) ($row['target_doc_no'] ?? ''))) ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-outline-info" href="<?= site_url('production/mrp/runs/' . $runId) ?>?planned_order_id=<?= $planId ?>&planned_status=prepared#planned-orders">Prepare</a>
                                <a class="btn btn-outline-primary" href="<?= site_url('production/mrp/runs/' . $runId) ?>?planned_order_id=<?= $planId ?>&planned_status=approved#planned-orders">Approve</a>
                                <a class="btn btn-outline-success" href="<?= site_url('production/mrp/runs/' . $runId) ?>?planned_order_id=<?= $planId ?>&planned_status=converted#planned-orders">Convert</a>
                                <a class="btn btn-light" href="<?= site_url('production/mrp/runs/' . $runId) ?>#planned-orders">Open</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
