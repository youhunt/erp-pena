<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$bom ??= [];
$lines ??= [];
$items ??= [];
$isEdit = (bool) ($isEdit ?? false);
$action ??= $isEdit ? site_url('production/boms/' . (int) ($bom['id'] ?? 0)) : site_url('production/boms');
$val = static fn(string $f, mixed $d = ''): string => (string) old($f, $bom[$f] ?? $d);
$lineRows = $lines !== [] ? $lines : array_fill(0, 5, []);
$itemCode = static fn(array $item): string => trim((string) ($item['item_code'] ?? $item['code'] ?? ''));
$itemName = static fn(array $item): string => trim((string) ($item['item_name'] ?? $item['name'] ?? ''));
$itemLabel = static fn(array $item): string => trim($itemCode($item) . ' - ' . $itemName($item), ' -');
$itemByCode = [];
$itemById = [];
foreach ($items as $item) {
    $code = $itemCode($item);
    if ($code !== '') {
        $itemByCode[$code] = $item;
    }
    if (isset($item['id']) && (int) $item['id'] > 0) {
        $itemById[(int) $item['id']] = $item;
    }
}
?>
<form method="post" action="<?= esc($action, 'attr') ?>">
    <?= csrf_field() ?>
    <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4"><div><h4 class="card-title mb-1"><?= esc($isEdit ? 'Edit BOM' : 'Create BOM') ?></h4><p class="text-muted mb-0">Define parent item, batch quantity, and child components.</p></div><a href="<?= $isEdit ? site_url('production/boms/' . (int) ($bom['id'] ?? 0)) : site_url('production/boms') ?>" class="btn btn-light">Back</a></div>
        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">Site</label><select name="site_code" class="form-select" required><?php foreach ($sites as $site): ?><?php $code=$site['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('site_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($site['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Department</label><select name="department_code" class="form-select" required><?php foreach ($departments as $department): ?><?php $code=$department['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('department_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($department['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Warehouse</label><select name="warehouse_code" class="form-select"><option value="">No Warehouse</option><?php foreach ($warehouses as $warehouse): ?><?php $code=$warehouse['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('warehouse_code')===$code?'selected':'' ?>><?= esc($code . ' - ' . ($warehouse['name'] ?? '')) ?></option><?php endforeach ?></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Parent Item</label><select name="parent_item_code" class="form-select" required><?php foreach ($items as $item): ?><?php $code=$itemCode($item); ?><option value="<?= esc($code) ?>" <?= $val('parent_item_code')===$code?'selected':'' ?>><?= esc($itemLabel($item)) ?></option><?php endforeach ?></select></div>
            <div class="col-md-2 mb-3"><label class="form-label">Type</label><input name="bom_type" class="form-control" value="<?= esc($val('bom_type', 'standard')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Qty/Batch</label><input type="number" step="0.000001" name="qty_batch" class="form-control" required value="<?= esc($val('qty_batch', '1')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">UoM</label><select name="uom_code" class="form-select" required><?php foreach ($uoms as $uom): ?><?php $code=$uom['code']??''; ?><option value="<?= esc($code) ?>" <?= $val('uom_code')===$code?'selected':'' ?>><?= esc($code) ?></option><?php endforeach ?></select></div>
            <div class="col-md-2 mb-3"><label class="form-label">% Ratio</label><input type="number" step="0.000001" name="ratio_percent" class="form-control" value="<?= esc($val('ratio_percent', '100')) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Active Date</label><input type="datetime-local" name="active_date" class="form-control" value="<?= esc(str_replace(' ', 'T', $val('active_date'))) ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Inactive Date</label><input type="datetime-local" name="inactive_date" class="form-control" value="<?= esc(str_replace(' ', 'T', $val('inactive_date'))) ?>"></div>
            <div class="col-md-12 mb-3"><label class="form-label">Description</label><input name="description" class="form-control" maxlength="500" value="<?= esc($val('description')) ?>"></div>
        </div>
    </div></div>
    <div class="card"><div class="card-body">
        <h4 class="card-title mb-3">Child Components</h4>
        <div class="table-responsive"><table class="table table-nowrap align-middle mb-0">
            <thead class="table-light"><tr><th>No</th><th>Child Item</th><th>Type</th><th class="text-end">Qty Used</th><th>UoM</th><th class="text-end">Factor</th><th>Description</th></tr></thead>
            <tbody><?php foreach ($lineRows as $i=>$line): ?><?php
                $child = trim((string) ($line['child_item_code'] ?? ''));
                $childId = (int) ($line['child_item_id'] ?? 0);
                if ($child === '' && $childId > 0 && isset($itemById[$childId])) {
                    $child = $itemCode($itemById[$childId]);
                }
                $childLabel = $child;
                if ($child !== '' && isset($itemByCode[$child])) {
                    $childLabel = $itemLabel($itemByCode[$child]);
                } elseif ($child !== '') {
                    $childLabel = trim($child . ' - ' . (string) ($line['child_item_name'] ?? ''), ' -');
                }
            ?><tr>
                <td><input type="number" name="child_no[]" class="form-control" value="<?= esc($line['child_no'] ?? (($i+1)*10)) ?>"></td>
                <td><select name="child_item_code[]" class="form-select"><option value="">Select item</option><?php if ($child !== '' && ! isset($itemByCode[$child])): ?><option value="<?= esc($child, 'attr') ?>" selected><?= esc($childLabel) ?></option><?php endif ?><?php foreach ($items as $item): ?><?php $code=$itemCode($item); ?><option value="<?= esc($code, 'attr') ?>" <?= $child===$code?'selected':'' ?>><?= esc($itemLabel($item)) ?></option><?php endforeach ?></select></td>
                <td><input name="component_type[]" class="form-control" value="<?= esc($line['component_type'] ?? 'material') ?>"></td>
                <td><input type="number" step="0.000001" name="qty_used[]" class="form-control text-end" value="<?= esc($line['qty_used'] ?? ($i===0?'1':'0')) ?>"></td>
                <td><select name="line_uom_code[]" class="form-select"><?php foreach ($uoms as $uom): ?><?php $code=$uom['code']??''; ?><option value="<?= esc($code) ?>" <?= ($line['uom_code']??'')===$code?'selected':'' ?>><?= esc($code) ?></option><?php endforeach ?></select></td>
                <td><input type="number" step="0.00001" name="factor[]" class="form-control text-end" value="<?= esc($line['factor'] ?? '1') ?>"></td>
                <td><input name="line_description[]" class="form-control" value="<?= esc($line['description'] ?? '') ?>"></td>
            </tr><?php endforeach ?></tbody>
        </table></div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> <?= $isEdit ? 'Update BOM' : 'Save BOM' ?></button><a class="btn btn-light" href="<?= $isEdit ? site_url('production/boms/' . (int) ($bom['id'] ?? 0)) : site_url('production/boms') ?>">Cancel</a></div>
    </div></div>
</form>
<?= $this->endSection() ?>