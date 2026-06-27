<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$workCenter ??= [];
$machines ??= [];
$costs ??= [];
$isEdit = (bool) ($isEdit ?? false);
$action ??= $isEdit ? site_url('production/work-centers/' . (int) ($workCenter['id'] ?? 0)) : site_url('production/work-centers');
$val = static fn (string $field, mixed $default = ''): string => (string) old($field, $workCenter[$field] ?? $default);
$machineRows = $machines !== [] ? $machines : [
    ['no' => 10, 'machine' => $val('machine_code'), 'notes1' => $val('notes'), 'speed' => $val('speed', '0'), 'capacity' => $val('capacity_percent', '100'), 'qtylabor' => $val('qty_labor', '0'), 'workhour' => $val('working_hour', '0'), 'length' => $val('max_length', '0'), 'luom' => $val('length_uom', 'CM'), 'width' => $val('max_width', '0'), 'wuom' => $val('width_uom', 'CM'), 'height' => $val('max_height', '0'), 'huom' => $val('height_uom', 'CM'), 'volume' => $val('max_volume', '0'), 'vuom' => $val('volume_uom', 'M3')],
];
$costRows = $costs !== [] ? $costs : [
    ['costtype' => $val('cost_type', 'Labor'), 'costamount' => $val('cost_amount', '0'), 'costuom' => $val('cost_uom', 'Hour'), 'notes2' => ''],
];
?>
<form method="post" action="<?= esc($action, 'attr') ?>" id="workCenterForm">
    <?= csrf_field() ?>
    <input type="hidden" name="machine_code" id="machine_code_header" value="<?= esc($val('machine_code')) ?>">

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h4 class="card-title mb-1"><?= esc($isEdit ? 'Edit Work Center' : 'Create Work Center') ?></h4>
                    <p class="text-muted mb-0">Define production work center, machine detail, and cost detail.</p>
                </div>
                <a href="<?= $isEdit ? site_url('production/work-centers/' . (int) ($workCenter['id'] ?? 0)) : site_url('production/work-centers') ?>" class="btn btn-light">Back</a>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Site</label>
                    <select name="site_code" class="form-select" required>
                        <option value="">Select Site</option>
                        <?php foreach ($sites as $site): ?>
                            <?php $code = (string) ($site['code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('site_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($site['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department_code" class="form-select" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $department): ?>
                            <?php $code = (string) ($department['code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('department_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($department['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_code" class="form-select" required>
                        <option value="">Select Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <?php $code = (string) ($warehouse['code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('warehouse_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($warehouse['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Work Center</label>
                    <input name="work_center_code" class="form-control" required maxlength="12" value="<?= esc($val('work_center_code')) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Description</label>
                    <input name="description" class="form-control" maxlength="300" value="<?= esc($val('description')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Active Date</label>
                    <input type="date" name="active_date" class="form-control" value="<?= esc($val('active_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Inactive Date</label>
                    <input type="date" name="inactive_date" class="form-control" value="<?= esc($val('inactive_date')) ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Notes</label>
                    <input name="notes" class="form-control" maxlength="300" value="<?= esc($val('notes')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="font-size-15 mb-1">Machine Detail</h5>
                    <p class="text-muted mb-0">Machine wajib diisi minimal 1 row. Row pertama otomatis menjadi primary machine.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addMachineRow"><i class="bx bx-plus me-1"></i> Add Machine</button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0" id="machineTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px">No</th>
                            <th style="min-width:140px">Machine</th>
                            <th style="min-width:180px">Notes</th>
                            <th class="text-end" style="width:110px">Speed</th>
                            <th class="text-end" style="width:110px">% Capacity</th>
                            <th class="text-end" style="width:110px">Qty Labor</th>
                            <th class="text-end" style="width:110px">Working Hour</th>
                            <th class="text-end" style="width:110px">Length</th>
                            <th style="width:90px">L UoM</th>
                            <th class="text-end" style="width:110px">Width</th>
                            <th style="width:90px">W UoM</th>
                            <th class="text-end" style="width:110px">Height</th>
                            <th style="width:90px">H UoM</th>
                            <th class="text-end" style="width:110px">Volume</th>
                            <th style="width:90px">V UoM</th>
                            <th style="width:80px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($machineRows as $i => $m): ?>
                        <tr>
                            <td><input type="number" name="machine_no[]" class="form-control form-control-sm text-center" value="<?= esc($m['no'] ?? (($i + 1) * 10)) ?>"></td>
                            <td><input name="machine[]" class="form-control form-control-sm machine-input" maxlength="12" value="<?= esc($m['machine'] ?? '') ?>" <?= $i === 0 ? 'required' : '' ?>></td>
                            <td><input name="machine_notes[]" class="form-control form-control-sm" maxlength="300" value="<?= esc($m['notes1'] ?? '') ?>"></td>
                            <td><input type="number" step="0.001" name="machine_speed[]" class="form-control form-control-sm text-end" value="<?= esc($m['speed'] ?? '0') ?>"></td>
                            <td><input type="number" step="0.001" name="machine_capacity[]" class="form-control form-control-sm text-end" value="<?= esc($m['capacity'] ?? '100') ?>"></td>
                            <td><input type="number" step="0.000001" name="machine_qtylabor[]" class="form-control form-control-sm text-end" value="<?= esc($m['qtylabor'] ?? '0') ?>"></td>
                            <td><input type="number" step="0.001" name="machine_workhour[]" class="form-control form-control-sm text-end" value="<?= esc($m['workhour'] ?? '0') ?>"></td>
                            <td><input type="number" step="0.000001" name="machine_length[]" class="form-control form-control-sm text-end" value="<?= esc($m['length'] ?? '0') ?>"></td>
                            <td><input name="machine_luom[]" class="form-control form-control-sm" value="<?= esc($m['luom'] ?? 'CM') ?>"></td>
                            <td><input type="number" step="0.000001" name="machine_width[]" class="form-control form-control-sm text-end" value="<?= esc($m['width'] ?? '0') ?>"></td>
                            <td><input name="machine_wuom[]" class="form-control form-control-sm" value="<?= esc($m['wuom'] ?? 'CM') ?>"></td>
                            <td><input type="number" step="0.000001" name="machine_height[]" class="form-control form-control-sm text-end" value="<?= esc($m['height'] ?? '0') ?>"></td>
                            <td><input name="machine_huom[]" class="form-control form-control-sm" value="<?= esc($m['huom'] ?? 'CM') ?>"></td>
                            <td><input type="number" step="0.000001" name="machine_volume[]" class="form-control form-control-sm text-end" value="<?= esc($m['volume'] ?? '0') ?>"></td>
                            <td><input name="machine_vuom[]" class="form-control form-control-sm" value="<?= esc($m['vuom'] ?? 'M3') ?>"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="font-size-15 mb-1">Cost Detail</h5>
                    <p class="text-muted mb-0">Bisa input lebih dari satu tipe biaya, misalnya Labor, Machine, Overhead.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addCostRow"><i class="bx bx-plus me-1"></i> Add Cost</button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0" id="costTable">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:150px">Cost Type</th>
                            <th class="text-end" style="width:150px">Amount</th>
                            <th style="width:120px">UoM</th>
                            <th style="min-width:220px">Notes</th>
                            <th style="width:80px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($costRows as $c): ?>
                        <tr>
                            <td><input name="costtype[]" class="form-control form-control-sm" maxlength="12" value="<?= esc($c['costtype'] ?? '') ?>"></td>
                            <td><input type="number" step="0.01" name="costamount[]" class="form-control form-control-sm text-end" value="<?= esc($c['costamount'] ?? '0') ?>"></td>
                            <td><input name="costuom[]" class="form-control form-control-sm" maxlength="4" value="<?= esc($c['costuom'] ?? 'Hour') ?>"></td>
                            <td><input name="cost_notes[]" class="form-control form-control-sm" maxlength="30" value="<?= esc($c['notes2'] ?? '') ?>"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> <?= $isEdit ? 'Update Work Center' : 'Save Work Center' ?></button>
                <a class="btn btn-light" href="<?= $isEdit ? site_url('production/work-centers/' . (int) ($workCenter['id'] ?? 0)) : site_url('production/work-centers') ?>">Cancel</a>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const form = document.getElementById('workCenterForm');
    const machineTable = document.querySelector('#machineTable tbody');
    const costTable = document.querySelector('#costTable tbody');
    const hiddenMachine = document.getElementById('machine_code_header');

    function syncPrimaryMachine() {
        const firstMachine = machineTable.querySelector('input[name="machine[]"]');
        hiddenMachine.value = firstMachine ? firstMachine.value.trim() : '';
    }

    function renumberMachineRows() {
        machineTable.querySelectorAll('tr').forEach((row, index) => {
            const no = row.querySelector('input[name="machine_no[]"]');
            const machine = row.querySelector('input[name="machine[]"]');
            if (no && no.value === '') no.value = (index + 1) * 10;
            if (machine) machine.required = index === 0;
        });
        syncPrimaryMachine();
    }

    document.getElementById('addMachineRow').addEventListener('click', function () {
        const first = machineTable.querySelector('tr');
        const row = first.cloneNode(true);
        row.querySelectorAll('input').forEach(input => {
            if (input.name === 'machine_no[]') input.value = (machineTable.querySelectorAll('tr').length + 1) * 10;
            else if (input.name.includes('uom') || ['machine_luom[]','machine_wuom[]','machine_huom[]'].includes(input.name)) input.value = 'CM';
            else if (input.name === 'machine_vuom[]') input.value = 'M3';
            else if (input.name === 'machine_capacity[]') input.value = '100';
            else if (input.type === 'number') input.value = '0';
            else input.value = '';
            input.required = false;
        });
        machineTable.appendChild(row);
        renumberMachineRows();
    });

    document.getElementById('addCostRow').addEventListener('click', function () {
        const first = costTable.querySelector('tr');
        const row = first.cloneNode(true);
        row.querySelectorAll('input').forEach(input => {
            if (input.name === 'costamount[]') input.value = '0';
            else if (input.name === 'costuom[]') input.value = 'Hour';
            else input.value = '';
        });
        costTable.appendChild(row);
    });

    document.addEventListener('input', function (event) {
        if (event.target && event.target.name === 'machine[]') syncPrimaryMachine();
    });

    document.addEventListener('click', function (event) {
        if (! event.target.classList.contains('remove-row')) return;
        const row = event.target.closest('tr');
        const tbody = row.closest('tbody');
        if (tbody.querySelectorAll('tr').length <= 1) {
            row.querySelectorAll('input').forEach(input => {
                if (input.type === 'number') input.value = input.name.includes('capacity') ? '100' : '0';
                else input.value = '';
            });
        } else {
            row.remove();
        }
        renumberMachineRows();
    });

    form.addEventListener('submit', function () {
        syncPrimaryMachine();
    });

    renumberMachineRows();
})();
</script>
<?= $this->endSection() ?>
