<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card"><div class="card-body">
    <h4 class="card-title mb-1">Employee ID</h4>
    <p class="text-muted">Employee master for cash bank and operational reference.</p>
    <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>
    <form method="get" action="<?= site_url('cash-bank/employees') ?>" class="row g-3 border rounded bg-light p-3 mb-4">
        <input type="hidden" name="action" value="save">
        <div class="col-md-2"><label class="form-label">Employee ID</label><input type="text" name="employee_code" maxlength="12" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Site Code</label><select name="site_code" class="form-select select2"><?php foreach ($sites as $s): ?><?php $code=(string)($s['code']??$s['site_code']??''); ?><option value="<?= esc($code,'attr') ?>"><?= esc($code.' - '.($s['name']??'')) ?></option><?php endforeach ?></select></div>
        <div class="col-md-2"><label class="form-label">Dept Code</label><select name="department_code" class="form-select select2"><option value="">-- Department --</option><?php foreach ($departments as $d): ?><?php $code=(string)($d['code']??$d['department_code']??''); ?><option value="<?= esc($code,'attr') ?>"><?= esc($code.' - '.($d['name']??'')) ?></option><?php endforeach ?></select></div>
        <div class="col-md-3"><label class="form-label">Employee Name</label><input type="text" name="name" maxlength="500" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Description</label><input type="text" name="description" maxlength="500" class="form-control"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Save</button></div>
    </form>
    <div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Employee ID</th><th>Site</th><th>Dept</th><th>Name</th><th>Description</th><th>Status</th></tr></thead><tbody>
    <?php if ($rows === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No employee yet.</td></tr><?php endif ?>
    <?php foreach ($rows as $row): ?><tr><td class="fw-semibold"><?= esc($row['employee_code']??'') ?></td><td><?= esc($row['site_code']??'') ?></td><td><?= esc($row['department_code']??'') ?></td><td><?= esc($row['name']??'') ?></td><td><?= esc($row['description']??'') ?></td><td><span class="badge bg-<?= (int)($row['is_active']??0)===1?'success':'secondary' ?>"><?= (int)($row['is_active']??0)===1?'Active':'Inactive' ?></span></td></tr><?php endforeach ?>
    </tbody></table></div>
</div></div>
<script>document.addEventListener('DOMContentLoaded',function(){if(window.jQuery&&jQuery.fn.select2){jQuery('.select2').select2({width:'100%'});}});</script>
<?= $this->endSection() ?>
