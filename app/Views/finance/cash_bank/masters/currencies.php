<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $edit = $editRow ?? null; ?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h4 class="card-title mb-1">Currency</h4><p class="text-muted mb-0">Master currency code, name, and rounding.</p></div>
        <a href="<?= site_url('cash-bank/currencies') ?>" class="btn btn-outline-secondary">Tambah Baru</a>
    </div>
    <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>
    <form method="get" action="<?= site_url('cash-bank/currencies') ?>" class="row g-3 border rounded bg-light p-3 mb-4">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= esc($edit['id'] ?? '') ?>">
        <div class="col-12"><h6 class="mb-0"><?= $edit ? 'Edit Currency' : 'Tambah Currency' ?></h6></div>
        <div class="col-md-2"><label class="form-label">Currency Code</label><input type="text" name="code" maxlength="6" class="form-control" required value="<?= esc($edit['code'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" maxlength="500" class="form-control" required value="<?= esc($edit['name'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Rounding</label><input type="number" step="0.0001" name="rounding" class="form-control" value="<?= esc($edit['rounding'] ?? '0') ?>" required></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><?= $edit ? 'Update' : 'Save' ?></button></div>
    </form>
    <div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Code</th><th>Name</th><th class="text-end">Rounding</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
    <?php if ($rows === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No currency yet.</td></tr><?php endif ?>
    <?php foreach ($rows as $row): ?><tr><td class="fw-semibold"><?= esc($row['code'] ?? '') ?></td><td><?= esc($row['name'] ?? '') ?></td><td class="text-end"><?= number_format((float) ($row['rounding'] ?? 0), 4) ?></td><td><span class="badge bg-<?= (int) ($row['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td><td class="text-end"><a href="<?= site_url('cash-bank/currencies?edit_id=' . (int)($row['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-primary">Edit</a></td></tr><?php endforeach ?>
    </tbody></table></div>
</div></div>
<?= $this->endSection() ?>
