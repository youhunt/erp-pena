<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Work Center</h4>
                <p class="text-muted mb-0">Machine, capacity, labor, working hour, and cost settings for production operations.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('production/imports/work-centers') ?>" class="btn btn-outline-primary"><i class="bx bx-upload me-1"></i> Import</a>
                <a href="<?= site_url('production/work-centers/new') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Work Center</a>
            </div>
        </div>

        <?php if (session('error')): ?>
            <div class="alert alert-danger"><?= esc(session('error')) ?></div>
        <?php endif ?>
        <?php if (session('message')): ?>
            <div class="alert alert-success"><?= esc(session('message')) ?></div>
        <?php endif ?>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Work Center</th>
                        <th>Site</th>
                        <th>Department</th>
                        <th>Warehouse</th>
                        <th>Primary Machine</th>
                        <th class="text-end">Capacity %</th>
                        <th class="text-end">Labor</th>
                        <th class="text-end">Hour</th>
                        <th class="text-end">Primary Cost</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><a href="<?= site_url('production/work-centers/' . $row['id']) ?>"><?= esc($row['work_center_code']) ?></a></div>
                            <small class="text-muted"><?= esc($row['description'] ?? '-') ?></small>
                        </td>
                        <td><?= esc($row['site_code']) ?></td>
                        <td><?= esc($row['department_code']) ?></td>
                        <td><?= esc($row['warehouse_code']) ?></td>
                        <td><?= esc($row['machine_code'] ?: '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['capacity_percent'] ?? 0), 3)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['qty_labor'] ?? 0), 4)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['working_hour'] ?? 0), 3)) ?></td>
                        <td class="text-end">
                            <?php if ((float) ($row['cost_amount'] ?? 0) > 0 || trim((string) ($row['cost_type'] ?? '')) !== ''): ?>
                                <div class="fw-semibold"><?= esc(number_format((float) ($row['cost_amount'] ?? 0), 2)) ?> <?= esc($row['cost_uom'] ?? '') ?></div>
                                <small class="text-muted"><?= esc($row['cost_type'] ?? '-') ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= (int) ($row['is_active'] ?? 1) === 1 ? 'success' : 'secondary' ?>">
                                <?= (int) ($row['is_active'] ?? 1) === 1 ? 'active' : 'inactive' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="<?= site_url('production/work-centers/' . $row['id']) ?>" class="btn btn-sm btn-light">View</a>
                                <a href="<?= site_url('production/work-centers/' . $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="post" action="<?= site_url('production/work-centers/' . $row['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Hapus Work Center <?= esc($row['work_center_code'], 'js') ?>? Data yang sudah dipakai di Routing/Work Order tidak bisa dihapus.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">No work center yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>

        <div class="alert alert-info mt-3 mb-0">
            Primary Machine dan Primary Cost diambil dari row pertama pada Machine Detail dan Cost Detail. Work Center yang sudah dipakai di Routing / Work Order tidak bisa dihapus.
        </div>
    </div>
</div>
<?= $this->endSection() ?>
