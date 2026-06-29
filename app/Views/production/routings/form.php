<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$routing ??= [];
$lines ??= [];
$items ??= [];
$sites ??= [];
$departments ??= [];
$warehouses ??= [];
$workCenters ??= [];
$uoms ??= [];
$isEdit = (bool) ($isEdit ?? false);
$action ??= $isEdit ? site_url('production/routings/' . (int) ($routing['id'] ?? 0)) : site_url('production/routings');
$val = static fn (string $field, mixed $default = ''): string => (string) old($field, $routing[$field] ?? $default);
$lineRows = $lines !== [] ? $lines : array_fill(0, 5, []);
$uomCode = static fn (array $uom): string => trim((string) ($uom['code'] ?? ''));
$formatLineDate = static function (mixed $value, string $default = ''): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return $default;
    }
    if ($value === '9999-12-31' || $value === '9999-12-31 00:00:00') {
        return '9999-12-31';
    }

    return substr($value, 0, 10);
};
?>
<style>
    .routing-line-table th,
    .routing-line-table td { vertical-align: middle; }
    .routing-line-table .col-no { width: 80px; min-width: 80px; }
    .routing-line-table .col-name { min-width: 170px; }
    .routing-line-table .col-wc { min-width: 220px; }
    .routing-line-table .col-type { width: 105px; min-width: 105px; }
    .routing-line-table .col-hour { width: 110px; min-width: 110px; }
    .routing-line-table .col-uom { width: 130px; min-width: 130px; }
    .routing-line-table .col-speed { width: 120px; min-width: 120px; }
    .routing-line-table .col-notes { min-width: 170px; }
    .routing-line-table .col-date { width: 155px; min-width: 155px; }
</style>
<form method="post" action="<?= esc($action, 'attr') ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="card-title mb-1"><?= esc($isEdit ? 'Edit Routing' : 'Create Routing') ?></h4>
                    <p class="text-muted mb-0">Screen 1 - routing header per item/site/department/warehouse.</p>
                </div>
                <a href="<?= $isEdit ? site_url('production/routings/' . (int) ($routing['id'] ?? 0)) : site_url('production/routings') ?>" class="btn btn-light">Back</a>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Item Code</label>
                    <select name="item_code" class="form-select" required>
                        <option value="">Select item</option>
                        <?php foreach ($items as $item): ?>
                            <?php $code = trim((string) ($item['item_code'] ?? $item['code'] ?? '')); ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $val('item_code') === $code ? 'selected' : '' ?>><?= esc(trim($code . ' - ' . (string) ($item['item_name'] ?? $item['name'] ?? ''), ' -')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Site</label>
                    <select name="site_code" class="form-select" required>
                        <option value="">Select site</option>
                        <?php foreach ($sites as $site): ?>
                            <?php $code = trim((string) ($site['code'] ?? '')); ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $val('site_code') === $code ? 'selected' : '' ?>><?= esc(trim($code . ' - ' . (string) ($site['name'] ?? ''), ' -')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Department</label>
                    <select name="department_code" class="form-select" required>
                        <option value="">Select department</option>
                        <?php foreach ($departments as $department): ?>
                            <?php $code = trim((string) ($department['code'] ?? '')); ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $val('department_code') === $code ? 'selected' : '' ?>><?= esc(trim($code . ' - ' . (string) ($department['name'] ?? ''), ' -')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_code" class="form-select">
                        <option value="">No Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <?php $code = trim((string) ($warehouse['code'] ?? '')); ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $val('warehouse_code') === $code ? 'selected' : '' ?>><?= esc(trim($code . ' - ' . (string) ($warehouse['name'] ?? ''), ' -')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Description</label>
                    <input name="description" class="form-control" maxlength="300" placeholder="Routing FG KARET A" value="<?= esc($val('description')) ?>">
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-1">Screen 2 - Operation Lines</h4>
            <p class="text-muted mb-3">Isi sequence routing dan work center. Type disesuaikan Main/Alt.</p>
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0 routing-line-table">
                    <thead class="table-light">
                        <tr>
                            <th class="col-no">Route No.</th>
                            <th class="col-name">Routing Name</th>
                            <th class="col-wc">Work Center</th>
                            <th class="col-type">Type</th>
                            <th class="col-hour text-end">Hour</th>
                            <th class="col-uom">Hour UoM</th>
                            <th class="col-speed text-end">Std Speed</th>
                            <th class="col-uom">Speed UoM</th>
                            <th class="col-notes">Notes</th>
                            <th class="col-date">Active Date</th>
                            <th class="col-date">Inactive Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lineRows as $i => $line): ?>
                        <?php
                            $type = (string) ($line['operation_type'] ?? ($i === 0 ? 'Main' : 'Alt'));
                            $activeDate = $formatLineDate($line['active_date'] ?? null);
                            $inactiveDate = $formatLineDate($line['inactive_date'] ?? null, '9999-12-31');
                        ?>
                        <tr>
                            <td class="col-no"><input name="route_no[]" class="form-control" value="<?= esc($line['route_no'] ?? (($i + 1) * 10)) ?>"></td>
                            <td class="col-name"><input name="routing_name[]" class="form-control" value="<?= esc($line['routing_name'] ?? '') ?>"></td>
                            <td class="col-wc">
                                <select name="work_center_code[]" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($workCenters as $wc): ?>
                                        <?php $code = trim((string) ($wc['work_center_code'] ?? '')); ?>
                                        <option value="<?= esc($code, 'attr') ?>" <?= (string) ($line['work_center_code'] ?? '') === $code ? 'selected' : '' ?>><?= esc(trim($code . ' - ' . (string) ($wc['description'] ?? ''), ' -')) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </td>
                            <td class="col-type">
                                <select name="operation_type[]" class="form-select">
                                    <option value="Main" <?= strcasecmp($type, 'Main') === 0 ? 'selected' : '' ?>>Main</option>
                                    <option value="Alt" <?= strcasecmp($type, 'Alt') === 0 ? 'selected' : '' ?>>Alt</option>
                                </select>
                            </td>
                            <td class="col-hour"><input type="number" step="0.000001" name="hour_qty[]" class="form-control text-end" value="<?= esc($line['hour_qty'] ?? ($i === 0 ? '0.5' : '0')) ?>"></td>
                            <td class="col-uom">
                                <select name="hour_uom[]" class="form-select">
                                    <?php $lineHourUom = (string) ($line['hour_uom'] ?? 'Minute'); ?>
                                    <?php foreach (array_unique(array_merge(['Minute', 'Second', 'Hour'], array_map($uomCode, $uoms))) as $uom): ?>
                                        <?php if ($uom === '') { continue; } ?>
                                        <option value="<?= esc($uom, 'attr') ?>" <?= $lineHourUom === $uom ? 'selected' : '' ?>><?= esc($uom) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </td>
                            <td class="col-speed"><input type="number" step="0.000001" name="std_speed[]" class="form-control text-end" value="<?= esc($line['std_speed'] ?? '0') ?>"></td>
                            <td class="col-uom">
                                <select name="speed_uom[]" class="form-select">
                                    <?php $lineSpeedUom = (string) ($line['speed_uom'] ?? 'Mtr'); ?>
                                    <?php foreach (array_unique(array_merge(['Mtr', 'Meter', 'PCS', 'Unit/Hour'], array_map($uomCode, $uoms))) as $uom): ?>
                                        <?php if ($uom === '') { continue; } ?>
                                        <option value="<?= esc($uom, 'attr') ?>" <?= $lineSpeedUom === $uom ? 'selected' : '' ?>><?= esc($uom) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </td>
                            <td class="col-notes"><input name="route_notes[]" class="form-control" value="<?= esc($line['notes'] ?? '') ?>"></td>
                            <td class="col-date"><input type="date" name="active_date[]" class="form-control" value="<?= esc($activeDate) ?>"></td>
                            <td class="col-date"><input type="date" name="inactive_date[]" class="form-control" value="<?= esc($inactiveDate) ?>"></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> <?= $isEdit ? 'Update Routing' : 'Save Routing' ?></button>
                <a class="btn btn-light" href="<?= $isEdit ? site_url('production/routings/' . (int) ($routing['id'] ?? 0)) : site_url('production/routings') ?>">Cancel</a>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
