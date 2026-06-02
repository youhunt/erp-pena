<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">User Management</h4>
                <p class="text-muted mb-0">Manage ERP users, roles, company access, and site access.</p>
            </div>
            <a href="<?= site_url('admin/users/new') ?>" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> New User
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($row['username']) ?></td>
                        <td><?= esc($row['email']) ?></td>
                        <td><?= esc($row['groups'] ?: '-') ?></td>
                        <td>
                            <span class="badge bg-<?= (int) $row['active'] === 1 ? 'success' : 'secondary' ?>">
                                <?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="<?= site_url('admin/users/' . $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bx bx-edit"></i>
                            </a>
                            <form action="<?= site_url('admin/users/' . $row['id'] . '/toggle') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bx bx-power-off"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
