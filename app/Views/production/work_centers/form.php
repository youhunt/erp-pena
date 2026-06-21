<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$workCenter ??= [];$machines ??= [];$costs ??= [];$isEdit=(bool)($isEdit??false);
$action ??= $isEdit ? site_url('production/work-centers/' . (int)($workCenter['id']??0)) : site_url('production/work-centers');
$val=static fn(string $f,mixed $d=''):string=>(string)old($f,$workCenter[$f]??$d);
$machineRows=$machines!==[]?$machines:[[]];$costRows=$costs!==[]?$costs:[[]];
?>
<form method="post" action="<?= esc($action,'attr') ?>">
    <?= csrf_field() ?>
    <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4"><div><h4 class="card-title mb-1"><?= esc($isEdit?'Edit Work Center':'Create Work Center') ?></h4><p class="text-muted mb-0">Define production machine capacity and cost.</p></div><a href="<?= $isEdit ? site_url('production/work-centers/' . (int)($workCenter['id']??0)) : site_url('production/work-centers') ?>" class="btn btn-light">Back</a></div>
        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">Site</label><select name="site_code" class="form-select" required><?php foreach ($sites as $site): ?><?php $code=$site['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('site_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($site['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Department</label><select name="department_code" class="form-select" required><?php foreach ($departments as $department): ?><?php $code=$department['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('department_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($department['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Warehouse</label><select name="warehouse_code" class="form-select" required><?php foreach ($warehouses as $warehouse): ?><?php $code=$warehouse['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('warehouse_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($warehouse['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Work Center</label><input name="work_center_code" class="form-control" required maxlength="12" value="<?= esc($val('work_center_code')) ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Machine</label><input name="machine_code" class="form-control" required maxlength="12" value="<?= esc($val('machine_code')) ?>"></div>
            <div class="col-md-9 mb-3"><label class="form-label">Description</label><input name="description" class="form-control" maxlength="300" value="<?= esc($val('description')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Speed</label><input type="number" step="0.001" name="speed" class="form-control" value="<?= esc($val('speed','0')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">% Capacity</label><input type="number" step="0.001" name="capacity_percent" class="form-control" value="<?= esc($val('capacity_percent','100')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Qty Labor</label><input type="number" step="0.000001" name="qty_labor" class="form-control" value="<?= esc($val('qty_labor','0')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Working Hour</label><input type="number" step="0.001" name="working_hour" class="form-control" value="<?= esc($val('working_hour','0')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Cost Type</label><input name="cost_type" class="form-control" value="<?= esc($val('cost_type','Labor')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Cost Amount</label><input type="number" step="0.01" name="cost_amount" class="form-control" value="<?= esc($val('cost_amount','0')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Cost UoM</label><input name="cost_uom" class="form-control" maxlength="4" value="<?= esc($val('cost_uom','Hour')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Max Length</label><input type="number" step="0.000001" name="max_length" class="form-control" value="<?= esc($val('max_length','0')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Length UoM</label><input name="length_uom" class="form-control" value="<?= esc($val('length_uom','CM')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Max Width</label><input type="number" step="0.000001" name="max_width" class="form-control" value="<?= esc($val('max_width','0')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Width UoM</label><input name="width_uom" class="form-control" value="<?= esc($val('width_uom','CM')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Max Height</label><input type="number" step="0.000001" name="max_height" class="form-control" value="<?= esc($val('max_height','0')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Height UoM</label><input name="height_uom" class="form-control" value="<?= esc($val('height_uom','CM')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Max Volume</label><input type="number" step="0.000001" name="max_volume" class="form-control" value="<?= esc($val('max_volume','0')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Volume UoM</label><input name="volume_uom" class="form-control" value="<?= esc($val('volume_uom','M3')) ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Active Date</label><input type="date" name="active_date" class="form-control" value="<?= esc($val('active_date',date('Y-m-d'))) ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Inactive Date</label><input type="date" name="inactive_date" class="form-control" value="<?= esc($val('inactive_date')) ?>"></div>
            <div class="col-md-12 mb-3"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="300" value="<?= esc($val('notes')) ?>"></div>
        </div>
        <hr><h5 class="font-size-15 mb-3">Machine Detail</h5>
        <div class="table-responsive mb-4"><table class="table table-bordered align-middle"><thead class="table-light"><tr><th style="width:80px">No</th><th>Machine</th><th>Notes</th><th class="text-end">Speed</th><th class="text-end">Capacity</th><th class="text-end">Labor</th><th class="text-end">Hour</th></tr></thead><tbody>
        <?php foreach ($machineRows as $i=>$m): ?><tr><td><input type="number" name="machine_no[]" class="form-control" value="<?= esc($m['no']??10) ?>"></td><td><input name="machine[]" class="form-control" maxlength="12" value="<?= esc($m['machine']??$val('machine_code')) ?>"></td><td><input name="machine_notes[]" class="form-control" maxlength="300" value="<?= esc($m['notes1']??$val('notes')) ?>"></td><td><input type="number" step="0.001" name="machine_speed[]" class="form-control text-end" value="<?= esc($m['speed']??$val('speed','0')) ?>"></td><td><input type="number" step="0.001" name="machine_capacity[]" class="form-control text-end" value="<?= esc($m['capacity']??$val('capacity_percent','100')) ?>"></td><td><input type="number" step="0.000001" name="machine_qtylabor[]" class="form-control text-end" value="<?= esc($m['qtylabor']??$val('qty_labor','0')) ?>"></td><td><input type="number" step="0.001" name="machine_workhour[]" class="form-control text-end" value="<?= esc($m['workhour']??$val('working_hour','0')) ?>"></td></tr><?php endforeach ?>
        </tbody></table></div>
        <h5 class="font-size-15 mb-3">Cost Detail</h5><div class="table-responsive mb-4"><table class="table table-bordered align-middle"><thead class="table-light"><tr><th>Cost Type</th><th class="text-end">Amount</th><th>UoM</th><th>Notes</th></tr></thead><tbody>
        <?php foreach ($costRows as $i=>$c): ?><tr><td><input name="costtype[]" class="form-control" maxlength="12" value="<?= esc($c['costtype']??$val('cost_type','Labor')) ?>"></td><td><input type="number" step="0.01" name="costamount[]" class="form-control text-end" value="<?= esc($c['costamount']??$val('cost_amount','0')) ?>"></td><td><input name="costuom[]" class="form-control" maxlength="4" value="<?= esc($c['costuom']??$val('cost_uom','Hour')) ?>"></td><td><input name="cost_notes[]" class="form-control" maxlength="30" value="<?= esc($c['notes2']??'') ?>"></td></tr><?php endforeach ?>
        </tbody></table></div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> <?= $isEdit?'Update Work Center':'Save Work Center' ?></button><a class="btn btn-light" href="<?= $isEdit ? site_url('production/work-centers/' . (int)($workCenter['id']??0)) : site_url('production/work-centers') ?>">Cancel</a></div>
    </div></div>
</form>
<?= $this->endSection() ?>
