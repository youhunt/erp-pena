<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$edit = $editRow ?? null;
$formMode = $edit !== null || (string) service('request')->getGet('mode') === 'form';
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">Cash Bank ID</h4>
                <p class="text-muted mb-0">Manage cash bank master data.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($formMode): ?>
                    <a href="<?= site_url('cash-bank/accounts') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>
                <?php else: ?>
                    <a href="<?= site_url('cash-bank/accounts?mode=form') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New</a>
                    <a href="<?= site_url('cash-bank/bank-entries/new') ?>" class="btn btn-outline-secondary">Bank Entry</a>
                <?php endif ?>
            </div>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <?php if ($formMode): ?>
            <form method="get" action="<?= site_url('cash-bank/accounts') ?>" class="row g-3">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= esc($edit['id'] ?? '') ?>">
                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <strong><?= $edit ? 'Edit Cash Bank ID' : 'Create Cash Bank ID' ?></strong><br>
                        Fill required bank master fields, then save.
                    </div>
                </div>
                <div class="col-md-4"><label class="form-label">Bank Branch <span class="text-danger">*</span></label><input type="text" name="bank_branch" maxlength="50" class="form-control" required value="<?= esc($edit['bank_branch'] ?? $edit['cash_bank_code'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Bank Code <span class="text-danger">*</span></label><input type="text" name="bank_code" maxlength="50" class="form-control" required value="<?= esc($edit['bank_code'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Bank Curr <span class="text-danger">*</span></label><select name="currency_code" class="form-select select2" required><?php $selectedCurrency = (string)($edit['currency_code'] ?? 'IDR'); ?><?php foreach ($currencies as $c): ?><?php $code=(string)($c['code']??''); ?><option value="<?= esc($code, 'attr') ?>" <?= $selectedCurrency === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($c['name'] ?? '')) ?></option><?php endforeach ?></select></div>
                <div class="col-md-6"><label class="form-label">Bank Name <span class="text-danger">*</span></label><input type="text" name="bank_name" maxlength="500" class="form-control" required value="<?= esc($edit['cash_bank_name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Bank Account <span class="text-danger">*</span></label><input type="text" name="bank_account" maxlength="50" class="form-control" required value="<?= esc($edit['bank_account'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">PIC</label><input type="text" name="pic" maxlength="100" class="form-control" value="<?= esc($edit['pic'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" maxlength="20" class="form-control" value="<?= esc($edit['phone'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Address</label><input type="text" name="address" maxlength="100" class="form-control" value="<?= esc($edit['address'] ?? '') ?>"></div>
                <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                    <a href="<?= site_url('cash-bank/accounts') ?>" class="btn btn-light">Cancel</a>
                    <button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> <?= $edit ? 'Update' : 'Save' ?></button>
                </div>
            </form>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-nowrap table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Branch</th><th>Code</th><th>Currency</th><th>Name</th><th>Account</th><th>PIC</th><th class="text-end">Balance</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php if ($accounts === []): ?><tr><td colspan="9" class="text-center text-muted py-4">No cash bank account yet.</td></tr><?php endif ?>
                    <?php foreach ($accounts as $account): ?>
                        <?php $active = (int) ($account['is_active'] ?? 1) === 1; ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($account['bank_branch'] ?? $account['cash_bank_code'] ?? '-') ?></td>
                            <td><?= esc($account['bank_code'] ?? '-') ?></td>
                            <td><?= esc($account['currency_code'] ?? '-') ?></td>
                            <td><?= esc($account['cash_bank_name'] ?? '-') ?></td>
                            <td><?= esc($account['bank_account'] ?? '-') ?></td>
                            <td><?= esc($account['pic'] ?? '-') ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float) ($account['current_balance'] ?? 0), 2) ?></td>
                            <td><span class="badge bg-<?= $active ? 'success' : 'secondary' ?>"><?= $active ? 'Active' : 'Inactive' ?></span></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('cash-bank/accounts?edit_id=' . (int)($account['id'] ?? 0)) ?>" title="Edit"><i class="bx bx-edit"></i></a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){if(window.jQuery&&jQuery.fn.select2){jQuery('.select2').select2({width:'100%'});}});</script>
<?= $this->endSection() ?>
