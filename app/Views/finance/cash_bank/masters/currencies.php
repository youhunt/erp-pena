<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$edit = $editRow ?? null;
$formMode = $edit !== null || (string) service('request')->getGet('mode') === 'form';
?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="card-title mb-1">Currency</h4><p class="text-muted mb-0">Manage currency master data.</p></div>
        <?php if ($formMode): ?><a href="<?= site_url('cash-bank/currencies') ?>" class="btn btn-light">Back</a><?php else: ?><a href="<?= site_url('cash-bank/currencies?mode=form') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New</a><?php endif ?>
    </div>
    <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

    <?php if ($formMode): ?>
    <form method="get" action="<?= site_url('cash-bank/currencies') ?>" class="row g-3">
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= esc($edit['id'] ?? '') ?>">
        <div class="col-12"><div class="alert alert-info"><strong><?= $edit ? 'Edit Currency' : 'Create Currency' ?></strong></div></div>
        <div class="col-md-3"><label class="form-label">Currency Code</label><input type="text" name="code" maxlength="6" class="form-control" required value="<?= esc($edit['code'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" maxlength="500" class="form-control" required value="<?= esc($edit['name'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Rounding</label><input type="number" step="0.0001" name="rounding" class="form-control" value="<?= esc($edit['rounding'] ?? '0') ?>" required></div>
        <div class="col-12 text-end"><a href="<?= site_url('cash-bank/currencies') ?>" class="btn btn-light">Cancel</a> <button class="btn btn-primary" type="submit">Save</button></div>
    </form>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-nowrap table-hover align-middle mb-0"><thead class="table-light"><tr><th>Code</th><th>Name</th><th class="text-end">Rounding</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
    <?php if ($rows === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No currency yet.</td></tr><?php endif ?>
    <?php foreach ($rows as $row): ?><tr><td class="fw-semibold"><?= esc($row['code'] ?? '') ?></td><td><?= esc($row['name'] ?? '') ?></td><td class="text-end"><?= number_format((float) ($row['rounding'] ?? 0), 4) ?></td><td><span class="badge bg-<?= (int) ($row['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td><td class="text-end"><a href="<?= site_url('cash-bank/currencies?edit_id=' . (int)($row['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-edit"></i></a></td></tr><?php endforeach ?>
    </tbody></table></div>
    <?php endif ?>
</div></div>
<?= $this->endSection() ?>
