<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card"><div class="card-body">
    <h4 class="card-title mb-1">Rate Master</h4>
    <p class="text-muted">Currency exchange rate by type, pair, date, and amount.</p>
    <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>
    <form method="get" action="<?= site_url('cash-bank/rates') ?>" class="row g-3 border rounded bg-light p-3 mb-4">
        <input type="hidden" name="action" value="save">
        <div class="col-md-2"><label class="form-label">Rate Type</label><input type="text" name="rate_type" maxlength="12" class="form-control" required placeholder="BI"></div>
        <div class="col-md-2"><label class="form-label">From Currency</label><select name="from_currency" class="form-select select2" required><?php foreach ($currencies as $c): ?><option value="<?= esc($c['code'],'attr') ?>"><?= esc($c['code']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-2"><label class="form-label">To Currency</label><select name="to_currency" class="form-select select2" required><?php foreach ($currencies as $c): ?><option value="<?= esc($c['code'],'attr') ?>" <?= ($c['code']??'')==='IDR'?'selected':'' ?>><?= esc($c['code']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-2"><label class="form-label">Date</label><input type="date" name="rate_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-2"><label class="form-label">Amount</label><input type="number" step="0.000000000001" name="amount" class="form-control" required></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Save</button></div>
    </form>
    <div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Type</th><th>From</th><th>To</th><th>Date</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody>
    <?php if ($rows === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No rate yet.</td></tr><?php endif ?>
    <?php foreach ($rows as $row): ?><tr><td class="fw-semibold"><?= esc($row['rate_type']??'') ?></td><td><?= esc($row['from_currency']??'') ?></td><td><?= esc($row['to_currency']??'') ?></td><td><?= esc($row['rate_date']??'') ?></td><td class="text-end"><?= number_format((float)($row['amount']??0),12) ?></td><td><span class="badge bg-<?= (int)($row['is_active']??0)===1?'success':'secondary' ?>"><?= (int)($row['is_active']??0)===1?'Active':'Inactive' ?></span></td></tr><?php endforeach ?>
    </tbody></table></div>
</div></div>
<script>document.addEventListener('DOMContentLoaded',function(){if(window.jQuery&&jQuery.fn.select2){jQuery('.select2').select2({width:'100%'});}});</script>
<?= $this->endSection() ?>
