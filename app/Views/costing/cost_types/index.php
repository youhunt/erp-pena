<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">Cost Type</h4>
                <p class="text-muted mb-0">Master cost type untuk Material, Labor, dan Overhead.</p>
            </div>
            <a href="<?= site_url('modules/calculate-cost') ?>" class="btn btn-success">Calculate Cost</a>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <?php if (! $hasTable): ?>
            <div class="alert alert-warning">Tabel Costing belum tersedia. Jalankan <code>database/hosting/2026-06-24_install_costing_module.sql</code>.</div>
        <?php else: ?>
            <form method="get" action="<?= site_url('modules/cost-type') ?>" class="row g-3 border rounded bg-light p-3 mb-4">
                <input type="hidden" name="action" value="save">
                <div class="col-md-2">
                    <label class="form-label">Cost Type</label>
                    <input type="text" name="type" maxlength="10" class="form-control" required placeholder="TK">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" maxlength="300" class="form-control" placeholder="Tenaga Kerja">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cost Group</label>
                    <select name="cost_group" class="form-select" required>
                        <option value="Material">Material</option>
                        <option value="Labor">Labor</option>
                        <option value="Overhead">Overhead</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Save</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Cost Type</th>
                        <th>Description</th>
                        <th>Cost Group</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No cost type yet.</td></tr><?php endif ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($row['type'] ?? '') ?></td>
                            <td><?= esc($row['description'] ?? '') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= esc($row['cost_group'] ?? '') ?></span></td>
                            <td><?= ! empty($row['is_active']) ? '<span class="badge bg-success-subtle text-success">Active</span>' : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>' ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-danger" href="<?= site_url('modules/cost-type?action=delete&id=' . (int) $row['id']) ?>">Disable</a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
