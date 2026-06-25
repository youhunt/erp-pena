<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $edit = $editRow ?? null; ?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="card-title mb-1">Employee ID</h4>
            <p class="text-muted mb-0">Employee master for cash bank and operational reference.</p>
        </div>
        <a href="<?= site_url('cash-bank/employees') ?>" class="btn btn-outline-secondary">Tambah Baru</a>
    </div>
    <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

    <form method="get" action="<?= site_url('cash-bank/employees') ?>" class="row g-3 border rounded bg-light p-3 mb-4">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= esc($edit['id'] ?? '') ?>">
        <div class="col-12"><h6 class="mb-0"><?= $edit ? 'Edit Employee' : 'Tambah Employee' ?></h6></div>
        <div class="col-md-2"><label class="form-label">Employee ID</label><input type="text" name="employee_code" maxlength="12" class="form-control" required value="<?= esc($edit['employee_code'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Site Code</label><input type="text" name="site_code" maxlength="12" class="form-control" value="<?= esc($edit['site_code'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Dept Code</label><input type="text" name="department_code" maxlength="12" class="form-control" value="<?= esc($edit['department_code'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Employee Name</label><input type="text" name="name" maxlength="500" class="form-control" required value="<?= esc($edit['name'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Description</label><input type="text" name="description" maxlength="500" class="form-control" value="<?= esc($edit['description'] ?? '') ?>"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><?= $edit ? 'Update' : 'Save' ?></button></div>
    </form>

    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead class="table-light"><tr><th>Employee ID</th><th>Site</th><th>Dept</th><th>Name</th><th>Description</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No employee yet.</td></tr><?php endif ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td class="fw-semibold"><?= esc($row['employee_code'] ?? '') ?></td>
                <td><?= esc($row['site_code'] ?? '') ?></td>
                <td><?= esc($row['department_code'] ?? '') ?></td>
                <td><?= esc($row['name'] ?? '') ?></td>
                <td><?= esc($row['description'] ?? '') ?></td>
                <td><span class="badge bg-<?= (int)($row['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int)($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                <td class="text-end"><a href="<?= site_url('cash-bank/employees?edit_id=' . (int)($row['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
</div></div>
<?= $this->endSection() ?>
