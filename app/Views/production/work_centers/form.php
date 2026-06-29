<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$workCenter ??= [];
$machines ??= [];
$costs ??= [];
$uoms ??= [];
$costTypes ??= [];
$isEdit = (bool) ($isEdit ?? false);
$action ??= $isEdit ? site_url('production/work-centers/' . (int) ($workCenter['id'] ?? 0)) : site_url('production/work-centers');
$val = static fn (string $field, mixed $default = ''): string => (string) old($field, $workCenter[$field] ?? $default);

if ($costTypes === []) {
    try {
        $db = \Config\Database::connect();
        if ($db->tableExists('costing_cost_types')) {
            $tenant = new \App\Services\TenantContext(session());
            $builder = $db->table('costing_cost_types')->where('deleted_at', null);
            if ($db->fieldExists('is_active', 'costing_cost_types')) {
                $builder->where('is_active', 1);
            }
            if ($tenant->activeCompanyId() !== null && $db->fieldExists('company_id', 'costing_cost_types')) {
                $builder->groupStart()->where('company_id', $tenant->activeCompanyId())->orWhere('company_id', null)->groupEnd();
            }
            $costTypes = $builder->orderBy('cost_group', 'ASC')->orderBy('type', 'ASC')->get(500)->getResultArray();
        }
    } catch (\Throwable) {
        $costTypes = [];
    }
}

$machineRows = $machines !== [] ? $machines : [
    ['no' => 10, 'machine' => $val('machine_code'), 'notes1' => $val('notes'), 'speed' => $val('speed', '0'), 'capacity' => $val('capacity_percent', '100'), 'qtylabor' => $val('qty_labor', '0'), 'workhour' => $val('working_hour', '0'), 'length' => $val('max_length', '0'), 'luom' => $val('length_uom', 'CM'), 'width' => $val('max_width', '0'), 'wuom' => $val('width_uom', 'CM'), 'height' => $val('max_height', '0'), 'huom' => $val('height_uom', 'CM'), 'volume' => $val('max_volume', '0'), 'vuom' => $val('volume_uom', 'M3')],
];
$costRows = $costs !== [] ? $costs : [
    ['costtype' => $val('cost_type', 'Labor'), 'costamount' => $val('cost_amount', '0'), 'costuom' => $val('cost_uom', 'HOUR'), 'notes2' => ''],
];
$uomOptions = static function (string $selected, array $uoms, string $fallback = ''): string {
    $selected = trim($selected !== '' ? $selected : $fallback);
    $html = '<option value="">Select</option>';
    $exists = false;
    foreach ($uoms as $uom) {
        $code = (string) ($uom['code'] ?? $uom['uom_code'] ?? '');
        if ($code === '') {
            continue;
        }
        $name = (string) ($uom['name'] ?? $uom['description'] ?? '');
        $exists = $exists || strtoupper($code) === strtoupper($selected);
        $label = trim($code . ($name !== '' ? ' - ' . $name : ''));
        $html .= '<option value="' . esc($code, 'attr') . '" ' . (strtoupper($selected) === strtoupper($code) ? 'selected' : '') . '>' . esc($label) . '</option>';
    }
    if ($selected !== '' && ! $exists) {
        $html .= '<option value="' . esc($selected, 'attr') . '" selected>' . esc($selected) . '</option>';
    }
    return $html;
};
$costTypeOptions = static function (string $selected, array $costTypes, string $fallback = ''): string {
    $selected = trim($selected !== '' ? $selected : $fallback);
    $html = '<option value="">Select Cost Type</option>';
    $exists = false;
    foreach ($costTypes as $costType) {
        $type = (string) ($costType['type'] ?? $costType['code'] ?? $costType['cost_type'] ?? '');
        if ($type === '') {
            continue;
        }
        $description = trim((string) ($costType['description'] ?? ''));
        $group = trim((string) ($costType['cost_group'] ?? ''));
        $exists = $exists || strtoupper($type) === strtoupper($selected);
        $label = $type;
        if ($description !== '') {
            $label .= ' - ' . $description;
        }
        if ($group !== '') {
            $label .= ' (' . $group . ')';
        }
        $html .= '<option value="' . esc($type, 'attr') . '" ' . (strtoupper($selected) === strtoupper($type) ? 'selected' : '') . '>' . esc($label) . '</option>';
    }
    if ($selected !== '' && ! $exists) {
        $html .= '<option value="' . esc($selected, 'attr') . '" selected>' . esc($selected) . '</option>';
    }
    return $html;
};
$uomBlankOptions = $uomOptions('', $uoms, '');
$uomCmOptions = $uomOptions('CM', $uoms, 'CM');
$uomM3Options = $uomOptions('M3', $uoms, 'M3');
$uomHourOptions = $uomOptions('HOUR', $uoms, 'HOUR');
$costTypeBlankOptions = $costTypeOptions('', $costTypes, '');
$costTypeLaborOptions = $costTypeOptions('Labor', $costTypes, 'Labor');
?>
<style>
    .wc-detail-wrap {
        overflow-x: auto;
        overflow-y: visible;
        padding-bottom: .5rem;
    }
    .wc-machine-table {
        min-width: 1900px;
        table-layout: fixed;
    }
    .wc-cost-table {
        min-width: 900px;
        table-layout: fixed;
    }
    .wc-machine-table th,
    .wc-machine-table td,
    .wc-cost-table th,
    .wc-cost-table td {
        white-space: nowrap;
        vertical-align: middle;
    }
    .wc-machine-table .form-control,
    .wc-machine-table .form-select,
    .wc-cost-table .form-control,
    .wc-cost-table .form-select {
        min-height: 34px;
    }
    .wc-machine-table .select2-container,
    .wc-cost-table .select2-container {
        min-width: 120px;
    }
    .wc-cost-table .costtype-select + .select2-container {
        min-width: 190px;
    }
    .wc-machine-table th:nth-child(1), .wc-machine-table td:nth-child(1) { width: 78px; }
    .wc-machine-table th:nth-child(2), .wc-machine-table td:nth-child(2) { width: 170px; }
    .wc-machine-table th:nth-child(3), .wc-machine-table td:nth-child(3) { width: 260px; }
    .wc-machine-table th:nth-child(4), .wc-machine-table td:nth-child(4),
    .wc-machine-table th:nth-child(5), .wc-machine-table td:nth-child(5),
    .wc-machine-table th:nth-child(6), .wc-machine-table td:nth-child(6),
    .wc-machine-table th:nth-child(7), .wc-machine-table td:nth-child(7),
    .wc-machine-table th:nth-child(8), .wc-machine-table td:nth-child(8),
    .wc-machine-table th:nth-child(10), .wc-machine-table td:nth-child(10),
    .wc-machine-table th:nth-child(12), .wc-machine-table td:nth-child(12),
    .wc-machine-table th:nth-child(14), .wc-machine-table td:nth-child(14) { width: 125px; }
    .wc-machine-table th:nth-child(9), .wc-machine-table td:nth-child(9),
    .wc-machine-table th:nth-child(11), .wc-machine-table td:nth-child(11),
    .wc-machine-table th:nth-child(13), .wc-machine-table td:nth-child(13),
    .wc-machine-table th:nth-child(15), .wc-machine-table td:nth-child(15) { width: 135px; }
    .wc-machine-table th:nth-child(16), .wc-machine-table td:nth-child(16) { width: 105px; }
</style>

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
                    <select name="site_code" class="form-select select2-basic" required>
                        <option value="">Select Site</option>
                        <?php foreach ($sites as $site): ?>
                            <?php $code = (string) ($site['code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('site_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($site['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department_code" class="form-select select2-basic" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $department): ?>
                            <?php $code = (string) ($department['code'] ?? ''); ?>
                            <option value="<?= esc($code) ?>" <?= $val('department_code') === $code ? 'selected' : '' ?>><?= esc($code . ' - ' . ($department['name'] ?? '')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_code" class="form-select select2-basic" required>
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
                    <input type="date" name="active_date" class="form-control" value="<?= esc(substr($val('active_date', date('Y-m-d')), 0, 10)) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Inactive Date</label>
                    <input type="date" name="inactive_date" class="form-control" value="<?= esc(substr($val('inactive_date'), 0, 10)) ?>">
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
                    <p class="text-muted mb-0">Scroll ke kanan untuk detail ukuran dan UoM. UoM mengambil dari master UOM.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addMachineRow"><i class="bx bx-plus me-1"></i> Add Machine</button>
            </div>
            <div class="wc-detail-wrap">
                <table class="table table-bordered table-sm align-middle mb-0 wc-machine-table" id="machineTable">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Machine</th>
                            <th>Notes</th>
                            <th class="text-end">Speed</th>
                            <th class="text-end">% Capacity</th>
                            <th class="text-end">Qty Labor</th>
                            <th class="text-end">Working Hour</th>
                            <th class="text-end">Length</th>
                            <th>L UoM</th>
                            <th class="text-end">Width</th>
                            <th>W UoM</th>
                            <th class="text-end">Height</th>
                            <th>H UoM</th>
                            <th class="text-end">Volume</th>
                            <th>V UoM</th>
                            <th>Action</th>
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
                            <td><select name="machine_luom[]" class="form-select form-select-sm uom-select"><?= $uomOptions((string) ($m['luom'] ?? 'CM'), $uoms, 'CM') ?></select></td>
                            <td><input type="number" step="0.000001" name="machine_width[]" class="form-control form-control-sm text-end" value="<?= esc($m['width'] ?? '0') ?>"></td>
                            <td><select name="machine_wuom[]" class="form-select form-select-sm uom-select"><?= $uomOptions((string) ($m['wuom'] ?? 'CM'), $uoms, 'CM') ?></select></td>
                            <td><input type="number" step="0.000001" name="machine_height[]" class="form-control form-control-sm text-end" value="<?= esc($m['height'] ?? '0') ?>"></td>
                            <td><select name="machine_huom[]" class="form-select form-select-sm uom-select"><?= $uomOptions((string) ($m['huom'] ?? 'CM'), $uoms, 'CM') ?></select></td>
                            <td><input type="number" step="0.000001" name="machine_volume[]" class="form-control form-control-sm text-end" value="<?= esc($m['volume'] ?? '0') ?>"></td>
                            <td><select name="machine_vuom[]" class="form-select form-select-sm uom-select"><?= $uomOptions((string) ($m['vuom'] ?? 'M3'), $uoms, 'M3') ?></select></td>
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
                    <p class="text-muted mb-0">Cost Type mengambil dari master Cost Type. Tambahkan master di menu Cost Type bila pilihan belum tersedia.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addCostRow"><i class="bx bx-plus me-1"></i> Add Cost</button>
            </div>
            <div class="wc-detail-wrap">
                <table class="table table-bordered table-sm align-middle mb-0 wc-cost-table" id="costTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:230px">Cost Type</th>
                            <th class="text-end" style="width:150px">Amount</th>
                            <th style="width:160px">UoM</th>
                            <th>Notes</th>
                            <th style="width:90px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($costRows as $c): ?>
                        <tr>
                            <td><select name="costtype[]" class="form-select form-select-sm costtype-select"><?= $costTypeOptions((string) ($c['costtype'] ?? 'Labor'), $costTypes, 'Labor') ?></select></td>
                            <td><input type="number" step="0.01" name="costamount[]" class="form-control form-control-sm text-end" value="<?= esc($c['costamount'] ?? '0') ?>"></td>
                            <td><select name="costuom[]" class="form-select form-select-sm uom-select"><?= $uomOptions((string) ($c['costuom'] ?? 'HOUR'), $uoms, 'HOUR') ?></select></td>
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
    const uomBlankOptions = <?= json_encode($uomBlankOptions) ?>;
    const uomCmOptions = <?= json_encode($uomCmOptions) ?>;
    const uomM3Options = <?= json_encode($uomM3Options) ?>;
    const uomHourOptions = <?= json_encode($uomHourOptions) ?>;
    const costTypeOptions = <?= json_encode($costTypeBlankOptions) ?>;

    function initSelect2(scope) {
        if (! window.jQuery || ! jQuery.fn.select2) return;
        jQuery(scope || document).find('.uom-select, .select2-basic, .costtype-select').each(function () {
            const el = jQuery(this);
            if (el.data('select2')) return;
            el.select2({ width: '100%' });
        });
    }

    function destroySelect2(scope) {
        if (! window.jQuery || ! jQuery.fn.select2) return;
        jQuery(scope || document).find('.uom-select, .select2-basic, .costtype-select').each(function () {
            const el = jQuery(this);
            if (el.data('select2')) el.select2('destroy');
        });
    }

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

    function optionHtml(kind) {
        if (kind === 'CM') return uomCmOptions;
        if (kind === 'M3') return uomM3Options;
        if (kind === 'HOUR') return uomHourOptions;
        return uomBlankOptions;
    }

    function machineRowHtml(rowNo) {
        return `
            <tr>
                <td><input type="number" name="machine_no[]" class="form-control form-control-sm text-center" value="${rowNo}"></td>
                <td><input name="machine[]" class="form-control form-control-sm machine-input" maxlength="12" value=""></td>
                <td><input name="machine_notes[]" class="form-control form-control-sm" maxlength="300" value=""></td>
                <td><input type="number" step="0.001" name="machine_speed[]" class="form-control form-control-sm text-end" value="0"></td>
                <td><input type="number" step="0.001" name="machine_capacity[]" class="form-control form-control-sm text-end" value="100"></td>
                <td><input type="number" step="0.000001" name="machine_qtylabor[]" class="form-control form-control-sm text-end" value="0"></td>
                <td><input type="number" step="0.001" name="machine_workhour[]" class="form-control form-control-sm text-end" value="0"></td>
                <td><input type="number" step="0.000001" name="machine_length[]" class="form-control form-control-sm text-end" value="0"></td>
                <td><select name="machine_luom[]" class="form-select form-select-sm uom-select">${optionHtml('CM')}</select></td>
                <td><input type="number" step="0.000001" name="machine_width[]" class="form-control form-control-sm text-end" value="0"></td>
                <td><select name="machine_wuom[]" class="form-select form-select-sm uom-select">${optionHtml('CM')}</select></td>
                <td><input type="number" step="0.000001" name="machine_height[]" class="form-control form-control-sm text-end" value="0"></td>
                <td><select name="machine_huom[]" class="form-select form-select-sm uom-select">${optionHtml('CM')}</select></td>
                <td><input type="number" step="0.000001" name="machine_volume[]" class="form-control form-control-sm text-end" value="0"></td>
                <td><select name="machine_vuom[]" class="form-select form-select-sm uom-select">${optionHtml('M3')}</select></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
            </tr>`;
    }

    function costRowHtml() {
        return `
            <tr>
                <td><select name="costtype[]" class="form-select form-select-sm costtype-select">${costTypeOptions}</select></td>
                <td><input type="number" step="0.01" name="costamount[]" class="form-control form-control-sm text-end" value="0"></td>
                <td><select name="costuom[]" class="form-select form-select-sm uom-select">${optionHtml('HOUR')}</select></td>
                <td><input name="cost_notes[]" class="form-control form-control-sm" maxlength="30" value=""></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
            </tr>`;
    }

    document.getElementById('addMachineRow').addEventListener('click', function () {
        const rowNo = (machineTable.querySelectorAll('tr').length + 1) * 10;
        machineTable.insertAdjacentHTML('beforeend', machineRowHtml(rowNo));
        const row = machineTable.lastElementChild;
        renumberMachineRows();
        initSelect2(row);
    });

    document.getElementById('addCostRow').addEventListener('click', function () {
        costTable.insertAdjacentHTML('beforeend', costRowHtml());
        initSelect2(costTable.lastElementChild);
    });

    document.addEventListener('input', function (event) {
        if (event.target && event.target.name === 'machine[]') syncPrimaryMachine();
    });

    document.addEventListener('click', function (event) {
        if (! event.target.classList.contains('remove-row')) return;
        const row = event.target.closest('tr');
        const tbody = row.closest('tbody');
        const isMachineTable = tbody === machineTable;
        destroySelect2(row);
        if (tbody.querySelectorAll('tr').length <= 1) {
            if (isMachineTable) {
                row.outerHTML = machineRowHtml(10);
                initSelect2(machineTable.lastElementChild);
            } else {
                row.outerHTML = costRowHtml();
                initSelect2(costTable.lastElementChild);
            }
        } else {
            row.remove();
        }
        renumberMachineRows();
    });

    form.addEventListener('submit', function () {
        syncPrimaryMachine();
    });

    renumberMachineRows();
    initSelect2(document);
})();
</script>
<?= $this->endSection() ?>
