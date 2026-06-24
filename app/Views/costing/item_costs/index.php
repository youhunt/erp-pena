<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">Item Cost</h4>
                <p class="text-muted mb-0">Master cost per item, site, department, dan warehouse.</p>
            </div>
            <a href="<?= site_url('modules/calculate-cost') ?>" class="btn btn-success">Calculate Cost</a>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <?php if (! $hasTable): ?>
            <div class="alert alert-warning">Tabel Item Cost belum tersedia. Jalankan <code>database/hosting/2026-06-24_install_costing_module.sql</code>.</div>
        <?php else: ?>
            <form method="get" action="<?= site_url('modules/item-cost') ?>" class="row g-3 border rounded bg-light p-3 mb-4">
                <input type="hidden" name="action" value="save">
                <div class="col-md-3"><label class="form-label">Item Code</label><input type="text" name="item_code" class="form-control" required placeholder="FG KARET A"></div>
                <div class="col-md-3"><label class="form-label">Item Name</label><input type="text" name="item_name" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Site</label><input type="text" name="site_code" class="form-control" placeholder="10"></div>
                <div class="col-md-2"><label class="form-label">Department</label><input type="text" name="department_code" class="form-control" placeholder="101"></div>
                <div class="col-md-2"><label class="form-label">Warehouse</label><input type="text" name="warehouse_code" class="form-control" placeholder="Whs001"></div>
                <div class="col-md-8"><label class="form-label">Description</label><input type="text" name="description" class="form-control" placeholder="Cost KARET Finish Goods NOVA"></div>
                <div class="col-md-2"><label class="form-label">This Item Cost</label><input type="number" step="0.000001" name="this_item_cost" class="form-control" value="0"></div>
                <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100">Save</button></div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Site</th>
                        <th>Department</th>
                        <th>Warehouse</th>
                        <th class="text-end">This Item Cost</th>
                        <th class="text-end">BOM Cost</th>
                        <th class="text-end">Total Cost</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?><tr><td colspan="9" class="text-center text-muted py-4">No item cost yet.</td></tr><?php endif ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?= esc($row['item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($row['item_name'] ?? '') ?></small></td>
                            <td><?= esc($row['site_code'] ?? '') ?></td>
                            <td><?= esc($row['department_code'] ?? '') ?></td>
                            <td><?= esc($row['warehouse_code'] ?? '') ?></td>
                            <td class="text-end"><?= number_format((float) ($row['this_item_cost'] ?? 0), 6) ?></td>
                            <td class="text-end"><?= number_format((float) ($row['bom_cost'] ?? 0), 6) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float) ($row['total_cost'] ?? 0), 6) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= esc($row['status'] ?? 'draft') ?></span></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-success" href="<?= site_url('modules/calculate-cost?item_code=' . rawurlencode((string) ($row['item_code'] ?? ''))) ?>">Calculate</a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
