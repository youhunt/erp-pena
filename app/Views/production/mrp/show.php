<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="card-title mb-1">MRP Run <?= esc($run['run_no'] ?? '') ?></h4>
                <p class="text-muted mb-0">Period <?= esc(($run['from_date'] ?? '') . ' - ' . ($run['to_date'] ?? '')) ?></p>
            </div>
            <a href="<?= site_url('production/mrp') ?>" class="btn btn-light">Back</a>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>

        <div class="row mb-4">
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Demand Items</div><h4><?= number_format((float) ($run['demand_count'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">MRP Lines</div><h4><?= number_format((float) ($run['line_count'] ?? 0), 0) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Gross Requirement</div><h4><?= number_format((float) ($run['gross_qty'] ?? 0), 4) ?></h4></div></div>
            <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted">Net Requirement</div><h4><?= number_format((float) ($run['net_qty'] ?? 0), 4) ?></h4></div></div>
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
                    </tr>
                </thead>
                <tbody>
                <?php if ($lines === []): ?><tr><td colspan="9" class="text-center text-muted py-4">No MRP lines.</td></tr><?php endif ?>
                <?php foreach ($lines as $line): ?>
                    <?php $net = (float) ($line['net_requirement'] ?? 0); $type = (string) ($line['line_type'] ?? 'material'); ?>
                    <tr>
                        <td><?= esc($line['line_no'] ?? '') ?></td>
                        <td><span class="badge <?= $type === 'missing_bom' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' ?>"><?= esc($type) ?></span></td>
                        <td><?= esc($line['parent_item_code'] ?? '') ?></td>
                        <td><strong><?= esc($line['component_item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($line['component_item_name'] ?? '') ?></small></td>
                        <td><?= esc($line['uom_code'] ?? '') ?></td>
                        <td class="text-end"><?= number_format((float) ($line['gross_requirement'] ?? 0), 6) ?></td>
                        <td class="text-end"><?= number_format((float) ($line['stock_available'] ?? 0), 6) ?></td>
                        <td class="text-end fw-bold <?= $net > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($net, 6) ?></td>
                        <td><?= esc($line['suggested_action'] ?? '') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
