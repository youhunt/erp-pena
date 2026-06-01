<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="card-title mb-0"><?= esc($config['title']) ?></h4>
            <div class="d-flex gap-2">
            <?php if ($canManage && ! empty($config['sync_action'])): ?>
                <form action="<?= site_url($config['sync_action']) ?>" method="post">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-info waves-effect waves-light" type="submit">
                        <i class="bx bx-refresh me-1"></i> Sync API
                    </button>
                </form>
            <?php endif ?>

            <?php if ($canManage): ?>
                <a class="btn btn-primary waves-effect waves-light" href="<?= site_url("setup/{$resource}/new") ?>">
                    <i class="bx bx-plus me-1"></i> New
                </a>
            <?php endif ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc((string) ($row[$display['code']] ?? $row['code'] ?? $row['id'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row[$display['name']] ?? $row['name'] ?? '-')) ?></td>
                        <td><?= esc((string) ($row[$display['description']] ?? $row['description'] ?? $row['address'] ?? $row['email'] ?? '-')) ?></td>
                        <td>
                            <span class="badge bg-<?= (int) ($row['is_active'] ?? 1) === 1 ? 'success' : 'secondary' ?>">
                                <?= (int) ($row['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($canManage): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= site_url("setup/{$resource}/{$row['id']}/edit") ?>">
                                    <i class="bx bx-edit"></i>
                                </a>
                                <form class="d-inline" action="<?= site_url("setup/{$resource}/{$row['id']}/delete") ?>" method="post" onsubmit="return confirm('Delete this record?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">View only</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No records yet.</td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
