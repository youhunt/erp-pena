<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$edit = $editRow ?? null;
$formMode = $edit !== null || (string) service('request')->getGet('mode') === 'form';
?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="card-title mb-1">Rate Master</h4><p class="text-muted mb-0">Manage currency exchange rate.</p></div>
        <?php if ($formMode): ?><a href="<?= site_url('cash-bank/rates') ?>" class="btn btn-light">Back</a><?php else: ?><a href="<?= site_url('cash-bank/rates?mode=form') ?>" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New</a><?php endif ?>
    </div>
    <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

    <?php if ($formMode): ?>
    <form method="get" action="<?= site_url('cash-bank/rates') ?>" class="row g-3">
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= esc($edit['id'] ?? '') ?>">
        <div class="col-12"><div class="alert alert-info"><strong><?= $edit ? 'Edit Rate' : 'Create Rate' ?></strong></div></div>
        <div class="col-md-2"><label class="form-label">Rate Type</label><input type="text" name="rate_type" maxlength="12" class="form-control" required value="<?= esc($edit['rate_type'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">From Currency</label><select name="from_currency" class="form-select select2" required><?php $from=(string)($edit['from_currency']??''); ?><?php foreach ($currencies as $c): ?><?php $code=(string)($c['code']??''); ?><option value="<?= esc($code,'attr') ?>" <?= $from===$code?'selected':'' ?>><?= esc($code) ?></option><?php endforeach ?></select></div>
        <div class="col-md-2"><label class="form-label">To Currency</label><select name="to_currency" class="form-select select2" required><?php $to=(string)($edit['to_currency']??'IDR'); ?><?php foreach ($currencies as $c): ?><?php $code=(string)($c['code']??''); ?><option value="<?= esc($code,'attr') ?>" <?= $to===$code?'selected':'' ?>><?= esc($code) ?></option><?php endforeach ?></select></div>
        <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="rate_date" class="form-control" value="<?= esc($edit['rate_date'] ?? date('Y-m-d')) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Amount</label><input type="number" step="0.000000000001" name="amount" class="form-control" required value="<?= esc($edit['amount'] ?? '') ?>"></div>
        <div class="col-12 text-end"><a href="<?= site_url('cash-bank/rates') ?>" class="btn btn-light">Cancel</a> <button class="btn btn-primary" type="submit">Save</button></div>
    </form>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-nowrap table-hover align-middle mb-0"><thead class="table-light"><tr><th>Type</th><th>From</th><th>To</th><th>Date</th><th class="text-end">Amount</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
    <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No rate yet.</td></tr><?php endif ?>
    <?php foreach ($rows as $row): ?><tr><td class="fw-semibold"><?= esc($row['rate_type']??'') ?></td><td><?= esc($row['from_currency']??'') ?></td><td><?= esc($row['to_currency']??'') ?></td><td><?= esc($row['rate_date']??'') ?></td><td class="text-end"><?= number_format((float)($row['amount']??0),12) ?></td><td><span class="badge bg-<?= (int)($row['is_active']??0)===1?'success':'secondary' ?>"><?= (int)($row['is_active']??0)===1?'Active':'Inactive' ?></span></td><td class="text-end"><a href="<?= site_url('cash-bank/rates?edit_id='.(int)($row['id']??0)) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-edit"></i></a></td></tr><?php endforeach ?>
    </tbody></table></div>
    <?php endif ?>
</div></div>
<script>document.addEventListener('DOMContentLoaded',function(){if(window.jQuery&&jQuery.fn.select2){jQuery('.select2').select2({width:'100%'});}});</script>
<?= $this->endSection() ?>
