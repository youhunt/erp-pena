<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$edit = $editRow ?? null;
$formMode = $edit !== null || (string) service('request')->getGet('mode') === 'form';
?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="card-title mb-1">Employee ID</h4>
            <p class="text-muted mb-0">Manage employee master data.</p>
        </div>
        <?php if ($formMode): ?>
            <a href="<?= site_url('cash-bank/employees') ?>" class="btn btn-light">Back</a>
        <?php else: ?>
            <a href="<?= site_url('cash-bank/employees?mode=form') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New</a>
        <?php endif ?>
    </div>

    <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

    <?php if ($formMode): ?>
        <form method="get" action="<?= site_url('cash-bank/employees') ?>" class="row g-3">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= esc($edit['id'] ?? '') ?>">
            <div class="col-12"><div class="alert alert-info"><strong><?= $edit ? 'Edit Employee' : 'Create Employee' ?></strong></div></div>
            <div class="col-md-3"><label class="form-label">Employee ID</label><input type="text" name="employee_code" maxlength="12" class="form-control" required value="<?= esc($edit['employee_code'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label">Site Code</label><input type="text" name="site_code" maxlength="12" class="form-control" value="<?= esc($edit['site_code'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label">Dept Code</label><input type="text" name="department_code" maxlength="12" class="form-control" value="<?= esc($edit['department_code'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label">Employee Name</label><input type="text" name="name" maxlength="500" class="form-control" required value="<?= esc($edit['name'] ?? '') ?>"></div>
            <div class="col-md-12"><label class="form-label">Description</label><input type="text" name="description" maxlength="500" class="form-control" value="<?= esc($edit['description'] ?? '') ?>"></div>
            <div class="col-12 text-end"><a href="<?= site_url('cash-bank/employees') ?>" class="btn btn-light">Cancel</a> <button class="btn btn-primary" type="submit">Save</button></div>
        </form>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-nowrap table-hover align-middle mb-0"><thead class="table-light"><tr><th>Employee ID</th><th>Site</th><th>Dept</th><th>Name</th><th>Description</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
        <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No employee yet.</td></tr><?php endif ?>
        <?php foreach ($rows as $row): ?><tr><td class="fw-semibold"><?= esc($row['employee_code'] ?? '') ?></td><td><?= esc($row['site_code'] ?? '') ?></td><td><?= esc($row['department_code'] ?? '') ?></td><td><?= esc($row['name'] ?? '') ?></td><td><?= esc($row['description'] ?? '') ?></td><td><span class="badge bg-<?= (int)($row['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int)($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td><td class="text-end"><a href="<?= site_url('cash-bank/employees?edit_id=' . (int)($row['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-edit"></i></a></td></tr><?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</div></div>
<?= $this->endSection() ?>
