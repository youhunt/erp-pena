<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$routing ??= [];$lines ??= [];$isEdit=(bool)($isEdit??false);
$action ??= $isEdit ? site_url('production/routings/' . (int)($routing['id']??0)) : site_url('production/routings');
$val=static fn(string $f,mixed $d=''):string=>(string)old($f,$routing[$f]??$d);
$lineRows=$lines!==[]?$lines:array_fill(0,5,[]);
?>
<form method="post" action="<?= esc($action,'attr') ?>">
    <?= csrf_field() ?>
    <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4"><div><h4 class="card-title mb-1"><?= esc($isEdit?'Edit Routing':'Create Routing') ?></h4><p class="text-muted mb-0">Define operation sequence and work center usage.</p></div><a href="<?= $isEdit ? site_url('production/routings/' . (int)($routing['id']??0)) : site_url('production/routings') ?>" class="btn btn-light">Back</a></div>
        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">Item Code</label><select name="item_code" class="form-select" required><?php foreach ($items as $item): ?><?php $code=$item['item_code']??$item['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('item_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Site</label><select name="site_code" class="form-select" required><?php foreach ($sites as $site): ?><?php $code=$site['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('site_code')===$code?'selected':'' ?>><?= esc(($code) . ' - ' . ($site['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Department</label><select name="department_code" class="form-select" required><?php foreach ($departments as $department): ?><?php $code=$department['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('department_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($department['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Warehouse</label><select name="warehouse_code" class="form-select"><option value="">No Warehouse</option><?php foreach ($warehouses as $warehouse): ?><?php $code=$warehouse['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('warehouse_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($warehouse['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-12 mb-3"><label class="form-label">Description</label><input name="description" class="form-control" maxlength="300" value="<?= esc($val('description')) ?>"></div>
        </div>
    </div></div>
    <div class="card"><div class="card-body">
        <h4 class="card-title mb-3">Route Lines</h4>
        <div class="table-responsive"><table class="table table-nowrap align-middle mb-0">
            <thead class="table-light"><tr><th>Route No</th><th>Name</th><th>Work Center</th><th>Type</th><th class="text-end">Hour</th><th>Hour UoM</th><th class="text-end">Std Speed</th><th>Speed UoM</th><th>Notes</th></tr></thead>
            <tbody><?php foreach ($lineRows as $i=>$line): ?><tr>
                <td><input name="route_no[]" class="form-control" value="<?= esc($line['route_no']??(($i+1)*10)) ?>"></td>
                <td><input name="routing_name[]" class="form-control" value="<?= esc($line['routing_name']??'') ?>"></td>
                <td><select name="work_center_code[]" class="form-select"><option value="">Select</option><?php foreach ($workCenters as $wc): ?><?php $code=$wc['work_center_code']??''; ?><option value="<?= esc($code) ?>" <?= ($line['work_center_code']??'')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($wc['description'] ?? '')) ?></option><?php endforeach ?></select></td>
                <td><input name="operation_type[]" class="form-control" value="<?= esc($line['operation_type']??'process') ?>"></td>
                <td><input type="number" step="0.000001" name="hour_qty[]" class="form-control text-end" value="<?= esc($line['hour_qty']??($i===0?'1':'0')) ?>"></td>
                <td><input name="hour_uom[]" class="form-control" value="<?= esc($line['hour_uom']??'Hour') ?>"></td>
                <td><input type="number" step="0.000001" name="std_speed[]" class="form-control text-end" value="<?= esc($line['std_speed']??'0') ?>"></td>
                <td><input name="speed_uom[]" class="form-control" value="<?= esc($line['speed_uom']??'Unit/Hour') ?>"></td>
                <td><input name="route_notes[]" class="form-control" value="<?= esc($line['notes']??'') ?>"></td>
            </tr><?php endforeach ?></tbody>
        </table></div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> <?= $isEdit?'Update Routing':'Save Routing' ?></button><a class="btn btn-light" href="<?= $isEdit ? site_url('production/routings/' . (int)($routing['id']??0)) : site_url('production/routings') ?>">Cancel</a></div>
    </div></div>
</form>
<?= $this->endSection() ?>
