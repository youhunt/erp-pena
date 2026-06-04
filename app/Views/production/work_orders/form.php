<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('production/work-orders') ?>">
    <?= csrf_field() ?>
    <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4"><div><h4 class="card-title mb-1">Create Work Order</h4><p class="text-muted mb-0">BOM and Routing lines are generated after save.</p></div><a href="<?= site_url('production/work-orders') ?>" class="btn btn-light">Back</a></div>
        <div class="row">
            <div class="col-md-2 mb-3"><label class="form-label">WO Code</label><input name="wo_code" class="form-control" value="<?= esc(old('wo_code', 'WO')) ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">WO No</label><input name="wo_no" class="form-control" required value="<?= esc(old('wo_no', 'WO-' . date('Ymd-His'))) ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">WO Date</label><input type="date" name="wo_date" class="form-control" required value="<?= esc(old('wo_date', date('Y-m-d'))) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Site</label><select name="site_code" class="form-select" required><?php foreach ($sites as $site): ?><option value="<?= esc($site['code'] ?? '') ?>"><?= esc($site['code'] ?? '') ?></option><?php endforeach ?></select></div>
            <div class="col-md-2 mb-3"><label class="form-label">Dept</label><select name="department_code" class="form-select" required><?php foreach ($departments as $dept): ?><option value="<?= esc($dept['code'] ?? '') ?>"><?= esc($dept['code'] ?? '') ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Item Parent</label><select name="parent_item_code" class="form-select" required><?php foreach ($items as $item): ?><?php $code = $item['item_code'] ?? $item['code'] ?? ''; ?><option value="<?= esc($code) ?>"><?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Work Center</label><select name="work_center_code" class="form-select"><option value="">Auto from routing</option><?php foreach ($workCenters as $wc): ?><option value="<?= esc($wc['work_center_code']) ?>"><?= esc($wc['work_center_code'] . ' - ' . ($wc['description'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-2 mb-3"><label class="form-label">Warehouse</label><select name="warehouse_code" class="form-select"><option value="">From BOM</option><?php foreach ($warehouses as $wh): ?><option value="<?= esc($wh['code'] ?? '') ?>"><?= esc($wh['code'] ?? '') ?></option><?php endforeach ?></select></div>
            <div class="col-md-2 mb-3"><label class="form-label">Qty WO</label><input type="number" step="0.000001" name="wo_qty" class="form-control" required value="<?= esc(old('wo_qty', '1')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Std Qty Finished</label><input class="form-control" value="Auto = Qty WO" disabled></div>
            <div class="col-md-12 mb-3"><label class="form-label">Description</label><input name="description" class="form-control" maxlength="500" value="<?= esc(old('description')) ?>"></div>
        </div>
        <div class="alert alert-info mb-4">Pastikan BOM dan Routing untuk item parent sudah dibuat. Saat WO disimpan, sistem akan meng-copy komponen BOM dan routing operasi ke Work Order.</div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> Save Work Order</button><a class="btn btn-light" href="<?= site_url('production/work-orders') ?>">Cancel</a></div>
    </div></div>
</form>
<?= $this->endSection() ?>
