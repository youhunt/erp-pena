<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('production/boms') ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><h4 class="card-title mb-1">Create BOM</h4><p class="text-muted mb-0">Define parent item, batch quantity, and child components.</p></div>
                <a href="<?= site_url('production/boms') ?>" class="btn btn-light">Back</a>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">Site</label><select name="site_code" class="form-select" required><?php foreach ($sites as $site): ?><option value="<?= esc($site['code'] ?? '') ?>"><?= esc(($site['code'] ?? '') . ' - ' . ($site['name'] ?? '')) ?></option><?php endforeach ?></select></div>
                <div class="col-md-3 mb-3"><label class="form-label">Department</label><select name="department_code" class="form-select" required><?php foreach ($departments as $department): ?><option value="<?= esc($department['code'] ?? '') ?>"><?= esc(($department['code'] ?? '') . ' - ' . ($department['name'] ?? '')) ?></option><?php endforeach ?></select></div>
                <div class="col-md-3 mb-3"><label class="form-label">Warehouse</label><select name="warehouse_code" class="form-select"><option value="">No Warehouse</option><?php foreach ($warehouses as $warehouse): ?><option value="<?= esc($warehouse['code'] ?? '') ?>"><?= esc(($warehouse['code'] ?? '') . ' - ' . ($warehouse['name'] ?? '')) ?></option><?php endforeach ?></select></div>
                <div class="col-md-3 mb-3"><label class="form-label">Parent Item</label><select name="parent_item_code" class="form-select" required><?php foreach ($items as $item): ?><?php $code = $item['item_code'] ?? $item['code'] ?? ''; ?><option value="<?= esc($code) ?>"><?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '')) ?></option><?php endforeach ?></select></div>
                <div class="col-md-2 mb-3"><label class="form-label">Type</label><input name="bom_type" class="form-control" value="<?= esc(old('bom_type', 'standard')) ?>"></div>
                <div class="col-md-2 mb-3"><label class="form-label">Qty/Batch</label><input type="number" step="0.000001" name="qty_batch" class="form-control" required value="<?= esc(old('qty_batch', '1')) ?>"></div>
                <div class="col-md-2 mb-3"><label class="form-label">UoM</label><select name="uom_code" class="form-select" required><?php foreach ($uoms as $uom): ?><option value="<?= esc($uom['code'] ?? '') ?>"><?= esc($uom['code'] ?? '') ?></option><?php endforeach ?></select></div>
                <div class="col-md-2 mb-3"><label class="form-label">% Ratio</label><input type="number" step="0.000001" name="ratio_percent" class="form-control" value="<?= esc(old('ratio_percent', '100')) ?>"></div>
                <div class="col-md-2 mb-3"><label class="form-label">Active Date</label><input type="datetime-local" name="active_date" class="form-control" value="<?= esc(old('active_date')) ?>"></div>
                <div class="col-md-2 mb-3"><label class="form-label">Inactive Date</label><input type="datetime-local" name="inactive_date" class="form-control" value="<?= esc(old('inactive_date')) ?>"></div>
                <div class="col-md-12 mb-3"><label class="form-label">Description</label><input name="description" class="form-control" maxlength="500" value="<?= esc(old('description')) ?>"></div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-3">Child Components</h4>
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light"><tr><th>No</th><th>Child Item</th><th>Type</th><th class="text-end">Qty Used</th><th>UoM</th><th class="text-end">Factor</th><th>Description</th></tr></thead>
                    <tbody>
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <tr>
                            <td><input type="number" name="child_no[]" class="form-control" value="<?= ($i + 1) * 10 ?>"></td>
                            <td><select name="child_item_code[]" class="form-select"><option value="">Select item</option><?php foreach ($items as $item): ?><?php $code = $item['item_code'] ?? $item['code'] ?? ''; ?><option value="<?= esc($code) ?>"><?= esc($code . ' - ' . ($item['item_name'] ?? $item['name'] ?? '')) ?></option><?php endforeach ?></select></td>
                            <td><input name="component_type[]" class="form-control" value="material"></td>
                            <td><input type="number" step="0.000001" name="qty_used[]" class="form-control text-end" value="<?= $i === 0 ? '1' : '0' ?>"></td>
                            <td><select name="line_uom_code[]" class="form-select"><?php foreach ($uoms as $uom): ?><option value="<?= esc($uom['code'] ?? '') ?>"><?= esc($uom['code'] ?? '') ?></option><?php endforeach ?></select></td>
                            <td><input type="number" step="0.00001" name="factor[]" class="form-control text-end" value="1"></td>
                            <td><input name="line_description[]" class="form-control"></td>
                        </tr>
                    <?php endfor ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> Save BOM</button><a class="btn btn-light" href="<?= site_url('production/boms') ?>">Cancel</a></div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
