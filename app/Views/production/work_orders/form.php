<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$workOrder ??= [];$components ??= [];$routings ??= [];$isEdit=(bool)($isEdit??false);
$action ??= $isEdit ? site_url('production/work-orders/' . (int)($workOrder['id']??0)) : site_url('production/work-orders');
$val=static fn(string $f,mixed $d=''):string=>(string)old($f,$workOrder[$f]??$d);
$componentRows=$components!==[]?$components:array_fill(0,3,[]);
$routingRows=$routings!==[]?$routings:array_fill(0,3,[]);
?>
<form method="post" action="<?= esc($action,'attr') ?>">
    <?= csrf_field() ?>
    <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4"><div><h4 class="card-title mb-1"><?= esc($isEdit?'Edit Work Order':'Create Work Order') ?></h4><p class="text-muted mb-0"><?= $isEdit ? 'Edit header, component, and routing lines while WO is draft.' : 'BOM and Routing lines are generated after save.' ?></p></div><a href="<?= $isEdit ? site_url('production/work-orders/' . (int)($workOrder['id']??0)) : site_url('production/work-orders') ?>" class="btn btn-light">Back</a></div>
        <div class="row">
            <div class="col-md-2 mb-3"><label class="form-label">WO Code</label><input name="wo_code" class="form-control" value="<?= esc($val('wo_code','WO')) ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">WO No</label><input name="wo_no" class="form-control" required value="<?= esc($val('wo_no','WO-' . date('Ymd-His'))) ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">WO Date</label><input type="date" name="wo_date" class="form-control" required value="<?= esc($val('wo_date',date('Y-m-d'))) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Site</label><select name="site_code" class="form-select" required><?php foreach ($sites as $site): ?><?php $code=$site['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('site_code')===$code?'selected':'' ?>><?= esc($code) ?></option><?php endforeach ?></select></div>
            <div class="col-md-2 mb-3"><label class="form-label">Dept</label><select name="department_code" class="form-select" required><?php foreach ($departments as $dept): ?><?php $code=$dept['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('department_code')===$code?'selected':'' ?>><?= esc($code) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Item Parent</label><select name="parent_item_code" class="form-select" required><?php foreach ($items as $item): ?><?php $code=$item['item_code']??$item['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('parent_item_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Work Center</label><select name="work_center_code" class="form-select"><option value="">Auto from routing</option><?php foreach ($workCenters as $wc): ?><?php $code=$wc['work_center_code']??''; ?><option value="<?= esc($code) ?>" <?= $val('work_center_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($wc['description'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-2 mb-3"><label class="form-label">Warehouse</label><select name="warehouse_code" class="form-select"><option value="">From BOM</option><?php foreach ($warehouses as $wh): ?><?php $code=$wh['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('warehouse_code')===$code?'selected':'' ?>><?= esc($code) ?></option><?php endforeach ?></select></div>
            <div class="col-md-2 mb-3"><label class="form-label">Qty WO</label><input type="number" step="0.000001" name="wo_qty" class="form-control" required value="<?= esc($val('wo_qty','1')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">UoM</label><input name="uom_code" class="form-control" value="<?= esc($val('uom_code','PCS')) ?>"></div>
            <div class="col-md-12 mb-3"><label class="form-label">Description</label><input name="description" class="form-control" maxlength="500" value="<?= esc($val('description')) ?>"></div>
        </div>
        <?php if (! $isEdit): ?><div class="alert alert-info mb-0">Pastikan BOM dan Routing untuk item parent sudah dibuat. Saat WO disimpan, sistem akan meng-copy komponen BOM dan routing operasi ke Work Order.</div><?php endif ?>
    </div></div>
    <?php if ($isEdit): ?>
    <div class="card"><div class="card-body"><h4 class="card-title mb-3">Components</h4><div class="table-responsive"><table class="table table-bordered align-middle"><thead class="table-light"><tr><th>Line</th><th>Item</th><th>Name</th><th class="text-end">Qty</th><th>UoM</th><th>Warehouse</th></tr></thead><tbody>
    <?php foreach($componentRows as $i=>$c): ?><tr><td><input name="component_line_no[]" class="form-control" value="<?= esc($c['line_no']??(($i+1)*10)) ?>"></td><td><input name="component_item_code[]" class="form-control" value="<?= esc($c['component_item_code']??'') ?>"></td><td><input name="component_item_name[]" class="form-control" value="<?= esc($c['component_item_name']??'') ?>"></td><td><input type="number" step="0.000001" name="component_qty_used[]" class="form-control text-end" value="<?= esc($c['qty_used']??'0') ?>"></td><td><input name="component_uom_code[]" class="form-control" value="<?= esc($c['uom_code']??'PCS') ?>"></td><td><input name="component_warehouse_code[]" class="form-control" value="<?= esc($c['warehouse_code']??'') ?>"></td></tr><?php endforeach ?>
    </tbody></table></div></div></div>
    <div class="card"><div class="card-body"><h4 class="card-title mb-3">Routing</h4><div class="table-responsive"><table class="table table-bordered align-middle"><thead class="table-light"><tr><th>Line</th><th>Name</th><th>Work Center</th><th class="text-end">Hour</th><th>UoM</th></tr></thead><tbody>
    <?php foreach($routingRows as $i=>$r): ?><tr><td><input name="routing_line_no[]" class="form-control" value="<?= esc($r['line_no']??(($i+1)*10)) ?>"></td><td><input name="wo_routing_name[]" class="form-control" value="<?= esc($r['routing_name']??'') ?>"></td><td><select name="wo_work_center_code[]" class="form-select"><option value="">Select</option><?php foreach($workCenters as $wc): ?><?php $code=$wc['work_center_code']??''; ?><option value="<?= esc($code) ?>" <?= ($r['work_center_code']??'')===$code?'selected':'' ?>><?= esc($code) ?></option><?php endforeach ?></select></td><td><input type="number" step="0.000001" name="wo_hour_qty[]" class="form-control text-end" value="<?= esc($r['hour_qty']??'0') ?>"></td><td><input name="wo_route_uom[]" class="form-control" value="<?= esc($r['uom_code']??'Hour') ?>"></td></tr><?php endforeach ?>
    </tbody></table></div></div></div>
    <?php endif ?>
    <div class="card"><div class="card-body"><div class="d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> <?= $isEdit?'Update Work Order':'Save Work Order' ?></button><a class="btn btn-light" href="<?= $isEdit ? site_url('production/work-orders/' . (int)($workOrder['id']??0)) : site_url('production/work-orders') ?>">Cancel</a></div></div></div>
</form>
<?= $this->endSection() ?>
