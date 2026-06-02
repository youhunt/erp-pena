<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Roles & Permissions</h4>
                <p class="text-muted mb-0">Read-only overview of Shield groups and permission matrix.</p>
            </div>
            <a href="<?= site_url('admin/users') ?>" class="btn btn-outline-secondary">
                <i class="bx bx-user me-1"></i> Users
            </a>
        </div>

        <div class="row">
            <?php foreach ($groups as $key => $group): ?>
                <?php $rolePermissions = $matrix[$key] ?? []; ?>
                <div class="col-xl-6">
                    <div class="card border shadow-none">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div>
                                    <h5 class="mb-1"><?= esc($group['title'] ?? $key) ?></h5>
                                    <p class="text-muted mb-0"><?= esc($group['description'] ?? '-') ?></p>
                                </div>
                                <span class="badge bg-primary"><?= esc($key) ?></span>
                            </div>

                            <div class="mt-3 d-flex flex-wrap gap-1">
                                <?php foreach ($rolePermissions as $permission): ?>
                                    <span class="badge bg-light text-dark border"><?= esc($permission) ?></span>
                                <?php endforeach ?>

                                <?php if ($rolePermissions === []): ?>
                                    <span class="text-muted">No permissions assigned.</span>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3">Permission Catalog</h5>
        <div class="table-responsive">
            <table class="table table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Permission</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($permissions as $permission => $description): ?>
                    <tr>
                        <td class="fw-semibold"><code><?= esc($permission) ?></code></td>
                        <td><?= esc($description) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
