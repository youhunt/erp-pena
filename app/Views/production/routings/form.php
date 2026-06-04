<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('production/routings') ?>">
    <?= csrf_field() ?>
    <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4"><div><h4 class="card-title mb-1">Create Routing</h4><p class="text-muted mb-0">Define operation sequence and work center usage.</p></div><a href="<?= site_url('production/routings') ?>" class="btn btn-light">Back</a></div>
        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">Item Code</label><select name="item_code" class="form-select" required><?php foreach ($items as $item): ?><?php $code = $item['item_code'] ?? $item['code'] ?? ''; ?><option value="<?= esc($code) ?>"><?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Site</label><select name="site_code" class="form-select" required><?php foreach ($sites as $site): ?><option value="<?= esc($site['code'] ?? '') ?>"><?= esc(($site['code'] ?? '') . ' - ' . ($site['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Department</label><select name="department_code" class="form-select" required><?php foreach ($departments as $department): ?><option value="<?= esc($department['code'] ?? '') ?>"><?= esc(($department['code'] ?? '') . ' - ' . ($department['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Warehouse</label><select name="warehouse_code" class="form-select"><option value="">No Warehouse</option><?php foreach ($warehouses as $warehouse): ?><option value="<?= esc($warehouse['code'] ?? '') ?>"><?= esc(($warehouse['code'] ?? '') . ' - ' . ($warehouse['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-12 mb-3"><label class="form-label">Description</label><input name="description" class="form-control" maxlength="300"></div>
        </div>
    </div></div>
    <div class="card"><div class="card-body">
        <h4 class="card-title mb-3">Route Lines</h4>
        <div class="table-responsive"><table class="table table-nowrap align-middle mb-0">
            <thead class="table-light"><tr><th>Route No</th><th>Name</th><th>Work Center</th><th>Type</th><th class="text-end">Hour</th><th>Hour UoM</th><th class="text-end">Std Speed</th><th>Speed UoM</th><th>Notes</th></tr></thead>
            <tbody><?php for ($i = 0; $i < 5; $i++): ?><tr>
                <td><input name="route_no[]" class="form-control" value="<?= ($i + 1) * 10 ?>"></td>
                <td><input name="routing_name[]" class="form-control"></td>
                <td><select name="work_center_code[]" class="form-select"><option value="">Select</option><?php foreach ($workCenters as $wc): ?><option value="<?= esc($wc['work_center_code']) ?>"><?= esc($wc['work_center_code'] . ' - ' . ($wc['description'] ?? '')) ?></option><?php endforeach ?></select></td>
                <td><input name="operation_type[]" class="form-control" value="process"></td>
                <td><input type="number" step="0.000001" name="hour_qty[]" class="form-control text-end" value="<?= $i === 0 ? '1' : '0' ?>"></td>
                <td><input name="hour_uom[]" class="form-control" value="Hour"></td>
                <td><input type="number" step="0.000001" name="std_speed[]" class="form-control text-end" value="0"></td>
                <td><input name="speed_uom[]" class="form-control" value="Unit/Hour"></td>
                <td><input name="route_notes[]" class="form-control"></td>
            </tr><?php endfor ?></tbody>
        </table></div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> Save Routing</button><a class="btn btn-light" href="<?= site_url('production/routings') ?>">Cancel</a></div>
    </div></div>
</form>
<?= $this->endSection() ?>
